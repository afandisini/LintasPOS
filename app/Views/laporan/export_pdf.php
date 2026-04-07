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
    <title><?= e($title ?? 'Laporan') ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --pdf-bg: #f3f4f6;
            --pdf-paper: #ffffff;
            --pdf-text: #1f2937;
            --pdf-muted: #6b7280;
            --pdf-line: #dfe3e8;
            --pdf-line-strong: #cfd6dd;
            --pdf-head: #f8fafc;
            --pdf-primary: #2563eb;
            --pdf-success-bg: #d1fae5;
            --pdf-success-text: #065f46;
            --pdf-warn-bg: #fef3c7;
            --pdf-warn-text: #92400e;
            --pdf-shadow: 0 10px 28px rgba(15, 23, 42, .08);
            --pdf-radius: 14px;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: var(--pdf-text);
        }

        .page {
            width: min(100%, 1180px);
            min-height: auto;
            padding: 28px;
            margin: 0 auto;
        }

        h1 {
            font-size: 28px;
            line-height: 1.15;
            margin-bottom: 4px;
            font-weight: 700;
            color: #111827;
        }

        .meta {
            font-size: 11px;
            color: var(--pdf-muted);
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .summary-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .summary-card {
            border: 1px solid var(--pdf-line);
            border-radius: 10px;
            padding: 10px 12px;
        }

        .summary-card .label {
            font-size: 10px;
            color: var(--pdf-muted);
            margin-bottom: 4px;
        }

        .summary-card .value {
            font-size: 24px;
            line-height: 1.1;
            font-weight: 700;
            color: #111827;
        }

        .table-wrap {
            overflow: hidden;
            border: 1px solid var(--pdf-line-strong);
            border-radius: 10px;
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }

        thead th {
            background: var(--pdf-head);
            border-bottom: 1px solid var(--pdf-line-strong);
            border-right: 1px solid var(--pdf-line);
            padding: 8px 8px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
        }

        thead th:last-child {
            border-right: none;
        }

        tbody td {
            border-top: 1px solid var(--pdf-line);
            border-right: 1px solid var(--pdf-line);
            padding: 7px 8px;
            font-size: 11px;
            vertical-align: top;
            color: #1f2937;
        }

        tbody td:last-child {
            border-right: none;
        }

        tbody tr:nth-child(even) td {
            background: #fcfcfd;
        }

        .text-end {
            text-align: right;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
            white-space: nowrap;
        }

        .badge-scc {
            background: var(--pdf-success-bg);
            color: var(--pdf-success-text);
        }

        .badge-wrn {
            background: var(--pdf-warn-bg);
            color: var(--pdf-warn-text);
        }

        .footer {
            margin-top: 14px;
            font-size: 10px;
            color: #808892;
            text-align: right;
        }

        .print-btn {
            position: sticky;
            top: 12px;
            margin-left: auto;
            margin-bottom: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: var(--pdf-primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 8px 20px rgba(37, 99, 235, .20);
            z-index: 10;
        }

        .print-btn:hover {
            filter: brightness(.97);
        }

        .empty-row {
            text-align: center;
            color: #888;
            padding: 18px 8px !important;
        }

        @media (max-width: 1100px) {
            .page {
                width: 100%;
                padding: 20px;
            }

            .summary-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .summary-card .value {
                font-size: 20px;
            }

            h1 {
                font-size: 24px;
            }
        }

        @media (max-width: 700px) {
            body {
                padding: 10px;
            }

            .page {
                padding: 14px;
                border-radius: 10px;
            }

            .summary-row {
                grid-template-columns: 1fr;
            }

            .summary-card .value {
                font-size: 18px;
            }

            h1 {
                font-size: 20px;
            }

            .meta {
                font-size: 11px;
            }

            thead th,
            tbody td {
                padding: 6px 6px;
                font-size: 10px;
            }

            .print-btn {
                width: 100%;
                justify-content: center;
                position: static;
            }
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
                font-size: 10px;
            }

            .print-btn {
                display: none !important;
            }

            .page {
                width: 100%;
                min-height: auto;
                padding: 0;
                margin: 0;
                border: none;
                border-radius: 0;
                box-shadow: none;
                background: #fff;
            }

            h1 {
                font-size: 16px;
                margin-bottom: 2px;
            }

            .meta {
                font-size: 10px;
                margin-bottom: 10px;
            }

            .summary-row {
                display: flex;
                gap: 8px;
                margin-bottom: 10px;
            }

            .summary-card {
                flex: 1;
                min-width: 100px;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 7px 10px;
            }

            .summary-card .label {
                font-size: 9px;
                margin-bottom: 2px;
                color: #666;
            }

            .summary-card .value {
                font-size: 13px;
            }

            .table-wrap {
                border: none;
                border-radius: 0;
                overflow: visible;
            }

            thead th {
                background: #f0f0f0 !important;
                font-size: 10px;
                padding: 5px 6px;
                border: 1px solid #ccc;
            }

            tbody td {
                font-size: 10px;
                padding: 4px 6px;
                border: 1px solid #ddd;
            }

            tbody tr:nth-child(even) td {
                background: #fafafa !important;
            }

            .badge {
                font-size: 9px;
                padding: 1px 6px;
                border-radius: 3px;
            }

            .footer {
                margin-top: 12px;
                font-size: 9px;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <h2><?= e($title ?? 'Laporan') ?></h2>
        <div class="meta">
            Periode: <?= e($filterTanggalDari !== '' ? $filterTanggalDari : '-') ?> s/d <?= e($filterTanggalSampai !== '' ? $filterTanggalSampai : '-') ?>
            <?php if ($filterMetode !== ''): ?><?php endif; ?>
        </div>

        <div class="summary-row">
            <div class="summary-card">
                <div class="label">Total Transaksi</div>
                <div class="value"><?= e(number_format((int) ($summary['total_transaksi'] ?? 0), 0, ',', '.')) ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Total Qty</div>
                <div class="value"><?= e(number_format((int) ($summary['total_qty'] ?? 0), 0, ',', '.')) ?></div>
            </div>
            <div class="summary-card">
                <div class="label"><?= $tipe === 'penjualan' ? 'Total Pendapatan' : 'Total Pembelian' ?></div>
                <div class="value">Rp <?= e(number_format((int) ($summary['grand_total'] ?? 0), 0, ',', '.')) ?></div>
            </div>
            <?php if ($canViewModal): ?>
                <div class="summary-card">
                    <div class="label">Total Modal</div>
                    <div class="value">Rp <?= e(number_format((int) ($summary['total_modal'] ?? 0), 0, ',', '.')) ?></div>
                </div>
                <?php if ($tipe === 'penjualan'): ?>
                    <div class="summary-card">
                        <div class="label">Laba Kotor</div>
                        <div class="value">Rp <?= e(number_format((int) ($summary['laba'] ?? 0), 0, ',', '.')) ?></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($tipe === 'penjualan'): ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
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
                        <tr>
                            <td colspan="<?= $canViewModal ? 10 : 9 ?>" style="text-align:center;color:#888;">Tidak ada data</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $row): ?>
                            <?php if (!is_array($row)) continue; ?>
                            <tr>
                                <td><?= e((string) ($i + 1)) ?></td>
                                <td><?= e((string) ($row['no_trx'] ?? '-')) ?></td>
                                <td><?= e((string) ($row['tanggal_input'] ?? '-')) ?></td>
                                <td><?= e((string) ($row['nama_pelanggan'] ?? 'Umum')) ?></td>
                                <td class="text-end"><?= e((string) ($row['total_qty'] ?? 0)) ?></td>
                                <td class="text-end">Rp <?= e(number_format((int) ($row['total'] ?? 0), 0, ',', '.')) ?></td>
                                <td class="text-end">Rp <?= e(number_format((int) ($row['bayar'] ?? 0), 0, ',', '.')) ?></td>
                                <td><?= e((string) ($row['payment_method'] ?? '-')) ?></td>
                                <td>
                                    <?php $st = (string) ($row['status_bayar'] ?? '-'); ?>
                                    <span class="badge <?= $st === 'Lunas' ? 'badge-scc' : 'badge-wrn' ?>"><?= e($st) ?></span>
                                </td>
                                <?php if ($canViewModal): ?>
                                    <td class="text-end">Rp <?= e(number_format((int) ($row['total_modal'] ?? 0), 0, ',', '.')) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>No Trx</th>
                        <th>Tanggal</th>
                        <th>Supplier</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Total Beli</th>
                        <th>Status</th>
                        <?php if ($canViewModal): ?><th class="text-end">Modal</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="<?= $canViewModal ? 8 : 7 ?>" style="text-align:center;color:#888;">Tidak ada data</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $i => $row): ?>
                            <?php if (!is_array($row)) continue; ?>
                            <tr>
                                <td><?= e((string) ($i + 1)) ?></td>
                                <td><?= e((string) ($row['no_trx'] ?? '-')) ?></td>
                                <td><?= e((string) ($row['tanggal_input'] ?? '-')) ?></td>
                                <td><?= e((string) ($row['nm_supplier'] ?? '-')) ?></td>
                                <td class="text-end"><?= e((string) ($row['total_qty'] ?? 0)) ?></td>
                                <td class="text-end">Rp <?= e(number_format((int) ($row['total'] ?? 0), 0, ',', '.')) ?></td>
                                <td>
                                    <?php $st = (string) ($row['status_bayar'] ?? '-'); ?>
                                    <span class="badge <?= $st === 'Lunas' ? 'badge-scc' : 'badge-wrn' ?>"><?= e($st) ?></span>
                                </td>
                                <?php if ($canViewModal): ?>
                                    <td class="text-end">Rp <?= e(number_format((int) ($row['total_modal'] ?? 0), 0, ',', '.')) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="footer">Generated by {{nama_toko}} &mdash; <?= e(date('d/m/Y H:i:s')) ?></div>
    </div>
    <?php if ($autoPrint ?? false): ?>
        <script>
            window.addEventListener('load', function() {
                window.print();
            });
        </script>
    <?php endif; ?>
</body>

</html>