<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$_SESSION['auth'] = ['id' => 1, 'role' => 'admin', 'name' => 'Admin'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$ctrl = new App\Controllers\LaporanController();

foreach (['penjualan', 'pembelian', 'po', 'hutang'] as $tipe) {
    $req = System\Http\Request::create('GET', '/laporan/datatable', [
        'draw' => '1', 'start' => '0', 'length' => '25',
        'tipe' => $tipe,
        'search' => ['value' => ''],
        'order' => [['column' => '0', 'dir' => 'desc']],
    ]);
    $resp = $ctrl->datatable($req);
    $data = json_decode($resp->content(), true);
    $ok = !isset($data['error']) && isset($data['data']);
    echo sprintf("[%s] tipe=%-10s total=%-3d count=%-3d %s\n",
        $ok ? 'PASS' : 'FAIL',
        $tipe,
        $data['recordsTotal'] ?? 0,
        count($data['data'] ?? []),
        isset($data['error']) ? 'ERROR: ' . $data['error'] : ''
    );
}
