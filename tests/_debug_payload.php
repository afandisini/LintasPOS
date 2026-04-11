<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$_SESSION['auth'] = ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

try {
    $pdo  = App\Services\Database::connection();
    $ctrl = new App\Controllers\SecurityController();
    $ref  = new ReflectionClass($ctrl);
    $m    = $ref->getMethod('overviewPayload');
    $m->setAccessible(true);
    $result = $m->invoke($ctrl, $pdo);
    echo 'PAYLOAD_KEYS=' . implode(',', array_keys($result)) . PHP_EOL;
    echo 'HAS_TAB=' . (array_key_exists('tab', $result) ? 'YES' : 'NO') . PHP_EOL;
    echo 'STATS_TYPE=' . gettype($result['stats'] ?? null) . PHP_EOL;
    echo 'STATS_COUNT=' . count($result['stats'] ?? []) . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
