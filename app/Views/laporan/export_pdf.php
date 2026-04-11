<?php
/** @var string $title */
/** @var string $tipe */
/** @var array<int,array<string,mixed>> $rows */
/** @var array<string,int> $summary */
/** @var bool $canViewModal */
/** @var string $filterTanggalDari */
/** @var string $filterTanggalSampai */
/** @var string $filterMetode */
/** @var bool $autoPrint */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'Laporan') ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
    font-size: 13px;
    color: var(--text-primary, #1e293b);
    background: var(--bg-secondary, #f8fafc);
    line-height: 1.5;
}

.wrap { padding: 20px; }

.rpt-header {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
}
.rpt-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary, #1e293b);
    margin-bottom: 2px;
}
.rpt-meta {
    font-size: 11px;
    color: var(--text-muted, #64748b);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 8px;
    margin-bottom: 14px;
}
.s-card {
    background: var(--bg-primary, #fff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: var(--radius, 8px);
    padding: 8px 12px;
}
.s-card .s-label {
    font-size: 10px;
    color: var(--text-muted, #64748b);
    text-transform: uppercase;
    letter-spacing: .4px;
    margin-bottom: 2px;
}
.s-card .s-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary, #1e293b);
}

.dt-wrap {
    overflow-x: auto;
    scrollbar-width: none;
}
.dt-wrap::-webkit-scrollbar { display: none; }

/* dtable — sesuai template */
.dtable {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.dtable thead th {
    background: var(--bg-tertiary, #f1f5f9);
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    padding: 8px 10px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-secondary, #475569);
    text-align: left;
    white-space: nowrap;
}
.dtable tbody td {
    padding: 7px 10px;
    border-bottom: 1px solid var(--border-color, #e2e8f0);
    vertical-align: middle;
    color: var(--text-primary, #1e293b);
}
.dtable tbody tr:last-child td { border-bottom: none; }
.dtable tbody tr:nth-child(even) td { background: var(--bg-secondary, #f8fafc); }
.dtable tfoot td {
    padding: 8px 10px;
    font-weight: 700;
    background: var(--bg-tertiary, #f1f5f9);
    border-top: 2px solid var(--border-color, #e2e8f0);
    font-size: 12px;
}
.nowrap { white-space: nowrap; }
.w-100 { width: 100%; }
.text-end { text-align: right; }
.text-center { text-align: center; }

.sbadge {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 600;
    white-space: nowrap;
}
.sbadge.scc { background: #dcfce7; color: #166534; }
.sbadge.wrn { background: #fef9c3; color: #854d0e; }

.rpt-footer {
    margin-top: 12px;
    font-size: 10px;
    color: var(--text-muted, #64748b);
    text-align: right;
}

@media print {
    body { background: #fff; font-size: 10px; }
    .wrap { padding: 0; }
    .dtable thead th, .dtable tbody td, .dtable tfoot td { font-size: 9px; padding: 4px 6px; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>
</head>
<body>
<div class="wrap">

    <div class="rpt-header">
        <div class="rpt-title"><?= e($title ?? 'Laporan') ?></div>
        <div class="rpt-meta">
            Periode: <?= e($filterTanggalDari !== '' ? $filterTanggalDari : '-') ?> s/d <?= e($filterTanggalSampai !== '' ? $filterTanggalSampai : '-') ?>
            <?php if ($filterMetode !== ''): ?> &bull; Metode: <?= e($filterMetode) ?><?php endif; ?>
            &bull; Dicetak: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <div class="summary-grid">
        <div class="s-card">
            <div class="s-label">Total Transaksi</div>
            <div class="s-value"><?= e(number_format((int)($summary['total_transaksi'] ?? 0), 0, ',', '.')) ?></div>
        </div>
        <div class="s-card">
            <div class="s-label">Total Qty</div>
            <div class="s-value"><?= e(number_format((int)($summary['total_qty'] ?? 0), 0, ',', '.')) ?></div>
        </div>
        <div class="s-card">
        <div class="s-label">
            <?php
            if ($tipe === 'penjualan') {
                echo 'Total Pendapatan';
            } elseif ($tipe === 'pembelian') {
                echo 'Total Pembelian';
            } elseif ($tipe === 'po') {
                echo 'Total PO';
            } else {
                echo 'Total Hutang';
            }
            ?>
        </div>
        <div class="s-value">Rp <?= e(number_format((int)($summary['grand_total'] ?? 0), 0, ',', '.')) ?></div>
    </div>
        <?php if ($canViewModal && $tipe === 'penjualan'): ?>
        <div class="s-card">
            <div class="s-label">Total Modal</div>
            <div class="s-value">Rp <?= e(number_format((int)($summary['total_modal'] ?? 0), 0, ',', '.')) ?></div>
        </div>
        <div class="s-card">
            <div class="s-label">Laba Kotor</div>
            <div class="s-value">Rp <?= e(number_format((int)($summary['laba'] ?? 0), 0, ',', '.')) ?></div>
        </div>
        <?php elseif ($canViewModal && $tipe === 'pembelian'): ?>
        <div class="s-card">
            <div class="s-label">Total Bayar</div>
            <div class="s-value">Rp <?= e(number_format((int)($summary['total_modal'] ?? 0), 0, ',', '.')) ?></div>
        </div>
        <div class="s-card">
            <div class="s-label">Sisa Hutang</div>
            <div class="s-value">Rp <?= e(number_format(max(0, (int)($summary['grand_total'] ?? 0) - (int)($summary['total_modal'] ?? 0)), 0, ',', '.')) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="dt-wrap">
        <?php if ($tipe === 'penjualan'): ?>
        <table class="dtable w-100 nowrap">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th>No Trx</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Bayar</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <?php if ($canViewModal): ?><th class="text-end">Modal</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                <tr><td colspan="<?= $canViewModal ? 10 : 9 ?>" class="text-center" style="padding:20px;color:var(--text-muted,#64748b);font-style:italic;">Tidak ada data</td></tr>
                <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                <?php if (!is_array($row)) continue; $st = (string)($row['status_bayar'] ?? '-'); ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td><?= e((string)($row['no_trx'] ?? '-')) ?></td>
                    <td><?= e((string)($row['tanggal_input'] ?? '-')) ?></td>
                    <td><?= e((string)($row['nama_pelanggan'] ?? 'Umum')) ?></td>
                    <td class="text-end"><?= e((string)($row['total_qty'] ?? 0)) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($row['total'] ?? 0), 0, ',', '.')) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($row['bayar'] ?? 0), 0, ',', '.')) ?></td>
                    <td><?= e((string)($row['payment_method'] ?? '-')) ?></td>
                    <td><span class="sbadge <?= $st === 'Lunas' ? 'scc' : 'wrn' ?>"><?= e($st) ?></span></td>
                    <?php if ($canViewModal): ?>
                    <td class="text-end">Rp <?= e(number_format((int)($row['total_modal'] ?? 0), 0, ',', '.')) ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($rows !== []): ?>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end">Total</td>
                    <td class="text-end"><?= e(number_format((int)($summary['total_qty'] ?? 0), 0, ',', '.')) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($summary['grand_total'] ?? 0), 0, ',', '.')) ?></td>
                    <td colspan="<?= $canViewModal ? 4 : 3 ?>"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
        <?php elseif ($tipe === 'pembelian'): ?>
        <table class="dtable w-100 nowrap">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th>No Trx</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Total Beli</th>
                    <th class="text-end">Bayar</th>
                    <th class="text-end">Sisa</th>
                    <th>Metode</th>
                    <th>Status</th>
                    <?php if ($canViewModal): ?><th class="text-end">Modal</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                <tr><td colspan="<?= $canViewModal ? 11 : 10 ?>" class="text-center" style="padding:20px;color:var(--text-muted,#64748b);font-style:italic;">Tidak ada data</td></tr>
                <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                <?php if (!is_array($row)) continue; $st = (string)($row['payment_status'] ?? $row['status_bayar'] ?? '-'); ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td><?= e((string)($row['no_trx'] ?? '-')) ?></td>
                    <td><?= e((string)($row['tanggal_input'] ?? '-')) ?></td>
                    <td><?= e((string)($row['nm_supplier'] ?? '-')) ?></td>
                    <td class="text-end"><?= e((string)($row['total_qty'] ?? 0)) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($row['total'] ?? 0), 0, ',', '.')) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($row['paid_amount'] ?? 0), 0, ',', '.')) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($row['remaining_amount'] ?? 0), 0, ',', '.')) ?></td>
                    <td><?= e((string)($row['payment_method'] ?? '-')) ?></td>
                    <td><span class="sbadge <?= $st === 'paid' ? 'scc' : 'wrn' ?>"><?= e($st) ?></span></td>
                    <?php if ($canViewModal): ?>
                    <td class="text-end">Rp <?= e(number_format((int)($row['total_modal'] ?? 0), 0, ',', '.')) ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if ($rows !== []): ?>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end">Total</td>
                    <td class="text-end"><?= e(number_format((int)($summary['total_qty'] ?? 0), 0, ',', '.')) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($summary['grand_total'] ?? 0), 0, ',', '.')) ?></td>
                    <td colspan="4"></td>
                    <?php if ($canViewModal): ?><td></td><?php endif; ?>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
        <?php elseif ($tipe === 'po'): ?>
        <table class="dtable w-100 nowrap">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th>No Reg PO</th>
                    <th>No Trx</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Total PO</th>
                    <th>Status PO</th>
                    <th>Metode</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                <tr><td colspan="9" class="text-center" style="padding:20px;color:var(--text-muted,#64748b);font-style:italic;">Tidak ada data</td></tr>
                <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                <?php if (!is_array($row)) continue; $st = (string)($row['po_status'] ?? '-'); ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td><?= e((string)($row['po_no_reg'] ?? '-')) ?></td>
                    <td><?= e((string)($row['no_trx'] ?? '-')) ?></td>
                    <td><?= e((string)($row['tanggal_input'] ?? '-')) ?></td>
                    <td><?= e((string)($row['nm_supplier'] ?? '-')) ?></td>
                    <td class="text-end"><?= e((string)($row['total_qty'] ?? 0)) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($row['total'] ?? 0), 0, ',', '.')) ?></td>
                    <td><span class="sbadge <?= $st === 'diterima' ? 'scc' : ($st === 'ditolak' ? 'wrn' : 'wrn') ?>"><?= e($st) ?></span></td>
                    <td><?= e((string)($row['payment_method'] ?? '-')) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php else: ?>
        <table class="dtable w-100 nowrap">
            <thead>
                <tr>
                    <th style="width:36px">#</th>
                    <th>No Hutang</th>
                    <th>Tanggal</th>
                    <th>Supplier</th>
                    <th>No Pembelian</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Bayar</th>
                    <th class="text-end">Sisa</th>
                    <th>Jatuh Tempo</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []): ?>
                <tr><td colspan="10" class="text-center" style="padding:20px;color:var(--text-muted,#64748b);font-style:italic;">Tidak ada data</td></tr>
                <?php else: ?>
                <?php foreach ($rows as $i => $row): ?>
                <?php if (!is_array($row)) continue; $st = (string)($row['status'] ?? '-'); ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td><?= e((string)($row['debt_no'] ?? '-')) ?></td>
                    <td><?= e((string)($row['debt_date'] ?? '-')) ?></td>
                    <td><?= e((string)($row['nama_supplier'] ?? '-')) ?></td>
                    <td><?= e((string)($row['no_trx'] ?? '-')) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($row['total_amount'] ?? 0), 0, ',', '.')) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($row['paid_amount'] ?? 0), 0, ',', '.')) ?></td>
                    <td class="text-end">Rp <?= e(number_format((int)($row['remaining_amount'] ?? 0), 0, ',', '.')) ?></td>
                    <td><?= e((string)($row['due_date'] ?? '-')) ?></td>
                    <td><span class="sbadge <?= $st === 'paid' ? 'scc' : ($st === 'partial' ? 'wrn' : 'dng') ?>"><?= e($st) ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        <?php endif; ?>
    </div>

    <div class="rpt-footer">Dicetak oleh <?= e((string)($auth['name'] ?? 'sistem')) ?> &mdash; <?= e(date('d/m/Y H:i:s')) ?></div>
</div>
<?php if ($autoPrint ?? false): ?>
<script>
window.addEventListener('load', function () { window.print(); });
window.addEventListener('afterprint', function () { window.history.back(); });
</script>
<?php else: ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.createElement('button');
    btn.textContent = '\u2190 Kembali';
    btn.onclick = function () { window.history.back(); };
    btn.style.cssText = 'position:fixed;top:12px;left:12px;padding:8px 14px;background:#475569;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:12px;font-weight:600;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.15);';
    document.body.appendChild(btn);
});
</script>
<?php endif; ?>
</body>
</html>
