<?php
/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var array<int,array<string,mixed>> $rows */
/** @var string $filterTanggalDari */
/** @var string $filterTanggalSampai */
/** @var bool $autoPrint */

if (!function_exists('to_numeric_value')) {
    function to_numeric_value(mixed $value): float
    {
        if (is_int($value) || is_float($value)) return (float) $value;
        if (is_object($value)) {
            if (method_exists($value, '__toString')) $value = (string) $value;
            else return 0.0;
        }
        if (is_string($value)) {
            $clean = trim(html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8'));
            if ($clean === '') {
                return 0.0;
            }

            $clean = preg_replace('/[^0-9,\.\-]/', '', $clean) ?? '';
            if ($clean === '' || $clean === '-') {
                return 0.0;
            }

            if (preg_match('/^-?\d{1,3}(?:\.\d{3})+(?:,\d+)?$/', $clean)) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } elseif (preg_match('/^-?\d{1,3}(?:,\d{3})+(?:\.\d+)?$/', $clean)) {
                $clean = str_replace(',', '', $clean);
            } elseif (str_contains($clean, ',') && !str_contains($clean, '.')) {
                $clean = str_replace(',', '.', $clean);
            }

            return (float) $clean;
        }
        return 0.0;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?? 'Laporan Keuangan' ?></title>
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
.sbadge.inf { background: #dbeafe; color: #1e40af; }

.rpt-footer { margin-top: 12px; font-size: 10px; color: var(--text-muted, #64748b); text-align: right; }

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
        <div class="rpt-title"><?= e($title ?? 'Laporan Keuangan') ?></div>
        <div class="rpt-meta">
            <?php if ($filterTanggalDari !== '' || $filterTanggalSampai !== ''): ?>
            Periode: <?= $filterTanggalDari !== '' ? date('d/m/Y', strtotime($filterTanggalDari)) : '...' ?> &ndash; <?= $filterTanggalSampai !== '' ? date('d/m/Y', strtotime($filterTanggalSampai)) : '...' ?> &bull;
            <?php endif; ?>
            Dicetak: <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <div class="dt-wrap">
        <table class="dtable w-100 nowrap">
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
            <tbody>
            <?php
            $totalNominal = 0;
            foreach ($rows as $r):
                if (!is_array($r)) continue;
                $nominal  = to_numeric_value($r['nominal'] ?? 0);
                $totalNominal += $nominal;
                $tipeArus = (string)($r['tipe_arus'] ?? '-');
                $badgeCls = $tipeArus === 'pemasukan' ? 'scc' : ($tipeArus === 'pengeluaran' ? 'wrn' : 'inf');
                $status   = (string)($r['status'] ?? '-');
            ?>
                <tr>
                    <td><?= e((string)($r['tanggal'] ?? '-')) ?></td>
                    <td><?= e((string)($r['no_ref'] ?? '-')) ?></td>
                    <td><?= e((string)($r['kode_akun'] ?? '-')) ?> &ndash; <?= e((string)($r['nama_akun'] ?? '-')) ?></td>
                    <td><span class="sbadge <?= $badgeCls ?>"><?= e($tipeArus) ?></span></td>
                    <td class="text-end"><?= format_currency_id($nominal) ?></td>
                    <td><?= e((string)($r['metode_pembayaran'] ?? '-')) ?></td>
                    <td><span class="sbadge <?= $status === 'posted' ? 'scc' : 'inf' ?>"><?= e($status) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="7" class="text-end" style="padding:20px;color:var(--text-muted,#64748b);font-style:italic;text-align:center;">Tidak ada data</td></tr>
            <?php endif; ?>
            </tbody>
            <?php if ($rows !== []): ?>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-end">TOTAL</td>
                    <td class="text-end"><?= format_currency_id($totalNominal) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
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
