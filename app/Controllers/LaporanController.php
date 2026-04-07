<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use PDO;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class LaporanController
{
    private const PAYMENT_METHODS = ['Cash', 'E-wallet', 'QRIS', 'Transfer Bank', 'Termin'];

    public function index(Request $request): Response
    {
        $auth = $this->auth();
        if (!$this->canAccessLaporan($auth)) {
            toast_add('Anda tidak punya akses ke fitur laporan.', 'error');
            return Response::redirect('/dashboard');
        }

        $payload = [
            'pelangganOptions' => [],
            'supplierOptions' => [],
            'kategoriOptions' => [],
            'akunOptions' => [],
            'produkOptions' => [],
            'paymentMethods' => self::PAYMENT_METHODS,
            'canViewModal' => $this->canViewModal($auth),
        ];

        try {
            $pdo = Database::connection();
            $payload['pelangganOptions'] = $pdo->query('SELECT id, nama_pelanggan FROM pelanggan WHERE deleted_at IS NULL ORDER BY nama_pelanggan ASC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
            $payload['supplierOptions'] = $pdo->query('SELECT id, nama_supplier FROM supplier ORDER BY nama_supplier ASC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
            $payload['kategoriOptions'] = $pdo->query('SELECT id, nama_kategori FROM kategori WHERE deleted_at IS NULL ORDER BY nama_kategori ASC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
            $payload['akunOptions'] = $pdo->query('SELECT id, kode_akun, nama_akun FROM akun_keuangan WHERE deleted_at IS NULL AND status = 1 ORDER BY kode_akun ASC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
        }

        $html = app()->view()->render('laporan/index', [
            'title' => 'Laporan',
            'auth' => $auth,
            'activeMenu' => 'laporan',
            ...$payload,
        ]);

        return Response::html($html);
    }

    public function datatable(Request $request): Response
    {
        $auth = $this->auth();
        if (!$this->canAccessLaporan($auth)) {
            return Response::json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Akses ditolak.'], 403);
        }

        $params = $request->all();
        $draw = max(0, (int) ($params['draw'] ?? 0));
        $start = max(0, (int) ($params['start'] ?? 0));
        $length = min(100, max(10, (int) ($params['length'] ?? 25)));
        $search = trim((string) (($params['search']['value'] ?? '') ?: ''));

        $tipe = strtolower(trim((string) ($params['tipe'] ?? 'penjualan')));
        if (!in_array($tipe, ['penjualan', 'pembelian'], true)) {
            $tipe = 'penjualan';
        }

        $filterTanggalDari = trim((string) ($params['tanggal_dari'] ?? ''));
        $filterTanggalSampai = trim((string) ($params['tanggal_sampai'] ?? ''));
        $filterPelanggan = (int) ($params['id_pelanggan'] ?? 0);
        $filterSupplier = trim((string) ($params['nm_supplier'] ?? ''));
        $filterMetode = trim((string) ($params['payment_method'] ?? ''));
        $filterKategori = (int) ($params['id_kategori'] ?? 0);
        $filterProduk = trim((string) ($params['nama_produk'] ?? ''));

        try {
            $pdo = Database::connection();
            $canViewModal = $this->canViewModal($auth);

            if ($tipe === 'penjualan') {
                return $this->datatablePenjualan($pdo, $draw, $start, $length, $search, $filterTanggalDari, $filterTanggalSampai, $filterPelanggan, $filterMetode, $filterKategori, $filterProduk, $canViewModal);
            }
            return $this->datatablePembelian($pdo, $draw, $start, $length, $search, $filterTanggalDari, $filterTanggalSampai, $filterSupplier, $filterMetode, $filterKategori, $filterProduk, $canViewModal);
        } catch (Throwable $e) {
            return Response::json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function datatableRugiLaba(Request $request): Response
    {
        $auth = $this->auth();
        if (!$this->canAccessLaporan($auth)) {
            return Response::json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Akses ditolak.'], 403);
        }

        $params = $request->all();
        $draw = max(0, (int) ($params['draw'] ?? 0));
        $tahun = (int) ($params['tahun'] ?? date('Y'));
        if ($tahun < 2000 || $tahun > 2100) $tahun = (int) date('Y');

        try {
            $pdo = Database::connection();
            $canViewModal = $this->canViewModal($auth);

            $rows = [];
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $periode = sprintf('%04d%02d', $tahun, $bulan);
                $namaBulan = date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun));

                // Penjualan
                $stmtPenjualan = $pdo->prepare('SELECT COALESCE(SUM(total), 0) AS total_penjualan, COALESCE(SUM(beli), 0) AS total_modal FROM penjualan WHERE periode = :periode');
                $stmtPenjualan->execute(['periode' => $periode]);
                $penjualan = $stmtPenjualan->fetch(PDO::FETCH_ASSOC);
                $totalPenjualan = (int) ($penjualan['total_penjualan'] ?? 0);
                $modalPenjualan = $canViewModal ? (int) ($penjualan['total_modal'] ?? 0) : 0;

                // Pembelian
                $stmtPembelian = $pdo->prepare('SELECT COALESCE(SUM(beli), 0) AS total_pembelian FROM pembelian WHERE periode = :periode');
                $stmtPembelian->execute(['periode' => $periode]);
                $pembelian = $stmtPembelian->fetch(PDO::FETCH_ASSOC);
                $totalPembelian = (int) ($pembelian['total_pembelian'] ?? 0);

                $labaKotor = $totalPenjualan - $modalPenjualan;
                $labaBersih = $labaKotor - $totalPembelian;

                $rows[] = [
                    'bulan' => $namaBulan,
                    'periode' => $periode,
                    'total_penjualan' => $totalPenjualan,
                    'total_modal' => $modalPenjualan,
                    'laba_kotor' => $labaKotor,
                    'total_pembelian' => $totalPembelian,
                    'laba_bersih' => $labaBersih,
                ];
            }

            return Response::json(['draw' => $draw, 'recordsTotal' => 12, 'recordsFiltered' => 12, 'data' => $rows]);
        } catch (Throwable $e) {
            return Response::json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function datatableKeuangan(Request $request): Response
    {
        $auth = $this->auth();
        if (!$this->canAccessLaporan($auth)) {
            return Response::json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => 'Akses ditolak.'], 403);
        }

        $params = $request->all();
        $draw = max(0, (int) ($params['draw'] ?? 0));
        $start = max(0, (int) ($params['start'] ?? 0));
        $length = min(100, max(10, (int) ($params['length'] ?? 25)));
        $search = trim((string) (($params['search']['value'] ?? '') ?: ''));
        $filterTanggalDari = trim((string) ($params['tanggal_dari'] ?? ''));
        $filterTanggalSampai = trim((string) ($params['tanggal_sampai'] ?? ''));
        $filterTipeArus = trim((string) ($params['tipe_arus'] ?? ''));
        $filterAkun = (int) ($params['akun_keuangan_id'] ?? 0);

        try {
            $pdo = Database::connection();
            $where = ' WHERE k.deleted_at IS NULL';
            $bindings = [];

            if ($filterTanggalDari !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTanggalDari)) {
                $where .= ' AND k.tanggal >= :tanggal_dari';
                $bindings['tanggal_dari'] = $filterTanggalDari;
            }
            if ($filterTanggalSampai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTanggalSampai)) {
                $where .= ' AND k.tanggal <= :tanggal_sampai';
                $bindings['tanggal_sampai'] = $filterTanggalSampai;
            }
            if ($filterTipeArus !== '') {
                $where .= ' AND k.tipe_arus = :tipe_arus';
                $bindings['tipe_arus'] = $filterTipeArus;
            }
            if ($filterAkun > 0) {
                $where .= ' AND k.akun_keuangan_id = :akun_keuangan_id';
                $bindings['akun_keuangan_id'] = $filterAkun;
            }
            if ($search !== '') {
                $where .= ' AND (k.no_ref LIKE :search OR k.deskripsi LIKE :search OR ak.nama_akun LIKE :search OR ak.kode_akun LIKE :search)';
                $bindings['search'] = '%' . $search . '%';
            }

            $baseFrom = ' FROM keuangan k LEFT JOIN akun_keuangan ak ON ak.id = k.akun_keuangan_id';

            $stmtCount = $pdo->prepare('SELECT COUNT(*) ' . $baseFrom . $where);
            foreach ($bindings as $k => $v) {
                $stmtCount->bindValue(':' . $k, $v);
            }
            $stmtCount->execute();
            $total = (int) $stmtCount->fetchColumn();

            $sql = 'SELECT k.id, k.tanggal, k.no_ref, ak.kode_akun, ak.nama_akun, k.tipe_arus, k.nominal, k.metode_pembayaran, k.deskripsi, k.status'
                . $baseFrom . $where
                . ' ORDER BY k.tanggal DESC, k.id DESC LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($bindings as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return Response::json(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => is_array($rows) ? $rows : []]);
        } catch (Throwable $e) {
            return Response::json(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function exportPdf(Request $request): Response
    {
        $auth = $this->auth();
        if (!$this->canAccessLaporan($auth)) {
            toast_add('Anda tidak punya akses ke fitur laporan.', 'error');
            return Response::redirect('/dashboard');
        }

        $params = $request->all();
        $tab = trim((string) ($params['tab'] ?? 'transaksi'));
        $autoPrint = (string) ($params['print'] ?? '') === '1';
        $canViewModal = $this->canViewModal($auth);

        if ($tab === 'rugi-laba') {
            return $this->exportRugiLabaPdf($request, $auth, $canViewModal, $autoPrint);
        }
        if ($tab === 'keuangan') {
            return $this->exportKeuanganPdf($request, $auth, $canViewModal, $autoPrint);
        }
        return $this->exportTransaksiPdf($request, $auth, $canViewModal, $autoPrint);
    }

    // ── private helpers ──────────────────────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    private function auth(): array
    {
        return is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function canAccessLaporan(array $auth): bool
    {
        $role = strtolower(trim((string) ($auth['role'] ?? '')));
        return in_array($role, ['admin', 'administrator', 'owner', 'spv', 'superadmin', 'super-admin'], true);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function canViewModal(array $auth): bool
    {
        $role = strtolower(trim((string) ($auth['role'] ?? '')));
        return in_array($role, ['admin', 'administrator', 'owner', 'spv', 'superadmin', 'super-admin'], true);
    }

    private function buildDateWhere(string $col, string $dari, string $sampai): string
    {
        $parts = [];
        if ($dari !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
            $parts[] = $col . ' >= ' . "'" . $dari . "'";
        }
        if ($sampai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
            $parts[] = $col . ' <= ' . "'" . $sampai . "'";
        }
        return $parts !== [] ? ' AND ' . implode(' AND ', $parts) : '';
    }

    private function datatablePenjualan(
        \PDO $pdo, int $draw, int $start, int $length, string $search,
        string $dari, string $sampai, int $pelanggan, string $metode,
        int $kategori, string $produk, bool $canViewModal
    ): Response {
        $where = ' WHERE 1=1';
        $bindings = [];

        $where .= $this->buildDateWhere('p.tanggal_input', $dari, $sampai);

        if ($pelanggan > 0) {
            $where .= ' AND p.id_pelanggan = :id_pelanggan';
            $bindings['id_pelanggan'] = $pelanggan;
        }
        if ($metode !== '') {
            $where .= ' AND p.payment_method = :payment_method';
            $bindings['payment_method'] = $metode;
        }
        if ($produk !== '') {
            $where .= ' AND EXISTS (SELECT 1 FROM penjualan_detail pd2 WHERE pd2.no_trx = p.no_trx AND pd2.nama_barang LIKE :nama_produk)';
            $bindings['nama_produk'] = '%' . $produk . '%';
        }
        if ($kategori > 0) {
            $where .= ' AND EXISTS (SELECT 1 FROM penjualan_detail pd3 INNER JOIN barang b3 ON b3.id = pd3.id_barang WHERE pd3.no_trx = p.no_trx AND b3.id_kategori = :id_kategori)';
            $bindings['id_kategori'] = $kategori;
        }
        if ($search !== '') {
            $where .= ' AND (p.no_trx LIKE :search OR pl.nama_pelanggan LIKE :search OR p.payment_method LIKE :search)';
            $bindings['search'] = '%' . $search . '%';
        }

        $baseFrom = ' FROM penjualan p LEFT JOIN pelanggan pl ON pl.id = p.id_pelanggan';

        $stmtCount = $pdo->prepare('SELECT COUNT(*) ' . $baseFrom . $where);
        foreach ($bindings as $k => $v) {
            $stmtCount->bindValue(':' . $k, $v);
        }
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        $modalCol = $canViewModal ? ', p.beli AS total_modal' : ', 0 AS total_modal';
        $sql = 'SELECT p.id, p.no_trx, p.tanggal_input, pl.nama_pelanggan, p.jumlah AS total_qty, p.total, p.bayar, p.status_bayar, p.payment_method' . $modalCol
            . $baseFrom . $where
            . ' ORDER BY p.id DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($bindings as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => is_array($rows) ? $rows : []]);
    }

    private function datatablePembelian(
        \PDO $pdo, int $draw, int $start, int $length, string $search,
        string $dari, string $sampai, string $supplier, string $metode,
        int $kategori, string $produk, bool $canViewModal
    ): Response {
        $where = ' WHERE 1=1';
        $bindings = [];

        $where .= $this->buildDateWhere('p.tanggal_input', $dari, $sampai);

        if ($supplier !== '') {
            $where .= ' AND p.nm_supplier LIKE :nm_supplier';
            $bindings['nm_supplier'] = '%' . $supplier . '%';
        }
        if ($metode !== '') {
            $where .= ' AND p.status_bayar = :status_bayar';
            $bindings['status_bayar'] = $metode;
        }
        if ($produk !== '') {
            $where .= ' AND EXISTS (SELECT 1 FROM pembelian_detail pd2 WHERE pd2.no_trx = p.no_trx AND pd2.nama_barang LIKE :nama_produk)';
            $bindings['nama_produk'] = '%' . $produk . '%';
        }
        if ($kategori > 0) {
            $where .= ' AND EXISTS (SELECT 1 FROM pembelian_detail pd3 INNER JOIN barang b3 ON b3.id = pd3.id_barang WHERE pd3.no_trx = p.no_trx AND b3.id_kategori = :id_kategori)';
            $bindings['id_kategori'] = $kategori;
        }
        if ($search !== '') {
            $where .= ' AND (p.no_trx LIKE :search OR p.nm_supplier LIKE :search OR p.status_bayar LIKE :search)';
            $bindings['search'] = '%' . $search . '%';
        }

        $baseFrom = ' FROM pembelian p';
        $modalCol = $canViewModal ? ', p.beli AS total_modal' : ', 0 AS total_modal';

        $stmtCount = $pdo->prepare('SELECT COUNT(*) ' . $baseFrom . $where);
        foreach ($bindings as $k => $v) {
            $stmtCount->bindValue(':' . $k, $v);
        }
        $stmtCount->execute();
        $total = (int) $stmtCount->fetchColumn();

        $sql = 'SELECT p.id, p.no_trx, p.tanggal_input, p.nm_supplier, p.jumlah AS total_qty, p.beli AS total, p.status_bayar, p.keterangan' . $modalCol
            . $baseFrom . $where
            . ' ORDER BY p.id DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($bindings as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return Response::json(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $total, 'data' => is_array($rows) ? $rows : []]);
    }

    /**
     * @return array{0: array<int,array<string,mixed>>, 1: array<string,int>}
     */
    private function fetchPenjualanForExport(
        \PDO $pdo, string $dari, string $sampai, int $pelanggan,
        string $metode, int $kategori, string $produk, bool $canViewModal
    ): array {
        $where = ' WHERE 1=1';
        $bindings = [];
        $where .= $this->buildDateWhere('p.tanggal_input', $dari, $sampai);
        if ($pelanggan > 0) { $where .= ' AND p.id_pelanggan = :id_pelanggan'; $bindings['id_pelanggan'] = $pelanggan; }
        if ($metode !== '') { $where .= ' AND p.payment_method = :payment_method'; $bindings['payment_method'] = $metode; }
        if ($produk !== '') { $where .= ' AND EXISTS (SELECT 1 FROM penjualan_detail pd2 WHERE pd2.no_trx = p.no_trx AND pd2.nama_barang LIKE :nama_produk)'; $bindings['nama_produk'] = '%' . $produk . '%'; }
        if ($kategori > 0) { $where .= ' AND EXISTS (SELECT 1 FROM penjualan_detail pd3 INNER JOIN barang b3 ON b3.id = pd3.id_barang WHERE pd3.no_trx = p.no_trx AND b3.id_kategori = :id_kategori)'; $bindings['id_kategori'] = $kategori; }

        $modalCol = $canViewModal ? ', p.beli AS total_modal' : ', 0 AS total_modal';
        $sql = 'SELECT p.no_trx, p.tanggal_input, pl.nama_pelanggan, p.jumlah AS total_qty, p.total, p.bayar, p.status_bayar, p.payment_method' . $modalCol
            . ' FROM penjualan p LEFT JOIN pelanggan pl ON pl.id = p.id_pelanggan' . $where . ' ORDER BY p.tanggal_input ASC, p.id ASC';
        $stmt = $pdo->prepare($sql);
        foreach ($bindings as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) $rows = [];

        $summary = ['total_transaksi' => count($rows), 'total_qty' => 0, 'grand_total' => 0, 'total_modal' => 0, 'laba' => 0];
        foreach ($rows as $r) {
            $summary['total_qty'] += (int) ($r['total_qty'] ?? 0);
            $summary['grand_total'] += (int) ($r['total'] ?? 0);
            $summary['total_modal'] += (int) ($r['total_modal'] ?? 0);
        }
        $summary['laba'] = $summary['grand_total'] - $summary['total_modal'];
        return [$rows, $summary];
    }

    /**
     * @return array{0: array<int,array<string,mixed>>, 1: array<string,int>}
     */
    private function fetchPembelianForExport(
        \PDO $pdo, string $dari, string $sampai, string $supplier,
        string $metode, int $kategori, string $produk, bool $canViewModal
    ): array {
        $where = ' WHERE 1=1';
        $bindings = [];
        $where .= $this->buildDateWhere('p.tanggal_input', $dari, $sampai);
        if ($supplier !== '') { $where .= ' AND p.nm_supplier LIKE :nm_supplier'; $bindings['nm_supplier'] = '%' . $supplier . '%'; }
        if ($metode !== '') { $where .= ' AND p.status_bayar = :status_bayar'; $bindings['status_bayar'] = $metode; }
        if ($produk !== '') { $where .= ' AND EXISTS (SELECT 1 FROM pembelian_detail pd2 WHERE pd2.no_trx = p.no_trx AND pd2.nama_barang LIKE :nama_produk)'; $bindings['nama_produk'] = '%' . $produk . '%'; }
        if ($kategori > 0) { $where .= ' AND EXISTS (SELECT 1 FROM pembelian_detail pd3 INNER JOIN barang b3 ON b3.id = pd3.id_barang WHERE pd3.no_trx = p.no_trx AND b3.id_kategori = :id_kategori)'; $bindings['id_kategori'] = $kategori; }

        $modalCol = $canViewModal ? ', p.beli AS total_modal' : ', 0 AS total_modal';
        $sql = 'SELECT p.no_trx, p.tanggal_input, p.nm_supplier, p.jumlah AS total_qty, p.beli AS total, p.status_bayar, p.keterangan' . $modalCol
            . ' FROM pembelian p' . $where . ' ORDER BY p.tanggal_input ASC, p.id ASC';
        $stmt = $pdo->prepare($sql);
        foreach ($bindings as $k => $v) { $stmt->bindValue(':' . $k, $v); }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) $rows = [];

        $summary = ['total_transaksi' => count($rows), 'total_qty' => 0, 'grand_total' => 0, 'total_modal' => 0, 'laba' => 0];
        foreach ($rows as $r) {
            $summary['total_qty'] += (int) ($r['total_qty'] ?? 0);
            $summary['grand_total'] += (int) ($r['total'] ?? 0);
            $summary['total_modal'] += (int) ($r['total_modal'] ?? 0);
        }
        return [$rows, $summary];
    }

    private function exportTransaksiPdf(Request $request, array $auth, bool $canViewModal, bool $autoPrint): Response
    {
        $params = $request->all();
        $tipe = strtolower(trim((string) ($params['tipe'] ?? 'penjualan')));
        if (!in_array($tipe, ['penjualan', 'pembelian'], true)) $tipe = 'penjualan';
        $filterTanggalDari = trim((string) ($params['tanggal_dari'] ?? ''));
        $filterTanggalSampai = trim((string) ($params['tanggal_sampai'] ?? ''));
        $filterPelanggan = (int) ($params['id_pelanggan'] ?? 0);
        $filterSupplier = trim((string) ($params['nm_supplier'] ?? ''));
        $filterMetode = trim((string) ($params['payment_method'] ?? ''));
        $filterKategori = (int) ($params['id_kategori'] ?? 0);
        $filterProduk = trim((string) ($params['nama_produk'] ?? ''));
        $rows = [];
        $summary = ['total_transaksi' => 0, 'total_qty' => 0, 'grand_total' => 0, 'total_modal' => 0, 'laba' => 0];
        try {
            $pdo = Database::connection();
            if ($tipe === 'penjualan') {
                [$rows, $summary] = $this->fetchPenjualanForExport($pdo, $filterTanggalDari, $filterTanggalSampai, $filterPelanggan, $filterMetode, $filterKategori, $filterProduk, $canViewModal);
            } else {
                [$rows, $summary] = $this->fetchPembelianForExport($pdo, $filterTanggalDari, $filterTanggalSampai, $filterSupplier, $filterMetode, $filterKategori, $filterProduk, $canViewModal);
            }
        } catch (Throwable) {}
        $html = app()->view()->render('laporan/export_pdf', [
            'title' => 'Laporan ' . ucfirst($tipe),
            'auth' => $auth,
            'tipe' => $tipe,
            'rows' => $rows,
            'summary' => $summary,
            'canViewModal' => $canViewModal,
            'filterTanggalDari' => $filterTanggalDari,
            'filterTanggalSampai' => $filterTanggalSampai,
            'filterMetode' => $filterMetode,
            'autoPrint' => $autoPrint,
        ]);
        return Response::html($html);
    }

    private function exportRugiLabaPdf(Request $request, array $auth, bool $canViewModal, bool $autoPrint): Response
    {
        $params = $request->all();
        $tahun = (int) ($params['tahun'] ?? date('Y'));
        if ($tahun < 2000 || $tahun > 2100) $tahun = (int) date('Y');
        $rows = [];
        try {
            $pdo = Database::connection();
            for ($bulan = 1; $bulan <= 12; $bulan++) {
                $periode = sprintf('%04d%02d', $tahun, $bulan);
                $namaBulan = date('F Y', mktime(0, 0, 0, $bulan, 1, $tahun));
                $stmtPenjualan = $pdo->prepare('SELECT COALESCE(SUM(total), 0) AS total_penjualan, COALESCE(SUM(beli), 0) AS total_modal FROM penjualan WHERE periode = :periode');
                $stmtPenjualan->execute(['periode' => $periode]);
                $penjualan = $stmtPenjualan->fetch(PDO::FETCH_ASSOC);
                $totalPenjualan = (int) ($penjualan['total_penjualan'] ?? 0);
                $modalPenjualan = $canViewModal ? (int) ($penjualan['total_modal'] ?? 0) : 0;
                $stmtPembelian = $pdo->prepare('SELECT COALESCE(SUM(beli), 0) AS total_pembelian FROM pembelian WHERE periode = :periode');
                $stmtPembelian->execute(['periode' => $periode]);
                $pembelian = $stmtPembelian->fetch(PDO::FETCH_ASSOC);
                $totalPembelian = (int) ($pembelian['total_pembelian'] ?? 0);
                $labaKotor = $totalPenjualan - $modalPenjualan;
                $labaBersih = $labaKotor - $totalPembelian;
                $rows[] = ['bulan' => $namaBulan, 'total_penjualan' => $totalPenjualan, 'total_modal' => $modalPenjualan, 'laba_kotor' => $labaKotor, 'total_pembelian' => $totalPembelian, 'laba_bersih' => $labaBersih];
            }
        } catch (Throwable) {}
        $html = app()->view()->render('laporan/export_rugi_laba_pdf', ['title' => 'Laporan Rugi Laba ' . $tahun, 'auth' => $auth, 'tahun' => $tahun, 'rows' => $rows, 'canViewModal' => $canViewModal, 'autoPrint' => $autoPrint]);
        return Response::html($html);
    }

    private function exportKeuanganPdf(Request $request, array $auth, bool $canViewModal, bool $autoPrint): Response
    {
        $params = $request->all();
        $filterTanggalDari = trim((string) ($params['tanggal_dari'] ?? ''));
        $filterTanggalSampai = trim((string) ($params['tanggal_sampai'] ?? ''));
        $filterTipeArus = trim((string) ($params['tipe_arus'] ?? ''));
        $filterAkun = (int) ($params['akun_keuangan_id'] ?? 0);
        $rows = [];
        try {
            $pdo = Database::connection();
            $where = ' WHERE k.deleted_at IS NULL';
            $bindings = [];
            if ($filterTanggalDari !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTanggalDari)) { $where .= ' AND k.tanggal >= :tanggal_dari'; $bindings['tanggal_dari'] = $filterTanggalDari; }
            if ($filterTanggalSampai !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterTanggalSampai)) { $where .= ' AND k.tanggal <= :tanggal_sampai'; $bindings['tanggal_sampai'] = $filterTanggalSampai; }
            if ($filterTipeArus !== '') { $where .= ' AND k.tipe_arus = :tipe_arus'; $bindings['tipe_arus'] = $filterTipeArus; }
            if ($filterAkun > 0) { $where .= ' AND k.akun_keuangan_id = :akun_keuangan_id'; $bindings['akun_keuangan_id'] = $filterAkun; }
            $sql = 'SELECT k.tanggal, k.no_ref, ak.kode_akun, ak.nama_akun, k.tipe_arus, k.nominal, k.metode_pembayaran, k.deskripsi, k.status FROM keuangan k LEFT JOIN akun_keuangan ak ON ak.id = k.akun_keuangan_id' . $where . ' ORDER BY k.tanggal ASC, k.id ASC';
            $stmt = $pdo->prepare($sql);
            foreach ($bindings as $k => $v) { $stmt->bindValue(':' . $k, $v); }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) $rows = [];
        } catch (Throwable) {}
        $html = app()->view()->render('laporan/export_keuangan_pdf', ['title' => 'Laporan Keuangan', 'auth' => $auth, 'rows' => $rows, 'filterTanggalDari' => $filterTanggalDari, 'filterTanggalSampai' => $filterTanggalSampai, 'autoPrint' => $autoPrint]);
        return Response::html($html);
    }
}
