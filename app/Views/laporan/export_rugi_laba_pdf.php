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
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:Arial,sans-serif;font-size:11px;line-height:1.4;padding:20mm;}
h1{font-size:18px;margin-bottom:4px;}
h2{font-size:14px;margin-bottom:8px;color:#333;}
.meta{margin-bottom:12px;color:#666;}
table{width:100%;border-collapse:collapse;margin-top:8px;}
th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;}
th{background:#f5f5f5;font-weight:600;}
.text-end{text-align:right;}
.text-center{text-align:center;}
.summary{margin-top:12px;padding:8px;background:#f9f9f9;border:1px solid #ddd;}
.summary div{display:flex;justify-content:space-between;padding:4px 0;}
.summary .grand{font-weight:700;font-size:12px;border-top:2px solid #333;padding-top:6px;margin-top:4px;}
@media print{body{padding:10mm;}@page{size:A4 portrait;margin:10mm;}}
</style>
</head>
<body>
<h1><?= $title ?? 'Laporan Rugi Laba' ?></h1>
<div class="meta">Tahun: <?= (string) $tahun ?> | Dicetak: <?= date('d/m/Y H:i') ?></div>
<table>
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
$totalPenjualan = 0;
$totalModal = 0;
$totalLabaKotor = 0;
$totalPembelian = 0;
$totalLabaBersih = 0;
foreach ($rows as $r):
    if (!is_array($r)) continue;
    $totalPenjualan += (int) ($r['total_penjualan'] ?? 0);
    $totalModal += (int) ($r['total_modal'] ?? 0);
    $totalLabaKotor += (int) ($r['laba_kotor'] ?? 0);
    $totalPembelian += (int) ($r['total_pembelian'] ?? 0);
    $totalLabaBersih += (int) ($r['laba_bersih'] ?? 0);
?>
<tr>
<td><?= (string) ($r['bulan'] ?? '-') ?></td>
<td class="text-end"><?= format_currency_id((int) ($r['total_penjualan'] ?? 0)) ?></td>
<?php if ($canViewModal): ?>
<td class="text-end"><?= format_currency_id((int) ($r['total_modal'] ?? 0)) ?></td>
<td class="text-end"><?= format_currency_id((int) ($r['laba_kotor'] ?? 0)) ?></td>
<?php endif; ?>
<td class="text-end"><?= format_currency_id((int) ($r['total_pembelian'] ?? 0)) ?></td>
<td class="text-end"><?= format_currency_id((int) ($r['laba_bersih'] ?? 0)) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr style="font-weight:700;background:#f0f0f0;">
<td>TOTAL</td>
<td class="text-end"><?= format_currency_id($totalPenjualan) ?></td>
<?php if ($canViewModal): ?>
<td class="text-end"><?= format_currency_id($totalModal) ?></td>
<td class="text-end"><?= format_currency_id($totalLabaKotor) ?></td>
<?php endif; ?>
<td class="text-end"><?= format_currency_id($totalPembelian) ?></td>
<td class="text-end"><?= format_currency_id($totalLabaBersih) ?></td>
</tr>
</tfoot>
</table>
<?php if ($autoPrint): ?>
<script>window.onload=function(){window.print();};</script>
<?php endif; ?>
</body>
</html>
