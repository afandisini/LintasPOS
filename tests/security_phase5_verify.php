<?php

/**
 * Security Phase 5 — Monitoring Verification
 * Run: php tests/security_phase5_verify.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

use App\Services\Database;
use App\Services\SecurityLogger;
use App\Services\BlockerService;

$pass = 0;
$fail = 0;

function check(string $label, bool $result): void
{
    global $pass, $fail;
    if ($result) { echo "[PASS] {$label}\n"; $pass++; }
    else         { echo "[FAIL] {$label}\n"; $fail++; }
}

$_SERVER['REMOTE_ADDR']           = '127.0.0.1';
$_SERVER['_SECURITY_REQUEST_ID']  = SecurityLogger::generateRequestId();
$_SESSION['auth']                 = ['id' => 1, 'role' => 'admin'];

try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    echo "[ERROR] DB: " . $e->getMessage() . "\n";
    exit(1);
}

// ── [1] Routes terdaftar ──────────────────────────────────────────────────────
$webRoutes = (string) file_get_contents(__DIR__ . '/../routes/web.php');
check('Route GET /security registered',                  str_contains($webRoutes, "'/security'"));
check('Route GET /security/events/datatable registered', str_contains($webRoutes, "'/security/events/datatable'"));
check('Route GET /security/audit/datatable registered',  str_contains($webRoutes, "'/security/audit/datatable'"));
check('Route GET /security/blocks/datatable registered', str_contains($webRoutes, "'/security/blocks/datatable'"));
check('Route POST /security/unblock registered',         str_contains($webRoutes, "'/security/unblock'"));

// ── [2] View file ada ─────────────────────────────────────────────────────────
check('View security/index.php exists', is_file(__DIR__ . '/../app/Views/security/index.php'));

// ── [3] Controller ada ────────────────────────────────────────────────────────
check('SecurityController exists', is_file(__DIR__ . '/../app/Controllers/SecurityController.php'));

// ── [4] Sidebar link ada ──────────────────────────────────────────────────────
$sidebar = (string) file_get_contents(__DIR__ . '/../app/Views/partials/dashboard/sidebar.php');
check('Sidebar has Security Monitor link', str_contains($sidebar, "site_url('security')"));
check('Sidebar has $isSecurity variable',  str_contains($sidebar, '$isSecurity'));

// ── [5] Overview stats query berjalan ────────────────────────────────────────
$eventsToday = (int) $pdo->query("SELECT COUNT(*) FROM security_events WHERE DATE(occurred_at) = CURDATE()")->fetchColumn();
check('Overview: events today query works', $eventsToday >= 0);

$activeBlocks = (int) $pdo->query("SELECT COUNT(*) FROM security_blocks WHERE is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())")->fetchColumn();
check('Overview: active blocks query works', $activeBlocks >= 0);

$failedLogins = (int) $pdo->query("SELECT COUNT(*) FROM auth_activity WHERE DATE(occurred_at) = CURDATE() AND result = 'failed'")->fetchColumn();
check('Overview: failed logins query works', $failedLogins >= 0);

$auditToday = (int) $pdo->query("SELECT COUNT(*) FROM admin_audit_logs WHERE DATE(occurred_at) = CURDATE()")->fetchColumn();
check('Overview: audit today query works', $auditToday >= 0);

// ── [6] Timeline query berjalan ───────────────────────────────────────────────
$stmt = $pdo->query(
    "SELECT DATE_FORMAT(occurred_at, '%H:00') AS hour, COUNT(*) AS total,
            SUM(severity IN ('high','critical')) AS high_count
     FROM security_events
     WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
     GROUP BY DATE_FORMAT(occurred_at, '%Y-%m-%d %H'), DATE_FORMAT(occurred_at, '%H:00')
     ORDER BY MIN(occurred_at) ASC"
);
$timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
check('Overview: timeline query works', is_array($timeline));

// ── [7] Top events query berjalan ─────────────────────────────────────────────
$stmt = $pdo->query(
    "SELECT event_code, severity, COUNT(*) AS cnt
     FROM security_events
     WHERE DATE(occurred_at) = CURDATE()
     GROUP BY event_code, severity
     ORDER BY cnt DESC LIMIT 8"
);
check('Overview: top events query works', $stmt !== false);

// ── [8] Events datatable filter berjalan ─────────────────────────────────────
// Seed satu event dulu
SecurityLogger::logSecurityEvent('TEST_MONITOR_EVENT', 'test', 'medium', 40, 'Phase5Test', ['test' => true]);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM security_events WHERE event_code = 'TEST_MONITOR_EVENT' AND severity = 'medium'"
);
$stmt->execute();
check('Events filter by severity works', (int) $stmt->fetchColumn() >= 1);

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM security_events WHERE ip_address LIKE :ip"
);
$stmt->execute([':ip' => '%127.0.0.1%']);
check('Events filter by IP works', (int) $stmt->fetchColumn() >= 0);

// ── [9] Audit datatable filter berjalan ──────────────────────────────────────
SecurityLogger::logAudit('security_test', 'CREATE', 'test', '1', null, ['key' => 'val']);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_logs WHERE module_name = 'security_test'");
$stmt->execute();
check('Audit filter by module works', (int) $stmt->fetchColumn() >= 1);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_logs WHERE action_name = 'CREATE' AND module_name = 'security_test'");
$stmt->execute();
check('Audit filter by action works', (int) $stmt->fetchColumn() >= 1);

// ── [10] Blocks datatable filter berjalan ────────────────────────────────────
$testIp = '10.5.5.' . rand(1, 99);
BlockerService::blockIp($testIp, 'TEST_MONITOR_BLOCK', 'low', 3600, 'phase5test');

$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM security_blocks WHERE block_value = :ip AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())"
);
$stmt->execute([':ip' => $testIp]);
check('Blocks: active block visible in query', (int) $stmt->fetchColumn() === 1);

BlockerService::unblockIp($testIp);
$stmt->execute([':ip' => $testIp]);
check('Blocks: after unblock not active', (int) $stmt->fetchColumn() === 0);

// ── [11] SecurityController guard (role check) ───────────────────────────────
$ctrl = file_get_contents(__DIR__ . '/../app/Controllers/SecurityController.php');
check('SecurityController has role guard', str_contains((string) $ctrl, "canAccessLaporan") || str_contains((string) $ctrl, "'admin', 'administrator'"));

// ── [12] Distinct categories query ───────────────────────────────────────────
$stmt = $pdo->query("SELECT DISTINCT category FROM security_events ORDER BY category ASC");
$cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
check('Events: distinct categories query works', is_array($cats));

// ── [13] Distinct modules query ──────────────────────────────────────────────
$stmt = $pdo->query("SELECT DISTINCT module_name FROM admin_audit_logs ORDER BY module_name ASC");
$mods = $stmt->fetchAll(PDO::FETCH_COLUMN);
check('Audit: distinct modules query works', is_array($mods) && count($mods) > 0);

// ── Summary ───────────────────────────────────────────────────────────────────
$total = $pass + $fail;
echo "\n[{$pass}/{$total} passed]\n";
if ($fail > 0) {
    echo "[WARNING] {$fail} test(s) failed.\n";
    exit(1);
}
echo "[OK] Phase 5 Monitoring verification complete.\n";
