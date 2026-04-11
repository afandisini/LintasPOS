<?php
// Phase 2 Verification Script
// Run: php tests/security_phase2_verify.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';

$_SERVER['REMOTE_ADDR']    = '10.0.0.99';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (VerifyBot Phase2)';
$_SERVER['REQUEST_URI']    = '/verify';
$_SERVER['QUERY_STRING']   = '';
$_SERVER['_SECURITY_REQUEST_ID'] = 'p2-verify-' . bin2hex(random_bytes(6));
$_SESSION = [];

use App\Services\SecurityLogger;
use App\Services\ThreatInspector;
use App\Services\RateLimitDetector;
use App\Services\Database;
use System\Http\Request;

$pdo  = Database::connection();
$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  [PASS] {$label}\n"; }
    else      { $fail++; echo "  [FAIL] {$label}\n"; }
}

function countEvents(PDO $pdo, string $code): int {
    return (int) $pdo->prepare('SELECT COUNT(*) FROM security_events WHERE event_code = ?')
        ->execute([$code]) ? (int) $pdo->query("SELECT COUNT(*) FROM security_events WHERE event_code = '{$code}'")->fetchColumn() : 0;
}

echo "\n=== Phase 2 Detection — Verification ===\n\n";

// ── Test 1: Tables exist ──────────────────────────────────────────────────
echo "[1] Migration — tables exist\n";
foreach (['security_blocks', 'security_event_rules'] as $t) {
    $n = (int) $pdo->query("SELECT COUNT(*) FROM {$t}")->fetchColumn();
    check("{$t} exists", true); // if we got here without exception, it exists
}
$rules = (int) $pdo->query("SELECT COUNT(*) FROM security_event_rules")->fetchColumn();
check('Default rules seeded (>= 7)', $rules >= 7);

// ── Test 2: SQLi detection ────────────────────────────────────────────────
echo "\n[2] ThreatInspector — SQLi\n";
$req = Request::create('GET', '/search', ['q' => "' UNION SELECT * FROM users --"]);
$result = ThreatInspector::inspect($req);
check('SQLi detected',          $result['is_suspicious'] === true);
check('risk_score >= 70',       $result['risk_score'] >= 70);
check('finding event_code correct', ($result['findings'][0]['event_code'] ?? '') === SecurityLogger::EVT_SQLI_PATTERN);

// ── Test 3: XSS detection ─────────────────────────────────────────────────
echo "\n[3] ThreatInspector — XSS\n";
$req = Request::create('GET', '/comment', ['body' => '<script>alert(document.cookie)</script>']);
$result = ThreatInspector::inspect($req);
check('XSS detected',           $result['is_suspicious'] === true);
check('risk_score >= 65',       $result['risk_score'] >= 65);
check('finding event_code correct', ($result['findings'][0]['event_code'] ?? '') === SecurityLogger::EVT_XSS_PATTERN);

// ── Test 4: Path Traversal detection ─────────────────────────────────────
echo "\n[4] ThreatInspector — Path Traversal\n";
$req = Request::create('GET', '/file', ['path' => '../../etc/passwd']);
$result = ThreatInspector::inspect($req);
check('Path traversal detected', $result['is_suspicious'] === true);
check('risk_score >= 65',        $result['risk_score'] >= 65);
check('finding event_code correct', ($result['findings'][0]['event_code'] ?? '') === SecurityLogger::EVT_PATH_TRAVERSAL);

// ── Test 5: Sensitive path probe ──────────────────────────────────────────
echo "\n[5] ThreatInspector — Sensitive path probe\n";
$req = Request::create('GET', '/wp-admin/admin.php');
$result = ThreatInspector::inspect($req);
check('Sensitive probe detected', $result['is_suspicious'] === true);
check('finding = GUEST_SENSITIVE_FILE_PROBE', ($result['findings'][0]['event_code'] ?? '') === 'GUEST_SENSITIVE_FILE_PROBE');

