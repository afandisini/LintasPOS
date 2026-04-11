<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$_SESSION['auth'] = ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'Debug/1.0';

$ctrl = new App\Controllers\TransaksiController();

// Test purchaseDebtDatatable
$req = System\Http\Request::create('GET', '/transaksi/pembelian/hutang/datatable?draw=1&start=0&length=10', [
    'draw' => '1', 'start' => '0', 'length' => '10',
    'search' => ['value' => ''],
    'order' => [['column' => '0', 'dir' => 'desc']],
]);
$resp = $ctrl->purchaseDebtDatatable($req);
$body = $resp->content();
$data = json_decode($body, true);
echo '=== purchaseDebtDatatable ===' . PHP_EOL;
echo 'draw=' . ($data['draw'] ?? 'N/A') . PHP_EOL;
echo 'recordsTotal=' . ($data['recordsTotal'] ?? 'N/A') . PHP_EOL;
echo 'data_count=' . count($data['data'] ?? []) . PHP_EOL;
if (!empty($data['error'])) echo 'ERROR: ' . $data['error'] . PHP_EOL;
if (!empty($data['data'])) echo 'sample=' . json_encode($data['data'][0], JSON_UNESCAPED_UNICODE) . PHP_EOL;

echo PHP_EOL;

// Test purchaseDebtDetail
$req2 = System\Http\Request::create('GET', '/transaksi/pembelian/hutang/detail?debt_id=1', ['debt_id' => '1']);
$resp2 = $ctrl->purchaseDebtDetail($req2);
$body2 = $resp2->content();
$data2 = json_decode($body2, true);
echo '=== purchaseDebtDetail ===' . PHP_EOL;
echo 'success=' . ($data2['success'] ?? 'N/A') . PHP_EOL;
if (!empty($data2['error'])) echo 'ERROR: ' . $data2['error'] . PHP_EOL;
if (!empty($data2['data'])) echo 'debt_no=' . ($data2['data']['debt_no'] ?? 'N/A') . PHP_EOL;
echo 'payments_count=' . count($data2['payments'] ?? []) . PHP_EOL;
