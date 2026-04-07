<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class DashboardController
{
    public function index(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];

        $stats = [
            'users' => 0,
            'customers' => 0,
            'products' => 0,
            'today_sales' => 0,
            'month_revenue' => 0,
            'month_transactions' => 0,
        ];
        $chartLabels = [];
        $chartValues = [];
        $recentSales = [];
        $lowStocks = [];

        try {
            $pdo = Database::connection();

            $stats['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $stats['customers'] = (int) $pdo->query('SELECT COUNT(*) FROM pelanggan WHERE deleted_at IS NULL')->fetchColumn();
            $stats['products'] = (int) $pdo->query('SELECT COUNT(*) FROM barang WHERE deleted_at IS NULL')->fetchColumn();
            $stats['today_sales'] = (int) $pdo->query(
                "SELECT COALESCE(SUM(total),0) FROM penjualan WHERE tanggal_input = CURDATE()"
            )->fetchColumn();
            $stats['month_revenue'] = (int) $pdo->query(
                "SELECT COALESCE(SUM(total),0) FROM penjualan WHERE DATE_FORMAT(STR_TO_DATE(tanggal_input, '%Y-%m-%d'), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"
            )->fetchColumn();
            $stats['month_transactions'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM penjualan WHERE DATE_FORMAT(STR_TO_DATE(tanggal_input, '%Y-%m-%d'), '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"
            )->fetchColumn();

            $statementChart = $pdo->query(
                "SELECT DATE_FORMAT(STR_TO_DATE(tanggal_input, '%Y-%m-%d'), '%Y-%m') AS ym, COALESCE(SUM(total),0) AS amount "
                . "FROM penjualan "
                . "WHERE tanggal_input IS NOT NULL AND tanggal_input <> '' "
                . "GROUP BY ym ORDER BY ym DESC LIMIT 6"
            );
            $rawChart = $statementChart->fetchAll();
            $rawChart = array_reverse(is_array($rawChart) ? $rawChart : []);

            foreach ($rawChart as $row) {
                $ym = (string) ($row['ym'] ?? '');
                if ($ym === '') {
                    continue;
                }

                $date = \DateTimeImmutable::createFromFormat('Y-m', $ym);
                $chartLabels[] = $date ? $date->format('M Y') : $ym;
                $chartValues[] = (int) ($row['amount'] ?? 0);
            }

            $recentSalesStmt = $pdo->query(
                "SELECT p.no_trx, p.tanggal_input, p.total, p.status_bayar, p.payment_method, COALESCE(pl.nama_pelanggan, 'Pelanggan Umum') AS pelanggan "
                . "FROM penjualan p "
                . "LEFT JOIN pelanggan pl ON pl.id = p.id_pelanggan "
                . "ORDER BY p.id DESC LIMIT 8"
            );
            $recentSales = $recentSalesStmt->fetchAll();
            if (!is_array($recentSales)) {
                $recentSales = [];
            }

            $lowStockStmt = $pdo->query(
                "SELECT id_barang, nama_barang, stok, harga_jual FROM barang WHERE stok <= 10 ORDER BY stok ASC, id DESC LIMIT 8"
            );
            $lowStocks = $lowStockStmt->fetchAll();
            if (!is_array($lowStocks)) {
                $lowStocks = [];
            }
        } catch (Throwable) {
            // Keep dashboard functional even when DB query fails.
        }

        $html = app()->view()->render('dashboard/index', [
            'title' => 'Dashboard ' . brand_name(),
            'auth' => $auth,
            'activeMenu' => 'dashboard',
            'stats' => $stats,
            'chart_labels_json' => json_encode($chartLabels, JSON_UNESCAPED_UNICODE),
            'chart_values_json' => json_encode($chartValues, JSON_UNESCAPED_UNICODE),
            'recent_sales' => $recentSales,
            'low_stocks' => $lowStocks,
        ]);

        return Response::html($html);
    }
}
