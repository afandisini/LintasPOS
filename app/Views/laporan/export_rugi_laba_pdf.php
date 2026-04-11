<?php
/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var int $tahun */
/** @var array<int,array<string,mixed>> $rows */
/** @var bool $canViewModal */
/** @var bool $autoPrint */
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?? 'Laporan Rugi Laba' ?></title>
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
.rpt-title { font-size: 16px; font-weight: 700; color: var(--text-primary, #1e293b); margin-bottom: 2px; }
.rpt-meta { font-size: 11px; color: var(--text-muted, #64748b); }

.dt-wrap {
    overflow-x: auto;
    scrollbar-width: none;
}
.dt-wrap::-webkit-scrollbar { display: none; }

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

.laba-pos { color: #166534; font-weight: 600; }
.laba-neg { color: #991b1b; font-weight: 600; }

.rpt-footer { margin-top: 12px; font-size: 10px; color: var(--text-muted, #64748b); text-align: right; }

@media print {
    body { background: #fff; font-size: 10px; }
    .wrap { padding: 0; }
    .dtable thead th, .dtable tbody td, .dtable tfoot td { font-size: 9px; padding: 4px 6px; }
    @page { size: A4 portrait; margin: 10mm; }
}
</style>
</head>
<body>
<div class="wrap">

    <div class="rpt-header">
        <div class="rpt-title"><?= e($title ?? 'Laporan Rugi Laba') ?></div>
        <div class="rpt-meta">Tahun: <?= (string)$tahun ?> &bull; Dicetak: <?= date('d/m/Y H:i') ?></div>
    </div>

    <div class="dt-wrap">
        <table class="dtable w-100 nowrap">
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
            <tbody>
            <?php
            $totPenjualan = 0; $totModal = 0; $totLabaKotor = 0; $totPembelian = 0; $totLabaBersih = 0;
            foreach ($rows as $r):
                if (!is_array($r)) continue;
                $totPenjualan  += (int)($r['total_penjualan'] ?? 0);
                $totModal      += (int)($r['total_modal'] ?? 0);
                $totLabaKotor  += (int)($r['laba_kotor'] ?? 0);
                $totPembelian  += (int)($r['total_pembelian'] ?? 0);
                $totLabaBersih += (int)($r['laba_bersih'] ?? 0);
                $lb = (int)($r['laba_bersih'] ?? 0);
            ?>
                <tr>
                    <td><?= e((string)($r['bulan'] ?? '-')) ?></td>
                    <td class="text-end"><?= format_currency_id((int)($r['total_penjualan'] ?? 0)) ?></td>
                    <?php if ($canViewModal): ?>
                    <td class="text-end"><?= format_currency_id((int)($r['total_modal'] ?? 0)) ?></td>
                    <td class="text-end"><?= format_currency_id((int)($r['laba_kotor'] ?? 0)) ?></td>
                    <?php endif; ?>
                    <td class="text-end"><?= format_currency_id((int)($r['total_pembelian'] ?? 0)) ?></td>
                    <td class="text-end <?= $lb >= 0 ? 'laba-pos' : 'laba-neg' ?>"><?= format_currency_id($lb) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>TOTAL</td>
                    <td class="text-end"><?= format_currency_id($totPenjualan) ?></td>
                    <?php if ($canViewModal): ?>
                    <td class="text-end"><?= format_currency_id($totModal) ?></td>
                    <td class="text-end"><?= format_currency_id($totLabaKotor) ?></td>
                    <?php endif; ?>
                    <td class="text-end"><?= format_currency_id($totPembelian) ?></td>
                    <td class="text-end <?= $totLabaBersih >= 0 ? 'laba-pos' : 'laba-neg' ?>"><?= format_currency_id($totLabaBersih) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="rpt-footer">Dicetak oleh <?= e((string)($auth['name'] ?? 'sistem')) ?> &mdash; <?= date('d/m/Y H:i:s') ?></div>
</div>
<?php if ($autoPrint): ?>
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
