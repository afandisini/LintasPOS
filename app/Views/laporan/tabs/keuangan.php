<?php
$akunOptions = $akunOptions ?? [];
?>
<section class="panel anim mb-3">
    <div class="panel-head">
        <span class="panel-title"><i class="bi bi-funnel me-1"></i> Filter Keuangan</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn-a btn-sm" id="btnExportKeuangan" data-cm-open="cmExportKeuangan"><i class="bi bi-file-pdf"></i> Export PDF Keuangan</button>
            <button type="button" class="btn-g btn-sm" id="btnResetKeuangan"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
        </div>
    </div>
    <div class="panel-body">
        <div class="u-form-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px 14px;">
            <div>
                <label class="fl">Tanggal Dari</label>
                <input class="fi" type="date" id="filterKeuTanggalDari">
            </div>
            <div>
                <label class="fl">Tanggal Sampai</label>
                <input class="fi" type="date" id="filterKeuTanggalSampai">
            </div>
            <div>
                <label class="fl">Tipe Arus</label>
                <select class="fi" id="filterKeuTipeArus">
                    <option value="">— Semua —</option>
                    <option value="pemasukan">Pemasukan</option>
                    <option value="pengeluaran">Pengeluaran</option>
                    <option value="netral">Netral</option>
                </select>
            </div>
            <div>
                <label class="fl">Akun</label>
                <select class="fi" id="filterKeuAkun">
                    <option value="">— Semua —</option>
                    <?php foreach ($akunOptions as $opt): ?>
                        <?php if (!is_array($opt)) continue; ?>
                        <option value="<?= e((string) ($opt['id'] ?? '0')) ?>"><?= e((string) ($opt['kode_akun'] ?? '-')) ?> - <?= e((string) ($opt['nama_akun'] ?? '-')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</section>

<section class="panel anim">
    <div class="panel-head">
        <span class="panel-title"><i class="bi bi-table me-1"></i> Data Keuangan</span>
    </div>
    <div class="panel-body">
        <div class="dt-wrap">
            <table class="dtable w-100 nowrap" id="keuanganTable">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>No Ref</th>
                        <th>Akun</th>
                        <th>Tipe Arus</th>
                        <th class="text-end">Nominal</th>
                        <th>Metode</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>
