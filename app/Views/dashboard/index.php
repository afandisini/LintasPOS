<?php
// app/Views/dashboard/index.php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var string $activeMenu */
/** @var array<string,int> $stats */
/** @var string $chart_labels_json */
/** @var string $chart_values_json */
/** @var array<int,array<string,mixed>> $recent_sales */
/** @var array<int,array<string,mixed>> $low_stocks */
?>
<?= raw(view('partials/dashboard/head', ['title' => $title ?? (brand_name() . ' Dashboard')])) ?>
<?= raw(view('partials/dashboard/shell_open', ['auth' => $auth, 'activeMenu' => $activeMenu ?? 'dashboard'])) ?>

<main class="main" id="mainContent">
    <div class="pg-header mb-3 anim">
        <h1>Dashboard</h1>
        <div class="d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <p class="small">Selamat datang kembali, <?= e((string) ($auth['name'] ?? 'User')) ?>. Berikut ringkasan bisnis hari ini.</p>
            </div>
            <div>
                <?= raw(view('partials/dashboard/breadcrumb', [
                    'items' => [],
                    'current' => 'Dashboard',
                ])) ?>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3 anim">
            <div class="stat-card">
                <div class="st-glow" style="background:var(--accent)"></div>
                <div class="st-icon" style="background:var(--accent-light);color:var(--accent)"><i class="bi bi-wallet2"></i></div>
                <div class="st-val">Rp <?= e(number_format((int) ($stats['month_revenue'] ?? 0), 0, ',', '.')) ?></div>
                <div class="st-label">Pendapatan Bulan Ini</div>
                <div class="st-change up"><i class="bi bi-arrow-up-short me-1"></i>Live</div>
            </div>
        </div>
        <div class="col-6 col-lg-3 anim">
            <div class="stat-card">
                <div class="st-glow" style="background:var(--info)"></div>
                <div class="st-icon" style="background:var(--info-light);color:var(--info)"><i class="bi bi-bag-check-fill"></i></div>
                <div class="st-val"><?= e(number_format((int) ($stats['month_transactions'] ?? 0), 0, ',', '.')) ?></div>
                <div class="st-label">Transaksi Bulanan</div>
                <div class="st-change up"><i class="bi bi-arrow-up-short me-1"></i>Live</div>
            </div>
        </div>
        <div class="col-6 col-lg-3 anim">
            <div class="stat-card">
                <div class="st-glow" style="background:var(--success)"></div>
                <div class="st-icon" style="background:var(--success-light);color:var(--success)"><i class="bi bi-people-fill"></i></div>
                <div class="st-val"><?= e(number_format((int) ($stats['customers'] ?? 0), 0, ',', '.')) ?></div>
                <div class="st-label">Pelanggan</div>
                <div class="st-change up"><i class="bi bi-arrow-up-short me-1"></i>Aktif</div>
            </div>
        </div>
        <div class="col-6 col-lg-3 anim">
            <div class="stat-card">
                <div class="st-glow" style="background:var(--danger)"></div>
                <div class="st-icon" style="background:var(--danger-light);color:var(--danger)"><i class="bi bi-person-badge-fill"></i></div>
                <div class="st-val"><?= e(number_format((int) ($stats['users'] ?? 0), 0, ',', '.')) ?></div>
                <div class="st-label">Users</div>
                <div class="st-change down"><i class="bi bi-shield-check me-1"></i>Secure</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-8 anim">
            <div class="panel h-100 mb-3">
                <div class="panel-head">
                    <span class="panel-title">Pendapatan Bulanan</span>
                    <button class="panel-link" type="button">6 Periode Terakhir</button>
                </div>
                <div class="panel-body">
                    <div class="chart-box"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 anim" id="stocks">
            <div class="panel h-100">
                <div class="panel-head"><span class="panel-title">Stok Rendah</span></div>
                <div class="panel-body" style="max-height:290px;overflow-y:auto">
                    <?php if ($low_stocks === []): ?>
                        <div class="text-muted small">Tidak ada stok kritis.</div>
                    <?php else: ?>
                        <?php foreach ($low_stocks as $item): ?>
                            <div class="act-item">
                                <div class="act-dot" style="background:var(--danger)"></div>
                                <div class="act-text">
                                    <p><strong><?= e((string) ($item['nama_barang'] ?? '-')) ?></strong> tersisa <strong><?= e((string) ($item['stok'] ?? '0')) ?></strong> unit</p>
                                    <span class="atime"><?= e((string) ($item['id_barang'] ?? '-')) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4 anim">
            <div class="panel mb-3">
                <div class="panel-head"><span class="panel-title">Akses Cepat</span></div>
                <div class="panel-body">
                    <div class="qa-grid">
                        <a class="qa-btn link-underline link-underline-opacity-0" href="<?= e(site_url('transaksi/penjualan')) ?>"><i class="bi bi-bag"></i><span>Penjualan</span></a>
                        <a class="qa-btn link-underline link-underline-opacity-0" href="<?= e(site_url('transaksi/pembelian')) ?>"><i class="bi bi-cart-plus"></i><span>Pembelian</span></a>
                        <a class="qa-btn link-underline link-underline-opacity-0" href="<?= e(site_url('barang')) ?>"><i class="bi bi-box-seam"></i><span>Barang</span></a>
                        <a class="qa-btn link-underline link-underline-opacity-0" href="<?= e(site_url('jasa')) ?>"><i class="bi bi-wrench"></i><span>Jasa</span></a>
                    </div>
                </div>
            </div>
            <div class="anim" id="users-table">
                <div class="panel h-100">
                    <div class="panel-head">
                        <span class="panel-title">Ringkasan Users</span>
                        <span class="text-muted" style="font-size:12px"><i class="bi bi-person-badge"></i> <?= e((string) ($auth['role'] ?? '-')) ?></span>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <div class="mini-item small"><span><i class="bi bi-people"></i></span><strong><?= e(number_format((int) ($stats['users'] ?? 0))) ?></strong></div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="mini-item small"><span><i class="bi bi-person"></i></span><strong><?= e(number_format((int) ($stats['customers'] ?? 0))) ?></strong></div>
                            </div>
                            <div class="col-md-12 mb-2">
                                <div class="mini-item small"><span><i class="bi bi-box-seam"></i></span><strong><?= e(number_format((int) ($stats['products'] ?? 0))) ?></strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8 anim">
            <div class="panel h-100" id="recent-sales">
                <div class="panel-head">
                    <span class="panel-title">Transaksi</span>
                    <div class="panel-link"><i class="bi bi-clock"></i> 8 Transaksi Terbaru</div>
                </div>
                <div class="panel-body" style="max-height:290px;overflow-y:auto">
                    <?php if ($recent_sales === []): ?>
                        <div class="text-muted small">Belum ada data transaksi.</div>
                    <?php else: ?>
                        <?php foreach ($recent_sales as $row): ?>
                            <?php $metode = trim((string) ($row['payment_method'] ?? '')) ?: '-'; ?>
                            <div class="act-item">
                                <div class="act-dot" style="background:var(--accent)"></div>
                                <div class="act-text">
                                    <p><strong><?= e((string) ($row['no_trx'] ?? '-')) ?></strong> — <?= e((string) ($row['pelanggan'] ?? '-')) ?> <span class="sbadge inf small"><span class="sd"></span><?= e($metode) ?></span></p>
                                    <span class="atime"><?= e((string) ($row['tanggal_input'] ?? '-')) ?> · Rp <?= e(number_format((int) ($row['total'] ?? 0), 0, ',', '.')) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?= raw(view('partials/shared/toast')) ?>

<script src="<?= e(base_url('assets/vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script>
    window.dashboardChartLabels = <?= raw((string) ($chart_labels_json ?? '[]')) ?>;
    window.dashboardChartValues = <?= raw((string) ($chart_values_json ?? '[]')) ?>;
</script>
<?= raw(helper_toast_script()) ?>
<?= raw(module_script('Dashboard/js/dashboard.js')) ?>
<?= raw(view('partials/dashboard/shell_close')) ?>