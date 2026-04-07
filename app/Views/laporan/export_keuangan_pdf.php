<?php

/** @var string $title */
/** @var array<string,mixed> $auth */
/** @var array<int,array<string,mixed>> $rows */
/** @var string $filterTanggalDari */
/** @var string $filterTanggalSampai */
/** @var bool $autoPrint */

if (!function_exists('to_numeric_value')) {
    function to_numeric_value(mixed $value): int
    {
        if (is_int($value) || is_float($value)) {
            return (int) $value;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $value = (string) $value;
            } else {
                return 0;
            }
        }

        if (is_string($value)) {
            $clean = strip_tags($value);
            $clean = html_entity_decode($clean, ENT_QUOTES, 'UTF-8');
            $clean = preg_replace('/[^0-9\-]/', '', $clean) ?? '0';

            if ($clean === '' || $clean === '-') {
                return 0;
            }

            return (int) $clean;
        }

        return 0;
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            padding: 20mm;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 4px;
        }

        h2 {
            font-size: 14px;
            margin-bottom: 8px;
            color: #333;
        }

        .meta {
            margin-bottom: 12px;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 5px 6px;
            text-align: left;
        }

        th {
            background: #f5f5f5;
            font-weight: 600;
            font-size: 10px;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .summary {
            margin-top: 12px;
            padding: 8px;
            background: #f9f9f9;
            border: 1px solid #ddd;
        }

        .summary div {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
        }

        .summary .grand {
            font-weight: 700;
            font-size: 12px;
            border-top: 2px solid #333;
            padding-top: 6px;
            margin-top: 4px;
        }

        @media print {
            body {
                padding: 10mm;
            }

            @page {
                size: A4 landscape;
                margin: 10mm;
            }
        }
    </style>
</head>

<body>
    <h1><?= $title ?? 'Laporan Keuangan' ?></h1>
    <div class="meta">
        <?php if ($filterTanggalDari !== '' || $filterTanggalSampai !== ''): ?>
            Periode: <?= $filterTanggalDari !== '' ? date('d/m/Y', strtotime($filterTanggalDari)) : '...' ?> - <?= $filterTanggalSampai !== '' ? date('d/m/Y', strtotime($filterTanggalSampai)) : '...' ?> |
        <?php endif; ?>
        Dicetak: <?= date('d/m/Y H:i') ?>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:80px;">Tanggal</th>
                <th style="width:100px;">No Ref</th>
                <th>Akun</th>
                <th style="width:80px;">Tipe Arus</th>
                <th style="width:100px;" class="text-end">Nominal</th>
                <th style="width:80px;">Metode</th>
                <th style="width:60px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $totalNominal = 0;
            foreach ($rows as $r):
                if (!is_array($r)) continue;

                $nominal = to_numeric_value($r['nominal'] ?? 0);
                $totalNominal += $nominal;
            ?>
                <tr>
                    <td><?= (string) ($r['tanggal'] ?? '-') ?></td>
                    <td><?= (string) ($r['no_ref'] ?? '-') ?></td>
                    <td><?= (string) ($r['kode_akun'] ?? '-') ?> - <?= (string) ($r['nama_akun'] ?? '-') ?></td>
                    <td><?= (string) ($r['tipe_arus'] ?? '-') ?></td>
                    <td class="text-end"><?= format_currency_id($nominal) ?></td>
                    <td><?= (string) ($r['metode_pembayaran'] ?? '-') ?></td>
                    <td><?= (string) ($r['status'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:700;background:#f0f0f0;">
                <td colspan="4" class="text-end">TOTAL</td>
                <td class="text-end"><?= format_currency_id($totalNominal) ?></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <?php if ($autoPrint): ?>
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    <?php endif; ?>
</body>

</html>