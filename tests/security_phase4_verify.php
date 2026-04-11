<?php

/**
 * Security Phase 4 — Response Verification
 * Run: php tests/security_phase4_verify.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

use App\Services\BlockerService;
use App\Services\AlertService;
use App\Services\SecurityLogger;
use App\Services\Database;

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

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['_SECURITY_REQUEST_ID'] = SecurityLogger::generateRequestId();
$_SESSION['auth'] = ['id' => 1, 'role' => 'admin'];

try {
    $pdo = Database::connection();
} catch (Throwable $e) {
    echo "[ERROR] DB: " . $e->getMessage() . "\n";
    exit(1);
}

// ── [1] security_blocks table + kolom baru ────────────────────────────────────
$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'security_blocks'");
check('security_blocks table exists', (int) $stmt->fetchColumn() === 1);

$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'security_blocks' AND column_name = 'blocked_by'");
check('security_blocks.blocked_by column exists', (int) $stmt->fetchColumn() === 1);

$stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'security_blocks' AND column_name = 'unblocked_at'");
check('security_blocks.unblocked_at column exists', (int) $stmt->fetchColumn() === 1);

// ── [2] BlockerService::blockIp ───────────────────────────────────────────────
$testIp = '10.0.0.' . rand(100, 200);
$blocked = BlockerService::blockIp($testIp, 'TEST_BLOCK', 'medium', 3600, 'test');
check('blockIp — returns true', $blocked === true);

$stmt = $pdo->prepare("SELECT id, reason_code, severity, blocked_by, is_active FROM security_blocks WHERE block_type = 'ip' AND block_value = :ip AND is_active = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute(['ip' => $testIp]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('blockIp — row inserted', is_array($row));
check('blockIp — reason_code correct', ($row['reason_code'] ?? '') === 'TEST_BLOCK');
check('blockIp — blocked_by correct', ($row['blocked_by'] ?? '') === 'test');
check('blockIp — is_active = 1', (int) ($row['is_active'] ?? 0) === 1);

// ── [3] BlockerService::isIpBlocked ──────────────────────────────────────────
check('isIpBlocked — blocked IP detected', BlockerService::isIpBlocked($testIp) === true);
check('isIpBlocked — clean IP not blocked', BlockerService::isIpBlocked('192.168.99.99') === false);

// ── [4] BlockerService::unblockIp ────────────────────────────────────────────
BlockerService::unblockIp($testIp);
check('unblockIp — IP no longer blocked', BlockerService::isIpBlocked($testIp) === false);

$stmt = $pdo->prepare("SELECT unblocked_at FROM security_blocks WHERE block_type = 'ip' AND block_value = :ip AND is_active = 0 ORDER BY id DESC LIMIT 1");
$stmt->execute(['ip' => $testIp]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('unblockIp — unblocked_at set', ($row['unblocked_at'] ?? null) !== null);

// ── [5] BlockerService::blockUser ────────────────────────────────────────────
$testUserId = 9999;
$blocked = BlockerService::blockUser($testUserId, 'TEST_USER_BLOCK', 'high', 3600, 'test');
check('blockUser — returns true', $blocked === true);
check('isUserBlocked — blocked user detected', BlockerService::isUserBlocked($testUserId) === true);
BlockerService::unblockUser($testUserId);
check('unblockUser — user no longer blocked', BlockerService::isUserBlocked($testUserId) === false);

// ── [6] BlockerService::blockSession ─────────────────────────────────────────
$testHash = hash('sha256', 'test_session_' . time());
BlockerService::blockSession($testHash, 'TEST_SESSION_BLOCK', 'high', 'test');
check('blockSession — session blocked', BlockerService::isSessionBlocked($testHash) === true);

// ── [7] Auto-block from brute force ──────────────────────────────────────────
$bruteIp = '10.1.1.' . rand(1, 99);
BlockerService::autoBlockFromBruteForce($bruteIp, 5);
check('autoBlockFromBruteForce — IP blocked', BlockerService::isIpBlocked($bruteIp) === true);

$stmt = $pdo->prepare("SELECT reason_code, blocked_by FROM security_blocks WHERE block_type = 'ip' AND block_value = :ip AND is_active = 1 ORDER BY id DESC LIMIT 1");
$stmt->execute(['ip' => $bruteIp]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('autoBlockFromBruteForce — reason_code GUEST_BRUTEFORCE_IP', ($row['reason_code'] ?? '') === 'GUEST_BRUTEFORCE_IP');
check('autoBlockFromBruteForce — blocked_by RateLimitDetector', ($row['blocked_by'] ?? '') === 'RateLimitDetector');
BlockerService::unblockIp($bruteIp);

// ── [8] SYSTEM_IP_BLOCKED event logged ───────────────────────────────────────
$stmt = $pdo->prepare("SELECT event_code, severity, action_taken FROM security_events WHERE event_code = 'SYSTEM_IP_BLOCKED' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('SYSTEM_IP_BLOCKED event — inserted', is_array($row));
check('SYSTEM_IP_BLOCKED event — action_taken blocked', ($row['action_taken'] ?? '') === 'blocked');

// ── [9] AlertService::critical ───────────────────────────────────────────────
AlertService::critical('TEST_ALERT_CRITICAL', 'Phase 4 test alert', ['test' => true]);

$logPath = __DIR__ . '/../storage/cache/security_alerts.log';
check('AlertService — log file created', is_file($logPath));

$logContent = is_file($logPath) ? (string) file_get_contents($logPath) : '';
check('AlertService — CRITICAL entry in log', str_contains($logContent, 'TEST_ALERT_CRITICAL'));
check('AlertService — level CRITICAL in log', str_contains($logContent, '[CRITICAL]'));

$stmt = $pdo->prepare("SELECT event_code, severity FROM security_events WHERE event_code = 'TEST_ALERT_CRITICAL' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
check('AlertService — event logged to security_events', is_array($row));
check('AlertService — severity critical', ($row['severity'] ?? '') === 'critical');

// ── [10] AlertService::tail ───────────────────────────────────────────────────
$tail = AlertService::tail(10);
check('AlertService::tail — returns array', is_array($tail));
check('AlertService::tail — not empty', count($tail) > 0);

// ── [11] BlockCheckerMiddleware registered ────────────────────────────────────
$appFile = file_get_contents(__DIR__ . '/../bootstrap/app.php');
check('BlockCheckerMiddleware registered in web group', str_contains((string) $appFile, 'BlockCheckerMiddleware'));

// ── [12] Expired block not active ────────────────────────────────────────────
$expiredIp = '10.2.2.' . rand(1, 99);
// Insert expired block directly
$pdo->prepare(
    "INSERT INTO security_blocks (block_type, block_value, reason_code, severity, expires_at, is_active, blocked_by, created_at)
     VALUES ('ip', :ip, 'TEST_EXPIRED', 'low', DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, 'test', NOW())"
)->execute(['ip' => $expiredIp]);
check('expired block — not detected as active', BlockerService::isIpBlocked($expiredIp) === false);

// ── Summary ───────────────────────────────────────────────────────────────────
$total = $pass + $fail;
echo "\n[{$pass}/{$total} passed]\n";
if ($fail > 0) {
    echo "[WARNING] {$fail} test(s) failed.\n";
    exit(1);
}
echo "[OK] Phase 4 Response verification complete.\n";
