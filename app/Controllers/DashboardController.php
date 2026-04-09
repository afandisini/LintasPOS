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

            $currentPeriode = date('Ym');
            $stats['month_revenue'] = (int) $pdo->query(
                "SELECT COALESCE(SUM(total),0) FROM penjualan WHERE periode = '{$currentPeriode}'"
            )->fetchColumn();
            $stats['month_transactions'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM penjualan WHERE periode = '{$currentPeriode}'"
            )->fetchColumn();

            $rawChart = $pdo->query(
                "SELECT periode, COALESCE(SUM(total),0) AS amount "
                . "FROM penjualan WHERE periode IS NOT NULL AND periode <> '' "
                . "GROUP BY periode ORDER BY periode DESC LIMIT 6"
            )->fetchAll();
            $dbMap = [];
            foreach (is_array($rawChart) ? $rawChart : [] as $row) {
                $dbMap[(string) ($row['periode'] ?? '')] = (int) ($row['amount'] ?? 0);
            }

            for ($i = 5; $i >= 0; $i--) {
                $dt = new \DateTimeImmutable('first day of -' . $i . ' month');
                $p  = $dt->format('Ym');
                $chartLabels[] = $dt->format('M Y');
                $chartValues[] = $dbMap[$p] ?? 0;
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
