<?php
$canViewModal = $canViewModal ?? false;
$pelangganOptions = $pelangganOptions ?? [];
$supplierOptions = $supplierOptions ?? [];
$kategoriOptions = $kategoriOptions ?? [];
$paymentMethods = $paymentMethods ?? [];
?>
<section class="panel anim mb-3">
    <div class="panel-head">
        <span class="panel-title"><i class="bi bi-funnel me-1"></i> Filter Laporan</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn-g btn-sm" id="btnResetFilter"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
            <button type="button" class="btn-a btn-sm" id="btnExportPdf"><i class="bi bi-file-earmark-pdf"></i> Export PDF</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="u-form-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px 14px;">
            <div>
                <label class="fl">Tipe Laporan</label>
                <select class="fi" id="filterTipe">
                    <option value="penjualan">Penjualan</option>
                    <option value="pembelian">Pembelian</option>
                    <option value="po">PO</option>
                    <option value="hutang">Hutang Supplier</option>
                </select>
            </div>
            <div>
                <label class="fl">Periode</label>
                <select class="fi" id="filterPeriode">
                    <option value="">— Semua —</option>
                    <option value="today">Hari Ini</option>
                    <option value="week">Minggu Ini</option>
                    <option value="month">Bulan Ini</option>
                    <option value="year">Tahun Ini</option>
                    <option value="custom">Custom</option>
                </select>
            </div>
            <div id="wrapTanggalDari">
                <label class="fl">Tanggal Dari</label>
                <input class="fi" type="date" id="filterTanggalDari">
            </div>
            <div id="wrapTanggalSampai">
                <label class="fl">Tanggal Sampai</label>
                <input class="fi" type="date" id="filterTanggalSampai">
            </div>
            <div id="wrapPelanggan">
                <label class="fl">Pelanggan</label>
                <select class="fi" id="filterPelanggan">
                    <option value="">— Semua —</option>
                    <?php foreach ($pelangganOptions as $p): ?>
                        <?php if (!is_array($p)) continue; ?>
                        <option value="<?= e((string) ($p['id'] ?? '')) ?>">
                            <?= e((string) ($p['nama_pelanggan'] ?? '-')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="wrapSupplier">
                <label class="fl">Supplier</label>
                <select class="fi" id="filterSupplier">
                    <option value="">— Semua —</option>
                    <?php foreach ($supplierOptions as $s): ?>
                        <?php if (!is_array($s)) continue; ?>
                        <option value="<?= e((string) ($s['nama_supplier'] ?? '')) ?>"><?= e((string) ($s['nama_supplier'] ?? '-')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="wrapKategori">
                <label class="fl">Kategori Produk</label>
                <select class="fi" id="filterKategori">
                    <option value="">— Semua —</option>
                    <?php foreach ($kategoriOptions as $k): ?>
                        <?php if (!is_array($k)) continue; ?>
                        <option value="<?= e((string) ($k['id'] ?? '')) ?>"><?= e((string) ($k['nama_kategori'] ?? '-')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="fl">Nama Produk</label>
                <input class="fi" type="text" id="filterProduk" placeholder="Cari nama produk...">
            </div>
            <div>
                <label class="fl">Metode Pembayaran</label>
                <select class="fi" id="filterMetode">
                    <option value="">— Semua —</option>
                    <?php foreach ($paymentMethods as $m): ?>
                        <option value="<?= e((string) $m) ?>"><?= e((string) $m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</section>

<div class="d-flex gap-3 flex-wrap mb-3 anim" id="summaryCards">
    <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
        <div class="small text-muted mb-1" id="labelTotalTrx"><i class="bi bi-receipt me-1"></i>Total Transaksi</div>
        <div style="font-size:22px;font-weight:700;" id="cardTotalTrx">—</div>
    </div>
    <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
        <div class="small text-muted mb-1" id="labelTotalQty"><i class="bi bi-boxes me-1"></i>Total Qty</div>
        <div style="font-size:22px;font-weight:700;" id="cardTotalQty">—</div>
    </div>
    <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
        <div class="small text-muted mb-1" id="labelGrandTotal"><i class="bi bi-cash-stack me-1"></i>Total Pendapatan</div>
        <div style="font-size:22px;font-weight:700;color:var(--success);" id="cardGrandTotal">—</div>
    </div>
    <?php if ($canViewModal): ?>
        <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
            <div class="small text-muted mb-1" id="labelTotalModal"><i class="bi bi-bag me-1"></i>Total Modal</div>
            <div style="font-size:22px;font-weight:700;color:var(--danger);" id="cardTotalModal">—</div>
        </div>
        <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
            <div class="small text-muted mb-1" id="labelLaba"><i class="bi bi-graph-up-arrow me-1"></i>Laba Kotor</div>
            <div style="font-size:22px;font-weight:700;color:var(--accent);" id="cardLaba">—</div>
        </div>
    <?php endif; ?>
</div>

<section class="panel anim">
    <div class="panel-head">
        <span class="panel-title" id="tableTitle"><i class="bi bi-table me-1"></i> Data Penjualan</span>
    </div>
    <div class="panel-body">
        <div class="dt-wrap">
            <table class="dtable w-100 nowrap" id="laporanTable">
                <thead>
                    <tr id="laporanTableHead"></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>
