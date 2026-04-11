<?php
// Phase 1 Verification Script
// Run: php tests/security_phase1_verify.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$_SERVER['REMOTE_ADDR']   = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'VerifyBot/1.0';
$_SERVER['REQUEST_URI']   = '/verify';
$_SERVER['QUERY_STRING']  = 'test=1';
$_SERVER['_SECURITY_REQUEST_ID'] = 'verify-' . bin2hex(random_bytes(8));
$_SESSION = ['auth' => ['id' => 1, 'name' => 'Admin Test', 'username' => 'admin']];

use App\Services\SecurityLogger;
use App\Services\Database;

$pdo = Database::connection();
$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [PASS] {$label}\n"; }
    else      { $fail++; echo "  [FAIL] {$label}\n"; }
}

// ── Before counts ─────────────────────────────────────────────────────────
$before = [];
foreach (['request_activity','security_events','auth_activity','admin_audit_logs'] as $t) {
    $before[$t] = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
}

echo "\n=== Phase 1 Security Logger — Verification ===\n\n";

// ── Test 1: request_activity ──────────────────────────────────────────────
echo "[1] request_activity\n";
SecurityLogger::logRequest('GET', '/verify', 200, 55, false, 0, 'test=1');
$after = (int) $pdo->query("SELECT COUNT(*) FROM request_activity")->fetchColumn();
check('Row inserted', $after === $before['request_activity'] + 1);

$row = $pdo->query("SELECT * FROM request_activity ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('method = GET',    ($row['method'] ?? '') === 'GET');
check('status_code = 200', (int)($row['status_code'] ?? 0) === 200);
check('response_time_ms = 55', (int)($row['response_time_ms'] ?? 0) === 55);
check('request_id set', !empty($row['request_id'] ?? ''));

// ── Test 2: auth_activity — login success ─────────────────────────────────
echo "\n[2] auth_activity — LOGIN_SUCCESS\n";
SecurityLogger::logAuth(SecurityLogger::AUTH_LOGIN_SUCCESS, 'success', 'admin', 1, 1, 0);
$after = (int) $pdo->query("SELECT COUNT(*) FROM auth_activity")->fetchColumn();
check('Row inserted', $after === $before['auth_activity'] + 1);

$row = $pdo->query("SELECT * FROM auth_activity ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('auth_event = AUTH_LOGIN_SUCCESS', ($row['auth_event'] ?? '') === 'AUTH_LOGIN_SUCCESS');
check('result = success',               ($row['result'] ?? '') === 'success');
check('user_id = 1',                    (int)($row['user_id'] ?? 0) === 1);
check('identifier masked (no raw)',     !str_contains((string)($row['identifier_masked'] ?? ''), '@') || str_contains((string)($row['identifier_masked'] ?? ''), '***'));

// ── Test 3: auth_activity — login failed ──────────────────────────────────
echo "\n[3] auth_activity — LOGIN_FAILED\n";
SecurityLogger::logAuth(SecurityLogger::AUTH_LOGIN_FAILED, 'failed', 'hacker@evil.com', null, 3, 15);
$row = $pdo->query("SELECT * FROM auth_activity ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('auth_event = AUTH_LOGIN_FAILED', ($row['auth_event'] ?? '') === 'AUTH_LOGIN_FAILED');
check('result = failed',                ($row['result'] ?? '') === 'failed');
check('user_id = NULL',                 $row['user_id'] === null);
check('risk_score = 15',               (int)($row['risk_score'] ?? 0) === 15);
check('identifier masked',             str_contains((string)($row['identifier_masked'] ?? ''), '***'));

// ── Test 4: security_events ───────────────────────────────────────────────
echo "\n[4] security_events — SQLI_PATTERN\n";
SecurityLogger::logSecurityEvent(
    SecurityLogger::EVT_SQLI_PATTERN,
    'injection',
    'high',
    70,
    'VerifyTest',
    ['pattern' => "' OR 1=1 --", 'field' => 'username'],
    'logged'
);
$after = (int) $pdo->query("SELECT COUNT(*) FROM security_events")->fetchColumn();
check('Row inserted', $after === $before['security_events'] + 1);

$row = $pdo->query("SELECT * FROM security_events ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('event_code correct',    ($row['event_code'] ?? '') === 'GUEST_SQLI_PATTERN');
check('severity = high',       ($row['severity'] ?? '') === 'high');
check('risk_score = 70',       (int)($row['risk_score'] ?? 0) === 70);
check('payload_summary set',   !empty($row['payload_summary'] ?? ''));
check('no raw password in payload', !str_contains((string)($row['payload_summary'] ?? ''), '"password"'));

// ── Test 5: admin_audit_logs ──────────────────────────────────────────────
echo "\n[5] admin_audit_logs — UPDATE\n";
SecurityLogger::logAudit(
    'barang', 'UPDATE', 'barang', '99',
    ['nama' => 'Produk Lama', 'harga' => 10000],
    ['nama' => 'Produk Baru', 'harga' => 15000],
    false, 0
);
$after = (int) $pdo->query("SELECT COUNT(*) FROM admin_audit_logs")->fetchColumn();
check('Row inserted', $after === $before['admin_audit_logs'] + 1);

$row = $pdo->query("SELECT * FROM admin_audit_logs ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('module_name = barang',  ($row['module_name'] ?? '') === 'barang');
check('action_name = UPDATE',  ($row['action_name'] ?? '') === 'UPDATE');
check('target_id = 99',        ($row['target_id'] ?? '') === '99');
check('before_snapshot set',   !empty($row['before_snapshot'] ?? ''));
check('after_snapshot set',    !empty($row['after_snapshot'] ?? ''));
check('diff_summary contains changed fields', str_contains((string)($row['diff_summary'] ?? ''), 'harga'));

// ── Summary ───────────────────────────────────────────────────────────────
echo "\n=== Result: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
