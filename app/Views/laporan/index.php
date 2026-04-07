<?php
/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var array<int,array<string,mixed>> $pelangganOptions */
/** @var array<int,array<string,mixed>> $supplierOptions */
/** @var array<int,array<string,mixed>> $kategoriOptions */
/** @var array<int,array<string,mixed>> $akunOptions */
/** @var array<int,string> $paymentMethods */
/** @var bool $canViewModal */

$extraHead = raw('<link href="' . e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.css')) . '" rel="stylesheet">');
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? 'Laporan', 'extraHead' => $extraHead])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'laporan'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1><?= e($title ?? 'Laporan') ?></h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div><p class="small">Laporan Penjualan, Pembelian, Rugi Laba, dan Keuangan</p></div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [['label' => 'Dashboard', 'url' => site_url('dashboard')]],
                    'current' => 'Laporan',
                ])) ?>
            </div>
        </div>
    </div>

    <div class="keu-tab-wrap mb-3 anim">
        <a class="keu-tab-link is-active" href="#" data-lap-tab="transaksi"><i class="bi bi-receipt"></i><span>Transaksi</span></a>
        <a class="keu-tab-link" href="#" data-lap-tab="rugi-laba"><i class="bi bi-graph-up-arrow"></i><span>Rugi Laba</span></a>
        <a class="keu-tab-link" href="#" data-lap-tab="keuangan"><i class="bi bi-cash-coin"></i><span>Keuangan</span></a>
    </div>

    <!-- Tab Content: Transaksi -->
    <div class="lap-content active" data-lap-content="transaksi">
        <?php include __DIR__ . '/tabs/transaksi.php'; ?>
    </div>

    <!-- Tab Content: Rugi Laba -->
    <div class="lap-content" data-lap-content="rugi-laba" style="display:none;">
        <?php include __DIR__ . '/tabs/rugi_laba.php'; ?>
    </div>

    <!-- Tab Content: Keuangan -->
    <div class="lap-content" data-lap-content="keuangan" style="display:none;">
        <?php include __DIR__ . '/tabs/keuangan.php'; ?>
    </div>
</main>

<!-- Modal Export Keuangan -->
<div class="cm-bg" id="cmExportKeuangan" data-cm-bg>
    <div class="panel cm-box cm-box-lg" role="dialog" aria-modal="true" aria-labelledby="cmExportKeuanganTitle" style="max-height: 90vh; display: flex; flex-direction: column;">
        <div class="panel-head">
            <span class="panel-title" id="cmExportKeuanganTitle"><i class="bi bi-file-pdf me-1"></i> Preview Laporan Keuangan</span>
            <button type="button" class="cm-x" data-cm-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="panel-body" style="flex: 1; overflow: auto; padding: 0;">
            <iframe id="pdfPreviewKeuangan" style="width: 100%; height: 100%; border: none;" src=""></iframe>
        </div>
        <div class="cm-foot">
            <button type="button" class="btn-g" data-cm-close>Batal</button>
            <button type="button" class="btn-a" id="btnCetakKeuangan"><i class="bi bi-printer me-1"></i> Cetak</button>
            <button type="button" class="btn-a" id="btnUnduhKeuangan"><i class="bi bi-download me-1"></i> Unduh PDF</button>
        </div>
    </div>
</div>

<?= raw(view('partials/shared/toast')) ?>
<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<script src="<?= e(base_url('assets/vendor/jquery/jquery-3.7.1.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/jquery.dataTables.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/vendor/datatables/dataTables.bootstrap5.min.js')) ?>"></script>
<script>window.laporanCanViewModal = <?= json_encode($canViewModal) ?>;</script>
<script src="<?= e(base_url('assets/js/laporan.js')) ?>"></script>
<?= raw(view('partials/dashboard/shell_close')) ?>
