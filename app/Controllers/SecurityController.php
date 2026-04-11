<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\BlockerService;
use App\Services\Database;
use App\Services\SecurityLogger;
use PDO;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class SecurityController
{
    // Only admin/owner/spv can access
    private function guard(): ?Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $role = strtolower(trim((string) ($auth['role'] ?? '')));
        if (!in_array($role, ['admin', 'administrator', 'owner', 'spv', 'superadmin', 'super-admin'], true)) {
            toast_add('Anda tidak punya akses ke Security Monitor.', 'error');
            return Response::redirect('/dashboard');
        }
        return null;
    }

    /** @return array<string,mixed> */
    private function auth(): array
    {
        return is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
    }

    // ── Main dashboard page ───────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        if ($g = $this->guard()) return $g;

        $tab = strtolower(trim((string) $request->input('tab', 'overview')));
        if (!in_array($tab, ['overview', 'events', 'audit', 'blocks'], true)) {
            $tab = 'overview';
        }

        try {
            $pdo = Database::connection();
            $payload = match ($tab) {
                'events' => $this->eventsPayload($pdo, $request),
                'audit'  => $this->auditPayload($pdo, $request),
                'blocks' => $this->blocksPayload($pdo, $request),
                default  => $this->overviewPayload($pdo),
            };
        } catch (Throwable) {
            $payload = [];
        }

        $html = app()->view()->render('security/index', [
            'title'      => 'Security Monitor',
            'auth'       => $this->auth(),
            'activeMenu' => 'security',
            'tab'        => $tab,
            ...$payload,
        ]);

        return Response::html($html);
    }

    // ── AJAX datatable endpoints ──────────────────────────────────────────────

    public function datatableEvents(Request $request): Response
    {
        if ($g = $this->guard()) {
            return Response::json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []], 403);
        }

        try {
            $pdo    = Database::connection();
            $params = $request->all();
            $draw   = max(0, (int) ($params['draw'] ?? 0));
            $start  = max(0, (int) ($params['start'] ?? 0));
            $length = min(100, max(10, (int) ($params['length'] ?? 25)));
            $search   = trim((string) (($params['search']['value'] ?? '') ?: ''));
            $severity = trim((string) ($params['severity'] ?? ''));
            $ip       = trim((string) ($params['ip'] ?? ''));
            $dari     = trim((string) ($params['dari'] ?? ''));
            $sampai   = trim((string) ($params['sampai'] ?? ''));

            [$where, $bind] = $this->buildEventsWhere($search, $severity, $ip, $dari, $sampai);

            $total = (int) $pdo->query('SELECT COUNT(*) FROM security_events')->fetchColumn();

            $stmtC = $pdo->prepare('SELECT COUNT(*) FROM security_events' . $where);
            foreach ($bind as $k => $v) $stmtC->bindValue($k, $v);
            $stmtC->execute();
            $filtered = (int) $stmtC->fetchColumn();

            $stmt = $pdo->prepare(
                'SELECT id, occurred_at, event_code, category, severity, risk_score,
                        actor_type, user_id, ip_address, path, detection_stage,
                        detection_source, action_taken
                 FROM security_events' . $where .
                ' ORDER BY id DESC LIMIT :limit OFFSET :offset'
            );
            foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return Response::json(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows]);
        } catch (Throwable $e) {
            return Response::json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function datatableAudit(Request $request): Response
    {
        if ($g = $this->guard()) {
            return Response::json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []], 403);
        }

        try {
            $pdo    = Database::connection();
            $params = $request->all();
            $draw   = max(0, (int) ($params['draw'] ?? 0));
            $start  = max(0, (int) ($params['start'] ?? 0));
            $length = min(100, max(10, (int) ($params['length'] ?? 25)));
            $search    = trim((string) (($params['search']['value'] ?? '') ?: ''));
            $module    = trim((string) ($params['module'] ?? ''));
            $action    = strtoupper(trim((string) ($params['action'] ?? '')));
            $sensitive = trim((string) ($params['sensitive'] ?? ''));
            $dari      = trim((string) ($params['dari'] ?? ''));
            $sampai    = trim((string) ($params['sampai'] ?? ''));

            $where = ' WHERE 1=1';
            $bind  = [];
            if ($search !== '') {
                $where .= ' AND (a.module_name LIKE :s OR a.action_name LIKE :s OR a.target_type LIKE :s OR a.diff_summary LIKE :s)';
                $bind[':s'] = '%' . $search . '%';
            }
            if ($module !== '') { $where .= ' AND a.module_name = :mod'; $bind[':mod'] = $module; }
            if ($action !== '') { $where .= ' AND a.action_name = :act'; $bind[':act'] = $action; }
            if ($sensitive === '1') { $where .= ' AND a.is_sensitive = 1'; }
            if ($dari !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
                $where .= ' AND a.occurred_at >= :dari'; $bind[':dari'] = $dari . ' 00:00:00';
            }
            if ($sampai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
                $where .= ' AND a.occurred_at <= :sampai'; $bind[':sampai'] = $sampai . ' 23:59:59';
            }

            $total = (int) $pdo->query('SELECT COUNT(*) FROM admin_audit_logs')->fetchColumn();
            $stmtC = $pdo->prepare('SELECT COUNT(*) FROM admin_audit_logs a' . $where);
            foreach ($bind as $k => $v) $stmtC->bindValue($k, $v);
            $stmtC->execute();
            $filtered = (int) $stmtC->fetchColumn();

            $stmt = $pdo->prepare(
                'SELECT a.id, a.occurred_at, a.user_id, u.name AS user_name,
                        a.module_name, a.action_name, a.target_type, a.target_id,
                        a.diff_summary, a.is_sensitive, a.risk_score, a.ip_address
                 FROM admin_audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id' . $where .
                ' ORDER BY a.id DESC LIMIT :limit OFFSET :offset'
            );
            foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return Response::json(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows]);
        } catch (Throwable $e) {
            return Response::json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function datatableBlocks(Request $request): Response
    {
        if ($g = $this->guard()) {
            return Response::json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []], 403);
        }

        try {
            $pdo    = Database::connection();
            $params = $request->all();
            $draw   = max(0, (int) ($params['draw'] ?? 0));
            $start  = max(0, (int) ($params['start'] ?? 0));
            $length = min(100, max(10, (int) ($params['length'] ?? 25)));
            $search     = trim((string) (($params['search']['value'] ?? '') ?: ''));
            $activeOnly = (string) ($params['active_only'] ?? '1') !== '0';

            $where = ' WHERE 1=1';
            $bind  = [];
            if ($activeOnly) { $where .= ' AND is_active = 1'; }
            if ($search !== '') {
                $where .= ' AND (block_value LIKE :s OR reason_code LIKE :s OR blocked_by LIKE :s)';
                $bind[':s'] = '%' . $search . '%';
            }

            $total = (int) $pdo->query('SELECT COUNT(*) FROM security_blocks')->fetchColumn();
            $stmtC = $pdo->prepare('SELECT COUNT(*) FROM security_blocks' . $where);
            foreach ($bind as $k => $v) $stmtC->bindValue($k, $v);
            $stmtC->execute();
            $filtered = (int) $stmtC->fetchColumn();

            $stmt = $pdo->prepare(
                'SELECT id, created_at, block_type, block_value, reason_code,
                        severity, expires_at, is_active, blocked_by, unblocked_at, notes
                 FROM security_blocks' . $where .
                ' ORDER BY id DESC LIMIT :limit OFFSET :offset'
            );
            foreach ($bind as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return Response::json(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows]);
        } catch (Throwable $e) {
            return Response::json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    // ── Unblock action ────────────────────────────────────────────────────────

    public function unblock(Request $request): Response
    {
        if ($g = $this->guard()) return $g;

        $type  = strtolower(trim((string) $request->input('type', '')));
        $value = trim((string) $request->input('value', ''));

        if (!in_array($type, ['ip', 'user'], true) || $value === '') {
            toast_add('Data tidak valid.', 'error');
            return Response::redirect('/security?tab=blocks');
        }

        if ($type === 'ip') {
            BlockerService::unblockIp($value);
        } else {
            BlockerService::unblockUser((int) $value);
        }

        SecurityLogger::logAudit('security', 'UNBLOCK', $type, $value,
            null, ['unblocked_by' => (int) ($_SESSION['auth']['id'] ?? 0)], true);

        toast_add(ucfirst($type) . ' ' . $value . ' berhasil di-unblock.', 'success');
        return Response::redirect('/security?tab=blocks');
    }

    // ── Private payload builders ──────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function overviewPayload(PDO $pdo): array
    {
        $stats = [
            'total_events_today'    => 0,
            'total_events_week'     => 0,
            'critical_events_today' => 0,
            'active_blocks'         => 0,
            'failed_logins_today'   => 0,
            'audit_actions_today'   => 0,
        ];

        try {
            $stats['total_events_today'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM security_events WHERE DATE(occurred_at) = CURDATE()"
            )->fetchColumn();

            $stats['total_events_week'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM security_events WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            )->fetchColumn();

            $stats['critical_events_today'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM security_events WHERE DATE(occurred_at) = CURDATE() AND severity IN ('high','critical')"
            )->fetchColumn();

            $stats['active_blocks'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM security_blocks WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())"
            )->fetchColumn();

            $stats['failed_logins_today'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM auth_activity WHERE DATE(occurred_at) = CURDATE() AND result = 'failed'"
            )->fetchColumn();

            $stats['audit_actions_today'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM admin_audit_logs WHERE DATE(occurred_at) = CURDATE()"
            )->fetchColumn();
        } catch (Throwable) {}

        // Timeline: last 24h events grouped by hour
        $timeline = [];
        try {
            $stmt = $pdo->query(
                "SELECT DATE_FORMAT(occurred_at, '%H:00') AS hour,
                        COUNT(*) AS total,
                        SUM(severity IN ('high','critical')) AS high_count
                 FROM security_events
                 WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                 GROUP BY DATE_FORMAT(occurred_at, '%Y-%m-%d %H'), DATE_FORMAT(occurred_at, '%H:00')
                 ORDER BY MIN(occurred_at) ASC"
            );
            $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // Top event codes today
        $topEvents = [];
        try {
            $stmt = $pdo->query(
                "SELECT event_code, severity, COUNT(*) AS cnt
                 FROM security_events
                 WHERE DATE(occurred_at) = CURDATE()
                 GROUP BY event_code, severity
                 ORDER BY cnt DESC
                 LIMIT 8"
            );
            $topEvents = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // Top attacker IPs today
        $topIps = [];
        try {
            $stmt = $pdo->query(
                "SELECT ip_address, COUNT(*) AS cnt, MAX(risk_score) AS max_risk
                 FROM security_events
                 WHERE DATE(occurred_at) = CURDATE() AND ip_address <> ''
                 GROUP BY ip_address
                 ORDER BY cnt DESC
                 LIMIT 5"
            );
            $topIps = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        // Recent critical events
        $recentCritical = [];
        try {
            $stmt = $pdo->query(
                "SELECT occurred_at, event_code, severity, ip_address, action_taken
                 FROM security_events
                 WHERE severity IN ('high','critical')
                 ORDER BY id DESC LIMIT 10"
            );
            $recentCritical = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {}

        return compact('stats', 'timeline', 'topEvents', 'topIps', 'recentCritical');
    }

    /** @return array<string,mixed> */
    private function eventsPayload(PDO $pdo, Request $request): array
    {
        $severities = ['low', 'medium', 'high', 'critical'];
        $categories = [];
        try {
            $stmt = $pdo->query("SELECT DISTINCT category FROM security_events ORDER BY category ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable) {}

        return compact('severities', 'categories');
    }

    /** @return array<string,mixed> */
    private function auditPayload(PDO $pdo, Request $request): array
    {
        $modules = [];
        $actions = ['CREATE', 'UPDATE', 'DELETE', 'UPLOAD', 'EXPORT', 'UNBLOCK'];
        try {
            $stmt = $pdo->query("SELECT DISTINCT module_name FROM admin_audit_logs ORDER BY module_name ASC");
            $modules = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable) {}

        return compact('modules', 'actions');
    }

    /** @return array<string,mixed> */
    private function blocksPayload(PDO $pdo, Request $request): array
    {
        $activeCount = 0;
        try {
            $activeCount = (int) $pdo->query(
                "SELECT COUNT(*) FROM security_blocks WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())"
            )->fetchColumn();
        } catch (Throwable) {}

        return compact('activeCount');
    }

    /**
     * @return array{0: string, 1: array<string,mixed>}
     */
    private function buildEventsWhere(string $search, string $severity, string $ip, string $dari, string $sampai): array
    {
        $where = ' WHERE 1=1';
        $bind  = [];

        if ($search !== '') {
            $where .= ' AND (event_code LIKE :s OR ip_address LIKE :s OR detection_source LIKE :s OR path LIKE :s)';
            $bind[':s'] = '%' . $search . '%';
        }
        if ($severity !== '' && in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $where .= ' AND severity = :sev'; $bind[':sev'] = $severity;
        }
        if ($ip !== '') {
            $where .= ' AND ip_address LIKE :ip'; $bind[':ip'] = '%' . $ip . '%';
        }
        if ($dari !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
            $where .= ' AND occurred_at >= :dari'; $bind[':dari'] = $dari . ' 00:00:00';
        }
        if ($sampai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
            $where .= ' AND occurred_at <= :sampai'; $bind[':sampai'] = $sampai . ' 23:59:59';
        }

        return [$where, $bind];
    }
}
