<?php

/**
 * Security Phase 3 — Audit Verification
 * Run: php tests/security_phase3_verify.php
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';

$app = require $basePath . '/bootstrap/app.php';

use App\Services\Database;
use App\Services\SecurityLogger;

$pass = 0;
$fail = 0;

function check(string $label, bool $result): void
{
    global $pass, $fail;
    if ($result) {
        echo "[PASS] {$label}\n";
        $pass++;
    } else {
        echo "[FAIL] {$label}\n";
        $fail++;
    }
}

try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    echo "[ERROR] DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ── [1] Tabel admin_audit_logs ada ───────────────────────────────────────────
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'admin_audit_logs'");
check('admin_audit_logs table exists', (int) $stmt->fetchColumn() === 1);

// ── [2] Kolom is_sensitive ada ────────────────────────────────────────────────
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'admin_audit_logs' AND column_name = 'is_sensitive'");
check('admin_audit_logs.is_sensitive column exists', (int) $stmt->fetchColumn() === 1);

// ── [3] SecurityLogger::logAudit CREATE ──────────────────────────────────────
$_SESSION['auth'] = ['id' => 1, 'role' => 'admin'];
$_SERVER['_SECURITY_REQUEST_ID'] = SecurityLogger::generateRequestId();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

SecurityLogger::logAudit('barang', 'CREATE', 'barang', '999',
    null, ['nama_barang' => 'Test Item', 'harga_jual' => 10000]);

$stmt = $pdo->prepare("SELECT id, module_name, action_name, target_type, target_id, before_snapshot, after_snapshot, diff_summary FROM admin_audit_logs WHERE module_name = 'barang' AND action_name = 'CREATE' AND target_id = '999' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('logAudit CREATE — row inserted', is_array($row));
check('logAudit CREATE — module_name correct', ($row['module_name'] ?? '') === 'barang');
check('logAudit CREATE — action_name correct', ($row['action_name'] ?? '') === 'CREATE');
check('logAudit CREATE — before_snapshot is null', ($row['before_snapshot'] ?? null) === null);
check('logAudit CREATE — after_snapshot has data', ($row['after_snapshot'] ?? '') !== '');
check('logAudit CREATE — diff_summary is Created', str_contains((string) ($row['diff_summary'] ?? ''), 'Created'));

// ── [4] SecurityLogger::logAudit UPDATE with diff ────────────────────────────
$before = ['nama_barang' => 'Old Name', 'harga_jual' => 5000];
$after  = ['nama_barang' => 'New Name', 'harga_jual' => 7500];
SecurityLogger::logAudit('barang', 'UPDATE', 'barang', '888', $before, $after);

$stmt = $pdo->prepare("SELECT diff_summary, before_snapshot, after_snapshot FROM admin_audit_logs WHERE module_name = 'barang' AND action_name = 'UPDATE' AND target_id = '888' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('logAudit UPDATE — row inserted', is_array($row));
check('logAudit UPDATE — before_snapshot stored', ($row['before_snapshot'] ?? '') !== '');
check('logAudit UPDATE — after_snapshot stored', ($row['after_snapshot'] ?? '') !== '');
check('logAudit UPDATE — diff_summary has changed fields', str_contains((string) ($row['diff_summary'] ?? ''), 'Changed'));

// ── [5] SecurityLogger::logAudit DELETE ──────────────────────────────────────
SecurityLogger::logAudit('barang', 'DELETE', 'barang', '777',
    ['nama_barang' => 'Deleted Item'], null);

$stmt = $pdo->prepare("SELECT diff_summary FROM admin_audit_logs WHERE module_name = 'barang' AND action_name = 'DELETE' AND target_id = '777' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('logAudit DELETE — row inserted', is_array($row));
check('logAudit DELETE — diff_summary is Deleted', str_contains((string) ($row['diff_summary'] ?? ''), 'Deleted'));

// ── [6] is_sensitive flag tersimpan ──────────────────────────────────────────
SecurityLogger::logAudit('users', 'DELETE', 'users', '555',
    ['name' => 'Test User'], null, true, SecurityLogger::RISK_MEDIUM);

$stmt = $pdo->prepare("SELECT is_sensitive, risk_score FROM admin_audit_logs WHERE module_name = 'users' AND action_name = 'DELETE' AND target_id = '555' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('logAudit sensitive — is_sensitive = 1', (int) ($row['is_sensitive'] ?? 0) === 1);
check('logAudit sensitive — risk_score = RISK_MEDIUM', (int) ($row['risk_score'] ?? 0) === SecurityLogger::RISK_MEDIUM);

// ── [7] Audit rules Phase 3 terseed ──────────────────────────────────────────
$auditRules = [
    'USER_BULK_DELETE_ATTEMPT',
    'USER_EXPORT_ABUSE',
    'USER_CONFIG_CHANGE',
    'USER_ROLE_CHANGE',
    'USER_SUSPICIOUS_UPLOAD',
    'USER_MIME_EXTENSION_MISMATCH',
    'USER_DOUBLE_EXTENSION_UPLOAD',
    'USER_PRICE_MANIPULATION',
    'USER_PARAMETER_TAMPERING',
    'USER_FORBIDDEN_ROUTE_ACCESS',
];
$placeholders = implode(',', array_fill(0, count($auditRules), '?'));
$stmt = $pdo->prepare("SELECT rule_code FROM security_event_rules WHERE rule_code IN ({$placeholders})");
$stmt->execute($auditRules);
$seeded = $stmt->fetchAll(PDO::FETCH_COLUMN);
check('audit rules seeded — count = ' . count($auditRules), count($seeded) === count($auditRules));

// ── [8] USER_ROLE_CHANGE event log ───────────────────────────────────────────
SecurityLogger::logSecurityEvent('USER_ROLE_CHANGE', 'audit', 'medium',
    SecurityLogger::RISK_MEDIUM, 'VerifyTest',
    ['target_user_id' => 99, 'old_role' => 2, 'new_role' => 3], 'logged');

$stmt = $pdo->prepare("SELECT event_code, severity, risk_score FROM security_events WHERE event_code = 'USER_ROLE_CHANGE' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('USER_ROLE_CHANGE event — inserted', is_array($row));
check('USER_ROLE_CHANGE event — severity medium', ($row['severity'] ?? '') === 'medium');
check('USER_ROLE_CHANGE event — risk_score correct', (int) ($row['risk_score'] ?? 0) === SecurityLogger::RISK_MEDIUM);

// ── [9] USER_DOUBLE_EXTENSION_UPLOAD event log ───────────────────────────────
SecurityLogger::logSecurityEvent('USER_DOUBLE_EXTENSION_UPLOAD', 'upload', 'critical',
    SecurityLogger::RISK_CRITICAL, 'VerifyTest',
    ['filename' => 'shell.php.jpg', 'inner_ext' => 'php'], 'blocked');

$stmt = $pdo->prepare("SELECT event_code, severity, action_taken FROM security_events WHERE event_code = 'USER_DOUBLE_EXTENSION_UPLOAD' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('USER_DOUBLE_EXTENSION_UPLOAD — inserted', is_array($row));
check('USER_DOUBLE_EXTENSION_UPLOAD — severity critical', ($row['severity'] ?? '') === 'critical');
check('USER_DOUBLE_EXTENSION_UPLOAD — action_taken blocked', ($row['action_taken'] ?? '') === 'blocked');

// ── [10] No raw sensitive data in audit log ───────────────────────────────────
SecurityLogger::logAudit('users', 'UPDATE', 'users', '444',
    ['name' => 'Test', 'pass' => 'secret123'], ['name' => 'Test2']);

$stmt = $pdo->prepare("SELECT before_snapshot FROM admin_audit_logs WHERE module_name = 'users' AND action_name = 'UPDATE' AND target_id = '444' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$beforeJson = (string) ($row['before_snapshot'] ?? '');
check('No raw password in audit snapshot', !str_contains($beforeJson, 'secret123'));

// ── Summary ───────────────────────────────────────────────────────────────────
$total = $pass + $fail;
echo "\n[{$pass}/{$total} passed]\n";
if ($fail > 0) {
    echo "[WARNING] {$fail} test(s) failed.\n";
    exit(1);
}
echo "[OK] Phase 3 Audit verification complete.\n";
