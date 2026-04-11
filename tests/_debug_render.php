<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$_SESSION['auth'] = ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Debug/1.0';

try {
    $pdo  = App\Services\Database::connection();
    $ctrl = new App\Controllers\SecurityController();
    $ref  = new ReflectionClass($ctrl);

    $m = $ref->getMethod('overviewPayload');
    $m->setAccessible(true);
    $payload = $m->invoke($ctrl, $pdo);

    $data = [
        'title'      => 'Security Monitor',
        'auth'       => ['id' => 1, 'role' => 'admin', 'name' => 'Admin'],
        'activeMenu' => 'security',
        'tab'        => 'overview',
        ...$payload,
    ];

    echo 'DATA_KEYS=' . implode(',', array_keys($data)) . PHP_EOL;
    echo 'TAB_VALUE=' . var_export($data['tab'], true) . PHP_EOL;
    echo 'STATS_KEYS=' . implode(',', array_keys($data['stats'])) . PHP_EOL;

    // Test render
    $html = app()->view()->render('security/index', $data);
    preg_match('/var tab\s*=\s*([^;]+);/', $html, $m2);
    echo 'TAB_IN_JS=' . ($m2[1] ?? 'not_found') . PHP_EOL;
    echo 'HAS_STAT_CARD=' . (str_contains($html, 'stat-card') ? 'YES' : 'NO') . PHP_EOL;
    echo 'HAS_EVENTS_HARI_INI=' . (str_contains($html, 'Events Hari Ini') ? 'YES' : 'NO') . PHP_EOL;

} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