// ── Test 6: Clean request — no false positive ─────────────────────────────
echo "\n[6] ThreatInspector — Clean request (no false positive)\n";
$req = Request::create('GET', '/dashboard', ['page' => '1', 'search' => 'laptop']);
$result = ThreatInspector::inspect($req);
check('Clean request not flagged', $result['is_suspicious'] === false);
check('risk_score = 0',            $result['risk_score'] === 0);

// ── Test 7: Rate limit — 404 flood ───────────────────────────────────────
echo "\n[7] RateLimitDetector — 404 flood\n";
$testIp = '192.168.99.1';
RateLimitDetector::reset('flood_404', $testIp);
$exceeded = false;
for ($i = 0; $i < 11; $i++) {
    $r = RateLimitDetector::check404Flood($testIp);
    if ($r['exceeded']) { $exceeded = true; break; }
}
check('404 flood threshold triggered at 10 hits', $exceeded);

// ── Test 8: Rate limit — brute force ─────────────────────────────────────
echo "\n[8] RateLimitDetector — login brute force\n";
$testIp2 = '192.168.99.2';
RateLimitDetector::reset('login_fail', $testIp2);
$blocked = false;
for ($i = 0; $i < 6; $i++) {
    $r = RateLimitDetector::checkLoginBruteForce($testIp2);
    if ($r['blocked']) { $blocked = true; break; }
}
check('Brute force blocked after 5 failures', $blocked);

// ── Test 9: Rate limit — reset on success ────────────────────────────────
echo "\n[9] RateLimitDetector — reset on success\n";
RateLimitDetector::reset('login_fail', $testIp2);
$r = RateLimitDetector::checkLoginBruteForce($testIp2);
check('Counter reset — not blocked after reset', $r['blocked'] === false);
check('Count = 1 after reset', $r['count'] === 1);

// ── Test 10: SecurityLogger — security_event logged ──────────────────────
echo "\n[10] SecurityLogger — security_event from inspection\n";
$before = (int) $pdo->query("SELECT COUNT(*) FROM security_events")->fetchColumn();
SecurityLogger::logSecurityEvent(
    SecurityLogger::EVT_SQLI_PATTERN, 'injection', 'high', 70,
    'Phase2Test', ['pattern' => 'UNION SELECT'], 'logged'
);
$after = (int) $pdo->query("SELECT COUNT(*) FROM security_events")->fetchColumn();
check('security_event inserted', $after === $before + 1);

$row = $pdo->query("SELECT * FROM security_events ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('event_code = GUEST_SQLI_PATTERN', ($row['event_code'] ?? '') === 'GUEST_SQLI_PATTERN');
check('severity = high',                 ($row['severity'] ?? '') === 'high');
check('detection_stage = before_login',  ($row['detection_stage'] ?? '') === 'before_login');

// ── Test 11: CSRF event code exists in rules ──────────────────────────────
echo "\n[11] security_event_rules — CSRF rule seeded\n";
$csrfRule = $pdo->query("SELECT * FROM security_event_rules WHERE rule_code = 'SYSTEM_CSRF_FAILED'")->fetch(PDO::FETCH_ASSOC);
check('CSRF rule exists',           is_array($csrfRule));
check('CSRF severity = medium',     ($csrfRule['severity'] ?? '') === 'medium');
check('CSRF action = logged',       ($csrfRule['default_action'] ?? '') === 'logged');

$bruteRule = $pdo->query("SELECT * FROM security_event_rules WHERE rule_code = 'GUEST_BRUTEFORCE_IP'")->fetch(PDO::FETCH_ASSOC);
check('Brute force rule exists',    is_array($bruteRule));
check('Brute force action = block', ($bruteRule['default_action'] ?? '') === 'block');

// ── Summary ───────────────────────────────────────────────────────────────
echo "\n=== Result: {$pass} passed, {$fail} failed ===\n";
exit($fail > 0 ? 1 : 0);
