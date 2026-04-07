<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use App\Services\KeuanganService;
use PDO;
use RuntimeException;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class KeuanganController
{
    public function datatable(Request $request): Response
    {
        $tab = strtolower(trim((string) $request->input('tab', 'keuangan')));
        if (!in_array($tab, ['akun', 'keuangan'], true)) {
            $tab = 'keuangan';
        }

        try {
            $pdo = Database::connection();
            $params = $request->all();
            $draw = max(0, (int) ($params['draw'] ?? 0));
            $start = max(0, (int) ($params['start'] ?? 0));
            $length = (int) ($params['length'] ?? 10);
            if ($length < 1) {
                $length = 10;
            }
            if ($length > 100) {
                $length = 100;
            }
            $search = trim((string) (($params['search']['value'] ?? '') ?: ''));

            if ($tab === 'akun') {
                $orderMap = [
                    0 => 'id',
                    1 => 'kode_akun',
                    2 => 'nama_akun',
                    3 => 'kategori',
                    4 => 'tipe_arus',
                    5 => 'status',
                ];
                $orderIndex = (int) ($params['order'][0]['column'] ?? 0);
                $orderColumn = $orderMap[$orderIndex] ?? 'id';
                $orderDir = strtolower((string) ($params['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

                $whereSql = ' WHERE deleted_at IS NULL';
                $bindings = [];
                if ($search !== '') {
                    $whereSql .= ' AND (kode_akun LIKE :search OR nama_akun LIKE :search OR kategori LIKE :search OR tipe_arus LIKE :search)';
                    $bindings['search'] = '%' . $search . '%';
                }

                $stmtTotal = $pdo->query('SELECT COUNT(*) FROM akun_keuangan WHERE deleted_at IS NULL');
                $recordsTotal = (int) $stmtTotal->fetchColumn();
                if ($search === '') {
                    $recordsFiltered = $recordsTotal;
                } else {
                    $stmtFiltered = $pdo->prepare('SELECT COUNT(*) FROM akun_keuangan' . $whereSql);
                    foreach ($bindings as $key => $value) {
                        $stmtFiltered->bindValue(':' . $key, $value);
                    }
                    $stmtFiltered->execute();
                    $recordsFiltered = (int) $stmtFiltered->fetchColumn();
                }

                $sql = 'SELECT id, kode_akun, nama_akun, kategori, tipe_arus, is_kas, is_modal, status FROM akun_keuangan' . $whereSql . ' ORDER BY `' . $orderColumn . '` ' . $orderDir . ' LIMIT :limit OFFSET :offset';
                $stmt = $pdo->prepare($sql);
                foreach ($bindings as $key => $value) {
                    $stmt->bindValue(':' . $key, $value);
                }
                $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!is_array($rows)) {
                    $rows = [];
                }
                foreach ($rows as &$row) {
                    $row['status_text'] = (int) ($row['status'] ?? 0) === 1 ? 'Aktif' : 'Nonaktif';
                    $row['kas_text'] = (int) ($row['is_kas'] ?? 0) === 1 ? 'Ya' : 'Tidak';
                    $row['modal_text'] = (int) ($row['is_modal'] ?? 0) === 1 ? 'Ya' : 'Tidak';
                }
                unset($row);

                return Response::json([
                    'draw' => $draw,
                    'recordsTotal' => $recordsTotal,
                    'recordsFiltered' => $recordsFiltered,
                    'data' => $rows,
                ]);
            }

            $orderMap = [
                0 => 'k.id',
                1 => 'k.tanggal',
                2 => 'k.no_ref',
                3 => 'a.kode_akun',
                4 => 'k.tipe_arus',
                5 => 'k.nominal',
            ];
            $orderIndex = (int) ($params['order'][0]['column'] ?? 0);
            $orderColumn = $orderMap[$orderIndex] ?? 'k.id';
            $orderDir = strtolower((string) ($params['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

            $whereSql = ' WHERE k.deleted_at IS NULL';
            $bindings = [];
            if ($search !== '') {
                $whereSql .= ' AND (k.no_ref LIKE :search OR a.kode_akun LIKE :search OR a.nama_akun LIKE :search OR k.deskripsi LIKE :search)';
                $bindings['search'] = '%' . $search . '%';
            }

            $stmtTotal = $pdo->query('SELECT COUNT(*) FROM keuangan WHERE deleted_at IS NULL');
            $recordsTotal = (int) $stmtTotal->fetchColumn();
            if ($search === '') {
                $recordsFiltered = $recordsTotal;
            } else {
                $stmtFiltered = $pdo->prepare('SELECT COUNT(*) FROM keuangan k LEFT JOIN akun_keuangan a ON a.id = k.akun_keuangan_id' . $whereSql);
                foreach ($bindings as $key => $value) {
                    $stmtFiltered->bindValue(':' . $key, $value);
                }
                $stmtFiltered->execute();
                $recordsFiltered = (int) $stmtFiltered->fetchColumn();
            }

            $sql = 'SELECT k.id, k.tanggal, k.no_ref, k.akun_keuangan_id, k.jenis, k.tipe_arus, k.nominal, k.metode_pembayaran, k.deskripsi, a.kode_akun, a.nama_akun
                FROM keuangan k
                LEFT JOIN akun_keuangan a ON a.id = k.akun_keuangan_id'
                . $whereSql
                . ' ORDER BY ' . $orderColumn . ' ' . $orderDir . ' LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($bindings as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                $rows = [];
            }

            return Response::json([
                'draw' => $draw,
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ]);
        } catch (Throwable) {
            return Response::json([
                'draw' => max(0, (int) ($request->all()['draw'] ?? 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data keuangan.',
            ], 500);
        }
    }

    public function input(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        if ($request->method() === 'POST') {
            return $this->handleInputPost($request, $auth);
        }

        $title = 'Input Keuangan';
        $activeTab = strtolower(trim((string) $request->input('tab', 'keuangan')));
        if (!in_array($activeTab, ['akun', 'keuangan'], true)) {
            $activeTab = 'keuangan';
        }

        $payload = [
            'akunOptions' => [],
            'activeTab' => $activeTab,
            'today' => date('Y-m-d'),
        ];

        try {
            $pdo = Database::connection();
            $stmtAkun = $pdo->query('SELECT id, kode_akun, nama_akun FROM akun_keuangan WHERE deleted_at IS NULL AND status = 1 ORDER BY kode_akun ASC, id ASC');
            $akunRows = $stmtAkun->fetchAll(PDO::FETCH_ASSOC);
            $payload['akunOptions'] = is_array($akunRows) ? $akunRows : [];
        } catch (Throwable) {
            toast_add('Gagal memuat data keuangan.', 'error');
        }

        $html = app()->view()->render('keuangan/input', [
            'title' => $title,
            'auth' => $auth,
            'activeMenu' => 'keuangan-input',
            ...$payload,
        ]);

        return Response::html($html);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function handleInputPost(Request $request, array $auth): Response
    {
        $action = strtolower(trim((string) $request->input('_action', '')));
        $tab = strtolower(trim((string) $request->input('_tab', 'keuangan')));
        if (!in_array($tab, ['akun', 'keuangan'], true)) {
            $tab = 'keuangan';
        }

        try {
            $pdo = Database::connection();
            if ($action === 'create_akun') {
                $this->createAkun($pdo, $request, $auth);
                toast_add('Akun keuangan berhasil ditambahkan.', 'success');
                return Response::redirect('/keuangan/input?tab=akun');
            }
            if ($action === 'update_akun') {
                $this->updateAkun($pdo, $request, $auth);
                toast_add('Akun keuangan berhasil diperbarui.', 'success');
                return Response::redirect('/keuangan/input?tab=akun');
            }
            if ($action === 'delete_akun') {
                $this->deleteAkun($pdo, $request, $auth);
                toast_add('Akun keuangan berhasil dihapus.', 'success');
                return Response::redirect('/keuangan/input?tab=akun');
            }
            if ($action === 'create_keuangan') {
                $this->createMutasi($pdo, $request, $auth);
                toast_add('Input keuangan berhasil disimpan.', 'success');
                return Response::redirect('/keuangan/input?tab=keuangan');
            }
            if ($action === 'update_keuangan') {
                $this->updateMutasi($pdo, $request, $auth);
                toast_add('Input keuangan berhasil diperbarui.', 'success');
                return Response::redirect('/keuangan/input?tab=keuangan');
            }
            if ($action === 'delete_keuangan') {
                $this->deleteMutasi($pdo, $request, $auth);
                toast_add('Input keuangan berhasil dihapus.', 'success');
                return Response::redirect('/keuangan/input?tab=keuangan');
            }

            toast_add('Aksi form tidak valid.', 'error');
        } catch (Throwable $e) {
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Proses input keuangan gagal.', 'error');
        }

        return Response::redirect('/keuangan/input?tab=' . $tab);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function createAkun(PDO $pdo, Request $request, array $auth): void
    {
        $kodeAkun = strtoupper(trim((string) $request->input('kode_akun', '')));
        $namaAkun = trim((string) $request->input('nama_akun', ''));
        $kategori = strtolower(trim((string) $request->input('kategori', 'lainnya')));
        $tipeArus = strtolower(trim((string) $request->input('tipe_arus', 'netral')));
        $deskripsi = trim((string) $request->input('deskripsi', ''));
        $isKas = (int) $request->input('is_kas', '0') === 1 ? 1 : 0;
        $isModal = (int) $request->input('is_modal', '0') === 1 ? 1 : 0;

        if ($kodeAkun === '' || $namaAkun === '') {
            throw new RuntimeException('Kode akun dan nama akun wajib diisi.');
        }
        if (!preg_match('/^[A-Z0-9\.\-]+$/', $kodeAkun)) {
            throw new RuntimeException('Format kode akun tidak valid.');
        }
        if (!in_array($kategori, ['aset', 'liabilitas', 'ekuitas', 'pendapatan', 'beban', 'lainnya'], true)) {
            $kategori = 'lainnya';
        }
        if (!in_array($tipeArus, ['pemasukan', 'pengeluaran', 'netral'], true)) {
            $tipeArus = 'netral';
        }

        $stmtExists = $pdo->prepare('SELECT id FROM akun_keuangan WHERE deleted_at IS NULL AND kode_akun = :kode LIMIT 1');
        $stmtExists->execute(['kode' => $kodeAkun]);
        $exists = $stmtExists->fetch(PDO::FETCH_ASSOC);
        if (is_array($exists)) {
            throw new RuntimeException('Kode akun sudah dipakai. Gunakan kode lain.');
        }

        $memberId = (int) ($auth['id'] ?? 0);
        $stmt = $pdo->prepare('INSERT INTO akun_keuangan (name, kode_akun, nama_akun, kategori, tipe_arus, is_kas, is_modal, deskripsi, status, created_by, updated_by, created_at, updated_at) VALUES (:name, :kode_akun, :nama_akun, :kategori, :tipe_arus, :is_kas, :is_modal, :deskripsi, 1, :created_by, :updated_by, NOW(), NOW())');
        $stmt->execute([
            'name' => $namaAkun,
            'kode_akun' => $kodeAkun,
            'nama_akun' => $namaAkun,
            'kategori' => $kategori,
            'tipe_arus' => $tipeArus,
            'is_kas' => $isKas,
            'is_modal' => $isModal,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
            'created_by' => $memberId > 0 ? $memberId : null,
            'updated_by' => $memberId > 0 ? $memberId : null,
        ]);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function createMutasi(PDO $pdo, Request $request, array $auth): void
    {
        $tanggal = trim((string) $request->input('tanggal', ''));
        $noRef = trim((string) $request->input('no_ref', ''));
        $tipeArus = strtolower(trim((string) $request->input('tipe_arus', 'pengeluaran')));
        $nominal = max(0, (int) $request->input('nominal', '0'));
        $akunId = max(0, (int) $request->input('akun_keuangan_id', '0'));
        $metode = trim((string) $request->input('metode_pembayaran', ''));
        $deskripsi = trim((string) $request->input('deskripsi', ''));

        if ($nominal <= 0) {
            throw new RuntimeException('Nominal wajib lebih dari 0.');
        }
        if ($tanggal === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = date('Y-m-d');
        }
        if (!in_array($tipeArus, ['pemasukan', 'pengeluaran', 'netral'], true)) {
            $tipeArus = 'netral';
        }
        if ($akunId <= 0) {
            throw new RuntimeException('Akun keuangan wajib dipilih.');
        }

        $memberId = (int) ($auth['id'] ?? 0);
        (new KeuanganService($pdo))->catatMutasi([
            'tanggal' => $tanggal,
            'no_ref' => $noRef !== '' ? $noRef : null,
            'akun_keuangan_id' => $akunId,
            'jenis' => $tipeArus === 'pengeluaran' ? 'kredit' : 'debit',
            'tipe_arus' => $tipeArus,
            'nominal' => $nominal,
            'metode_pembayaran' => $metode !== '' ? $metode : null,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
            'status' => 'posted',
            'created_by' => $memberId > 0 ? $memberId : null,
        ]);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function updateAkun(PDO $pdo, Request $request, array $auth): void
    {
        $id = max(0, (int) $request->input('id', '0'));
        if ($id <= 0) {
            throw new RuntimeException('ID akun tidak valid.');
        }

        $kodeAkun = strtoupper(trim((string) $request->input('kode_akun', '')));
        $namaAkun = trim((string) $request->input('nama_akun', ''));
        $kategori = strtolower(trim((string) $request->input('kategori', 'lainnya')));
        $tipeArus = strtolower(trim((string) $request->input('tipe_arus', 'netral')));
        $deskripsi = trim((string) $request->input('deskripsi', ''));
        $isKas = (int) $request->input('is_kas', '0') === 1 ? 1 : 0;
        $isModal = (int) $request->input('is_modal', '0') === 1 ? 1 : 0;
        $status = (int) $request->input('status', '1') === 1 ? 1 : 0;

        if ($kodeAkun === '' || $namaAkun === '') {
            throw new RuntimeException('Kode akun dan nama akun wajib diisi.');
        }

        $stmtExists = $pdo->prepare('SELECT id FROM akun_keuangan WHERE deleted_at IS NULL AND kode_akun = :kode AND id <> :id LIMIT 1');
        $stmtExists->execute(['kode' => $kodeAkun, 'id' => $id]);
        if (is_array($stmtExists->fetch(PDO::FETCH_ASSOC))) {
            throw new RuntimeException('Kode akun sudah dipakai akun lain.');
        }

        $memberId = (int) ($auth['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE akun_keuangan SET name = :name, kode_akun = :kode_akun, nama_akun = :nama_akun, kategori = :kategori, tipe_arus = :tipe_arus, is_kas = :is_kas, is_modal = :is_modal, deskripsi = :deskripsi, status = :status, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'name' => $namaAkun,
            'kode_akun' => $kodeAkun,
            'nama_akun' => $namaAkun,
            'kategori' => $kategori,
            'tipe_arus' => $tipeArus,
            'is_kas' => $isKas,
            'is_modal' => $isModal,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
            'status' => $status,
            'updated_by' => $memberId > 0 ? $memberId : null,
        ]);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function deleteAkun(PDO $pdo, Request $request, array $auth): void
    {
        $id = max(0, (int) $request->input('id', '0'));
        if ($id <= 0) {
            throw new RuntimeException('ID akun tidak valid.');
        }
        $memberId = (int) ($auth['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE akun_keuangan SET deleted_at = NOW(), updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id, 'updated_by' => $memberId > 0 ? $memberId : null]);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function updateMutasi(PDO $pdo, Request $request, array $auth): void
    {
        $id = max(0, (int) $request->input('id', '0'));
        if ($id <= 0) {
            throw new RuntimeException('ID mutasi tidak valid.');
        }
        $tanggal = trim((string) $request->input('tanggal', ''));
        $noRef = trim((string) $request->input('no_ref', ''));
        $tipeArus = strtolower(trim((string) $request->input('tipe_arus', 'pengeluaran')));
        $nominal = max(0, (int) $request->input('nominal', '0'));
        $akunId = max(0, (int) $request->input('akun_keuangan_id', '0'));
        $metode = trim((string) $request->input('metode_pembayaran', ''));
        $deskripsi = trim((string) $request->input('deskripsi', ''));
        if ($nominal <= 0 || $akunId <= 0) {
            throw new RuntimeException('Akun dan nominal wajib valid.');
        }
        if ($tanggal === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = date('Y-m-d');
        }
        if (!in_array($tipeArus, ['pemasukan', 'pengeluaran', 'netral'], true)) {
            $tipeArus = 'netral';
        }
        $jenis = $tipeArus === 'pengeluaran' ? 'kredit' : 'debit';
        $memberId = (int) ($auth['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE keuangan SET tanggal = :tanggal, no_ref = :no_ref, akun_keuangan_id = :akun_keuangan_id, akun_keunagan_id = :akun_keunagan_id, jenis = :jenis, tipe_arus = :tipe_arus, nominal = :nominal, harga_operasional = :harga_operasional, metode_pembayaran = :metode_pembayaran, deskripsi = :deskripsi, ket_operasional = :ket_operasional, nama_operasional = :nama_operasional, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'tanggal' => $tanggal,
            'no_ref' => $noRef !== '' ? $noRef : null,
            'akun_keuangan_id' => $akunId,
            'akun_keunagan_id' => $akunId,
            'jenis' => $jenis,
            'tipe_arus' => $tipeArus,
            'nominal' => $nominal,
            'harga_operasional' => $nominal,
            'metode_pembayaran' => $metode !== '' ? $metode : null,
            'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
            'ket_operasional' => $deskripsi !== '' ? $deskripsi : null,
            'nama_operasional' => $deskripsi !== '' ? $deskripsi : null,
            'updated_by' => $memberId > 0 ? $memberId : null,
        ]);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function deleteMutasi(PDO $pdo, Request $request, array $auth): void
    {
        $id = max(0, (int) $request->input('id', '0'));
        if ($id <= 0) {
            throw new RuntimeException('ID mutasi tidak valid.');
        }
        $memberId = (int) ($auth['id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE keuangan SET deleted_at = NOW(), updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id, 'updated_by' => $memberId > 0 ? $memberId : null]);
    }
}
