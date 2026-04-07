<?php
$canViewModal = $canViewModal ?? false;
?>
<section class="panel anim mb-3">
    <div class="panel-head">
        <span class="panel-title"><i class="bi bi-funnel me-1"></i> Filter Rugi Laba</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn-a btn-sm" id="btnExportRugiLaba"><i class="bi bi-file-pdf"></i> Export PDF Rugi Laba</button>
            <button type="button" class="btn-g btn-sm" id="btnResetRugiLaba"><i class="bi bi-arrow-counterclockwise"></i> Reset</button>
        </div>
    </div>
    <div class="panel-body">
        <div style="max-width:200px;">
            <label class="fl">Tahun</label>
            <select class="fi" id="filterTahunRugiLaba">
                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?= e((string) $y) ?>"><?= e((string) $y) ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>
</section>

<div class="d-flex gap-3 flex-wrap mb-3 anim">
    <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
        <div class="small text-muted mb-1"><i class="bi bi-cash-stack me-1"></i>Total Penjualan</div>
        <div style="font-size:22px;font-weight:700;color:var(--success);" id="cardRLTotalPenjualan">—</div>
    </div>
    <?php if ($canViewModal): ?>
        <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
            <div class="small text-muted mb-1"><i class="bi bi-bag me-1"></i>Total Modal</div>
            <div style="font-size:22px;font-weight:700;color:var(--danger);" id="cardRLTotalModal">—</div>
        </div>
        <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
            <div class="small text-muted mb-1"><i class="bi bi-graph-up me-1"></i>Laba Kotor</div>
            <div style="font-size:22px;font-weight:700;color:var(--accent);" id="cardRLLabaKotor">—</div>
        </div>
    <?php endif; ?>
    <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
        <div class="small text-muted mb-1"><i class="bi bi-cart me-1"></i>Total Pembelian</div>
        <div style="font-size:22px;font-weight:700;color:var(--danger);" id="cardRLTotalPembelian">—</div>
    </div>
    <div class="panel" style="flex:1;min-width:140px;padding:14px 18px;">
        <div class="small text-muted mb-1"><i class="bi bi-trophy me-1"></i>Laba Bersih</div>
        <div style="font-size:22px;font-weight:700;color:var(--primary);" id="cardRLLabaBersih">—</div>
    </div>
</div>

<section class="panel anim">
    <div class="panel-head">
        <span class="panel-title"><i class="bi bi-table me-1"></i> Laporan Rugi Laba Per Bulan</span>
    </div>
    <div class="panel-body">
        <div class="dt-wrap">
            <table class="dtable w-100 nowrap" id="rugiLabaTable">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th class="text-end">Penjualan</th>
                        <?php if ($canViewModal): ?>
                            <th class="text-end">Modal</th>
                            <th class="text-end">Laba Kotor</th>
                        <?php endif; ?>
                        <th class="text-end">Pembelian</th>
                        <th class="text-end">Laba Bersih</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>
