<?php
// app/Views/transaksi/index.php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Transaksi'])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'transaksi'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1><?= e($title ?? 'Transaksi') ?></h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Pusat transaksi penjualan dan pembelian.</p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [
                        ['label' => 'Dashboard', 'url' => site_url('dashboard')],
                    ],
                    'current' => $title ?? 'Transaksi',
                ])) ?>
            </div>
        </div>
    </div>

    <section class="panel anim">
        <div class="panel-head">
            <span class="panel-title"><i class="bi bi-receipt-cutoff me-1"></i> Menu Transaksi</span>
            <span class="text-muted small">Penjualan / Pembelian</span>
        </div>
        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="panel fm-panel-full">
                        <div class="panel-head">
                            <span class="panel-title">Transaksi Penjualan</span>
                        </div>
                        <div class="panel-body">
                            <p class="text-muted mb-2">Alur kasir untuk proses penjualan, pembayaran, hold transaksi, dan cetak struk.</p>
                            <button type="button" class="btn-a btn-sm" disabled><i class="bi bi-cart-check"></i><span>Segera Hadir</span></button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel fm-panel-full">
                        <div class="panel-head">
                            <span class="panel-title">Transaksi Pembelian</span>
                        </div>
                        <div class="panel-body">
                            <p class="text-muted mb-2">Alur pembelian barang, validasi saldo kas, serta fallback PO saat dana tidak cukup.</p>
                            <button type="button" class="btn-g btn-sm" disabled><i class="bi bi-bag-check"></i><span>Segera Hadir</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?= raw(view('partials/shared/toast')) ?>
<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>
