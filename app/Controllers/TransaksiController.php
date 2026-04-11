<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use App\Services\KeuanganService;
use App\Services\SupplierDebtService;
use PDO;
use RuntimeException;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class TransaksiController
{
    /**
     * @var array<int, string>
     */
    private const PAYMENT_METHODS = ['Cash', 'E-wallet', 'QRIS', 'Transfer Bank'];
    /**
     * @var array<int, string>
     */
    private const PURCHASE_PAYMENT_METHODS = ['Cash', 'Termin'];

    public function index(Request $request): Response
    {
        return Response::redirect('/transaksi/penjualan');
    }

    public function penjualan(Request $request): Response
    {
        $auth = $this->auth();
        if (!$this->canAccessPenjualan($auth)) {
            toast_add('Anda tidak punya akses ke fitur penjualan.', 'error');
            return Response::redirect('/dashboard');
        }

        $title = menu_generator_title_by_route('transaksi', 'Penjualan');
        $pageTitle = $title === 'Transaksi' ? 'Penjualan' : ($title ?? 'Penjualan');
        $payload = [
            'cartItems' => [],
            'barangOptions' => [],
            'jasaOptions' => [],
            'frequentBarangOptions' => [],
            'frequentJasaOptions' => [],
            'pelangganOptions' => [],
            'holdRows' => [],
            'summary' => ['qty' => 0, 'subtotal' => 0, 'diskon' => 0, 'grand_total' => 0],
            'activeHoldId' => (int) ($_SESSION['penjualan_active_hold_id'] ?? 0),
            'lastReceipt' => [],
        ];

        if (is_array($_SESSION['penjualan_last_receipt'] ?? null)) {
            $payload['lastReceipt'] = $_SESSION['penjualan_last_receipt'];
            unset($_SESSION['penjualan_last_receipt']);
        }

        try {
            $pdo = Database::connection();
            $memberId = (int) ($auth['id'] ?? 0);
            $payload['cartItems'] = $this->cartItems($pdo, $memberId);
            $payload['summary'] = $this->cartSummary($payload['cartItems']);
            $payload['barangOptions'] = $this->listBarang($pdo);
            $payload['jasaOptions'] = $this->listJasa($pdo);
            $payload['frequentBarangOptions'] = $this->frequentBarang($pdo);
            $payload['frequentJasaOptions'] = $this->frequentJasa($pdo);
            $payload['pelangganOptions'] = $this->listPelanggan($pdo);
            $holdTable = $this->resolveHoldTable($pdo);
            if ($holdTable !== null) {
                $stmtHold = $pdo->prepare('SELECT id, hold_code, id_pelanggan, payment_method, catatan, created_at FROM `' . $holdTable . '` WHERE (id_member = :id_member OR id_member IS NULL OR id_member = 0) AND status = :status ORDER BY id DESC LIMIT 20');
                $stmtHold->execute(['id_member' => $memberId, 'status' => 'hold']);
                $rows = $stmtHold->fetchAll(PDO::FETCH_ASSOC);
                $payload['holdRows'] = is_array($rows) ? $rows : [];
            }
        } catch (Throwable) {
            toast_add('Gagal memuat data penjualan.', 'error');
        }

        $html = app()->view()->render('transaksi/penjualan', [
            'title' => $pageTitle,
            'auth' => $auth,
            'activeMenu' => 'transaksi-penjualan',
            'paymentMethods' => self::PAYMENT_METHODS,
            ...$payload,
        ]);

        return Response::html($html);
    }

    public function addCartItem(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $itemType = strtolower(trim((string) $request->input('item_type', 'barang')));
        $itemId = (int) $request->input('item_id', '0');
        $qty = max(1, (int) $request->input('qty', '1'));
        if (!in_array($itemType, ['barang', 'jasa'], true) || $itemId <= 0) {
            toast_add('Item tidak valid.', 'error');
            return Response::redirect('/transaksi/penjualan');
        }

        try {
            $pdo = Database::connection();
            $pdo->beginTransaction();

            $item = $itemType === 'barang' ? $this->findBarang($pdo, $itemId) : $this->findJasa($pdo, $itemId);
            if ($item === null) {
                throw new RuntimeException('Item tidak ditemukan.');
            }

            // Cek diskon aktif untuk barang (jangan sampai blokir add-to-cart jika skema diskon berbeda)
            $diskonAktif = 0;
            if ($itemType === 'barang') {
                try {
                    $barangCode = (string) ($item['code'] ?? '');
                    $diskonAktif = $this->activeDiskonByBarangRef($pdo, $barangCode, $itemId);
                } catch (Throwable) {
                    $diskonAktif = 0;
                }
            }

            $stmtExisting = $pdo->prepare('SELECT id, jumlah FROM keranjang WHERE id_member = :id_member AND item_type = :item_type AND item_ref_id = :item_ref_id LIMIT 1');
            $stmtExisting->execute(['id_member' => $memberId, 'item_type' => $itemType, 'item_ref_id' => $itemId]);
            $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);
            $newQty = max(0, (int) ($existing['jumlah'] ?? 0)) + $qty;
            if ($itemType === 'barang' && $newQty > (int) ($item['stok'] ?? 0)) {
                throw new RuntimeException('Stok barang tidak mencukupi.');
            }

            if (is_array($existing)) {
                $update = $pdo->prepare('UPDATE keranjang SET jumlah = :jumlah, beli = :beli, jual = :jual, diskon = :diskon, updated_at = NOW() WHERE id = :id');
                $update->execute([
                    'jumlah' => (string) $newQty,
                    'beli' => (int) ($item['beli'] ?? 0),
                    'jual' => (int) ($item['jual'] ?? 0),
                    'diskon' => $diskonAktif,
                    'id' => (int) ($existing['id'] ?? 0),
                ]);
            } else {
                $insert = $pdo->prepare('INSERT INTO keranjang (id_barang, item_type, item_ref_id, id_member, nama_barang, diskon, jumlah, beli, jual, tanggal_input, created_at, updated_at) VALUES (:id_barang, :item_type, :item_ref_id, :id_member, :nama_barang, :diskon, :jumlah, :beli, :jual, :tanggal_input, NOW(), NOW())');
                $insert->execute([
                    'id_barang' => (string) ($item['code'] ?? ''),
                    'item_type' => $itemType,
                    'item_ref_id' => $itemId,
                    'id_member' => $memberId,
                    'nama_barang' => (string) ($item['name'] ?? ''),
                    'jumlah' => (string) $newQty,
                    'beli' => (int) ($item['beli'] ?? 0),
                    'jual' => (int) ($item['jual'] ?? 0),
                    'diskon' => $diskonAktif,
                    'tanggal_input' => date('Y-m-d'),
                ]);
            }

            $pdo->commit();
            toast_add('Item berhasil masuk keranjang.', 'success');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Gagal menambah keranjang.', 'error');
        }

        return Response::redirect('/transaksi/penjualan');
    }

    public function updateCartItem(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $cartId = (int) $request->input('cart_id', '0');
        $qty = max(0, (int) $request->input('qty', '0'));
        if ($cartId <= 0) {
            toast_add('Item keranjang tidak valid.', 'error');
            return Response::redirect('/transaksi/penjualan');
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id, item_type, item_ref_id FROM keranjang WHERE id = :id AND id_member = :id_member LIMIT 1');
            $stmt->execute(['id' => $cartId, 'id_member' => $memberId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('Item keranjang tidak ditemukan.');
            }

            if ($qty <= 0) {
                $del = $pdo->prepare('DELETE FROM keranjang WHERE id = :id AND id_member = :id_member');
                $del->execute(['id' => $cartId, 'id_member' => $memberId]);
                toast_add('Item keranjang dihapus.', 'success');
                return Response::redirect('/transaksi/penjualan');
            }

            if ((string) ($row['item_type'] ?? 'barang') === 'barang') {
                $barang = $this->findBarang($pdo, (int) ($row['item_ref_id'] ?? 0));
                if ($barang === null || $qty > (int) ($barang['stok'] ?? 0)) {
                    throw new RuntimeException('Stok barang tidak mencukupi.');
                }
            }

            $upd = $pdo->prepare('UPDATE keranjang SET jumlah = :jumlah, updated_at = NOW() WHERE id = :id AND id_member = :id_member');
            $upd->execute(['jumlah' => (string) $qty, 'id' => $cartId, 'id_member' => $memberId]);
            toast_add('Jumlah keranjang diperbarui.', 'success');
        } catch (Throwable $e) {
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Gagal update keranjang.', 'error');
        }

        return Response::redirect('/transaksi/penjualan');
    }

    public function removeCartItem(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $cartId = (int) $request->input('cart_id', '0');
        if ($cartId <= 0) {
            toast_add('Item keranjang tidak valid.', 'error');
            return Response::redirect('/transaksi/penjualan');
        }

        try {
            $pdo = Database::connection();
            $del = $pdo->prepare('DELETE FROM keranjang WHERE id = :id AND id_member = :id_member');
            $del->execute(['id' => $cartId, 'id_member' => $memberId]);
            toast_add('Item keranjang dihapus.', 'success');
        } catch (Throwable) {
            toast_add('Gagal hapus item keranjang.', 'error');
        }

        return Response::redirect('/transaksi/penjualan');
    }

    public function clearCart(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        try {
            $pdo = Database::connection();
            $del = $pdo->prepare('DELETE FROM keranjang WHERE id_member = :id_member');
            $del->execute(['id_member' => $memberId]);
            unset($_SESSION['penjualan_active_hold_id']);
            toast_add('Keranjang dikosongkan.', 'success');
        } catch (Throwable) {
            toast_add('Gagal mengosongkan keranjang.', 'error');
        }

        return Response::redirect('/transaksi/penjualan');
    }

    public function holdCart(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $idPelanggan = (int) $request->input('id_pelanggan', '0');
        $paymentMethod = trim((string) $request->input('payment_method', ''));
        $catatan = trim((string) $request->input('catatan', ''));
        if ($paymentMethod !== '' && !in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            $paymentMethod = '';
        }

        try {
            $pdo = Database::connection();
            $holdTable = $this->resolveHoldTable($pdo);
            $holdItemsTable = $this->resolveHoldItemsTable($pdo);
            if ($holdTable === null || $holdItemsTable === null) {
                throw new RuntimeException('Tabel hold belum tersedia. Jalankan migration terbaru.');
            }
            $items = $this->cartItems($pdo, $memberId);
            if ($items === []) {
                throw new RuntimeException('Keranjang kosong, tidak bisa di-hold.');
            }

            $pdo->beginTransaction();
            $holdCode = 'HLD-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            $insHold = $pdo->prepare('INSERT INTO `' . $holdTable . '` (hold_code, id_member, id_pelanggan, payment_method, catatan, status, created_at, updated_at) VALUES (:hold_code, :id_member, :id_pelanggan, :payment_method, :catatan, :status, NOW(), NOW())');
            $insHold->execute([
                'hold_code' => $holdCode,
                'id_member' => $memberId,
                'id_pelanggan' => $idPelanggan > 0 ? $idPelanggan : null,
                'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
                'catatan' => $catatan !== '' ? $catatan : null,
                'status' => 'hold',
            ]);
            $holdId = (int) $pdo->lastInsertId();

            $insItem = $pdo->prepare('INSERT INTO `' . $holdItemsTable . '` (hold_id, item_type, item_ref_id, item_code, item_name, qty, beli, jual, diskon, total, created_at) VALUES (:hold_id, :item_type, :item_ref_id, :item_code, :item_name, :qty, :beli, :jual, :diskon, :total, NOW())');
            foreach ($items as $item) {
                $qty = max(1, (int) ($item['jumlah'] ?? 1));
                $jual = max(0, (int) ($item['jual'] ?? 0));
                $diskon = max(0, (int) ($item['diskon'] ?? 0));
                $insItem->execute([
                    'hold_id' => $holdId,
                    'item_type' => (string) ($item['item_type'] ?? 'barang'),
                    'item_ref_id' => (int) ($item['item_ref_id'] ?? 0),
                    'item_code' => (string) ($item['id_barang'] ?? ''),
                    'item_name' => (string) ($item['nama_barang'] ?? ''),
                    'qty' => $qty,
                    'beli' => (int) ($item['beli'] ?? 0),
                    'jual' => $jual,
                    'diskon' => $diskon,
                    'total' => max(0, ($jual - $diskon) * $qty),
                ]);
            }

            $del = $pdo->prepare('DELETE FROM keranjang WHERE id_member = :id_member');
            $del->execute(['id_member' => $memberId]);
            unset($_SESSION['penjualan_active_hold_id']);
            $pdo->commit();

            toast_add('Transaksi ditahan: ' . $holdCode . '.', 'success');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Gagal hold transaksi.', 'error');
        }

        return Response::redirect('/transaksi/penjualan');
    }

    public function resumeHold(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $holdId = (int) $request->input('hold_id', '0');
        if ($holdId <= 0) {
            toast_add('Data hold tidak valid.', 'error');
            return Response::redirect('/transaksi/penjualan');
        }

        try {
            $pdo = Database::connection();
            $holdTable = $this->resolveHoldTable($pdo);
            $holdItemsTable = $this->resolveHoldItemsTable($pdo);
            if ($holdTable === null || $holdItemsTable === null) {
                throw new RuntimeException('Tabel hold belum tersedia. Jalankan migration terbaru.');
            }

            $pdo->beginTransaction();
            $stmtHold = $pdo->prepare('SELECT id, hold_code FROM `' . $holdTable . '` WHERE id = :id AND (id_member = :id_member OR id_member IS NULL OR id_member = 0) AND status = :status LIMIT 1 FOR UPDATE');
            $stmtHold->execute(['id' => $holdId, 'id_member' => $memberId, 'status' => 'hold']);
            $hold = $stmtHold->fetch(PDO::FETCH_ASSOC);
            if (!is_array($hold)) {
                throw new RuntimeException('Data hold tidak ditemukan.');
            }

            $stmtItems = $pdo->prepare('SELECT * FROM `' . $holdItemsTable . '` WHERE hold_id = :hold_id ORDER BY id ASC');
            $stmtItems->execute(['hold_id' => $holdId]);
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($items) || $items === []) {
                throw new RuntimeException('Data item hold kosong.');
            }

            $pdo->prepare('DELETE FROM keranjang WHERE id_member = :id_member')->execute(['id_member' => $memberId]);
            $ins = $pdo->prepare('INSERT INTO keranjang (id_barang, item_type, item_ref_id, id_member, nama_barang, diskon, jumlah, beli, jual, tanggal_input, created_at, updated_at) VALUES (:id_barang, :item_type, :item_ref_id, :id_member, :nama_barang, :diskon, :jumlah, :beli, :jual, :tanggal_input, NOW(), NOW())');
            foreach ($items as $item) {
                $ins->execute([
                    'id_barang' => (string) ($item['item_code'] ?? ''),
                    'item_type' => (string) ($item['item_type'] ?? 'barang'),
                    'item_ref_id' => (int) ($item['item_ref_id'] ?? 0),
                    'id_member' => $memberId,
                    'nama_barang' => (string) ($item['item_name'] ?? ''),
                    'diskon' => max(0, (int) ($item['diskon'] ?? 0)),
                    'jumlah' => (string) max(1, (int) ($item['qty'] ?? 1)),
                    'beli' => max(0, (int) ($item['beli'] ?? 0)),
                    'jual' => max(0, (int) ($item['jual'] ?? 0)),
                    'tanggal_input' => date('Y-m-d'),
                ]);
            }
            $_SESSION['penjualan_active_hold_id'] = $holdId;
            $pdo->commit();

            toast_add('Hold ' . (string) ($hold['hold_code'] ?? ('#' . $holdId)) . ' dilanjutkan.', 'success');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Gagal resume hold.', 'error');
        }

        return Response::redirect('/transaksi/penjualan');
    }

    public function deleteHold(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $holdId = (int) $request->input('hold_id', '0');
        if ($holdId <= 0) {
            toast_add('Data hold tidak valid.', 'error');
            return Response::redirect('/transaksi/penjualan');
        }

        try {
            $pdo = Database::connection();
            $holdTable = $this->resolveHoldTable($pdo);
            $holdItemsTable = $this->resolveHoldItemsTable($pdo);
            if ($holdTable === null || $holdItemsTable === null) {
                throw new RuntimeException('Tabel hold belum tersedia. Jalankan migration terbaru.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT id FROM `' . $holdTable . '` WHERE id = :id AND (id_member = :id_member OR id_member IS NULL OR id_member = 0) LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $holdId, 'id_member' => $memberId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('Data hold tidak ditemukan.');
            }

            $pdo->prepare('DELETE FROM `' . $holdItemsTable . '` WHERE hold_id = :hold_id')->execute(['hold_id' => $holdId]);
            $pdo->prepare('DELETE FROM `' . $holdTable . '` WHERE id = :id')->execute(['id' => $holdId]);
            $activeHoldId = (int) ($_SESSION['penjualan_active_hold_id'] ?? 0);
            if ($activeHoldId === $holdId) {
                unset($_SESSION['penjualan_active_hold_id']);
            }
            $pdo->commit();
            toast_add('Data hold berhasil dihapus.', 'success');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Gagal menghapus hold.', 'error');
        }

        return Response::redirect('/transaksi/penjualan');
    }

    public function quickPelanggan(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return $guard;
        }

        $nama = trim((string) $request->input('nama_pelanggan', ''));
        $telepon = trim((string) $request->input('telepon_pelanggan', ''));
        $alamat = trim((string) $request->input('alamat_pelanggan', ''));
        $email = trim((string) $request->input('email_pelanggan', ''));
        if ($nama === '') {
            toast_add('Nama pelanggan wajib diisi.', 'error');
            return Response::redirect('/transaksi/penjualan');
        }

        try {
            $pdo = Database::connection();
            $nextId = ((int) $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 FROM pelanggan')->fetchColumn());
            $kode = 'PL' . str_pad((string) max(1, $nextId), 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare('INSERT INTO pelanggan (kode_pelanggan, nama_pelanggan, alamat_pelanggan, telepon_pelanggan, email_pelanggan, created_at) VALUES (:kode_pelanggan, :nama_pelanggan, :alamat_pelanggan, :telepon_pelanggan, :email_pelanggan, NOW())');
            $stmt->execute([
                'kode_pelanggan' => $kode,
                'nama_pelanggan' => $nama,
                'alamat_pelanggan' => $alamat !== '' ? $alamat : null,
                'telepon_pelanggan' => $telepon !== '' ? $telepon : null,
                'email_pelanggan' => $email !== '' ? $email : null,
            ]);
            toast_add('Pelanggan baru berhasil ditambahkan.', 'success');
        } catch (Throwable) {
            toast_add('Gagal menambah pelanggan baru.', 'error');
        }

        return Response::redirect('/transaksi/penjualan');
    }

    public function checkout(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $idPelanggan = (int) $request->input('id_pelanggan', '0');
        $paymentMethod = trim((string) $request->input('payment_method', ''));
        $bayar = max(0, (int) $request->input('bayar', '0'));
        $activeHoldId = max(0, (int) $request->input('active_hold_id', '0'));
        $keterangan = trim((string) $request->input('keterangan', ''));
        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            toast_add('Metode pembayaran tidak valid.', 'error');
            return Response::redirect('/transaksi/penjualan');
        }

        try {
            $pdo = Database::connection();
            $items = $this->cartItems($pdo, $memberId);
            if ($items === []) {
                throw new RuntimeException('Keranjang kosong.');
            }

            $pdo->beginTransaction();
            $normalized = [];
            $totalQty = 0;
            $totalBeli = 0;
            $grandTotal = 0;

            foreach ($items as $item) {
                $itemType = (string) ($item['item_type'] ?? 'barang');
                $refId = (int) ($item['item_ref_id'] ?? 0);
                $qty = max(1, (int) ($item['jumlah'] ?? 1));
                $diskon = max(0, (int) ($item['diskon'] ?? 0));
                $jual = max(0, (int) ($item['jual'] ?? 0));
                $beli = max(0, (int) ($item['beli'] ?? 0));

                if ($itemType === 'barang') {
                    $stmtBarang = $pdo->prepare('SELECT id, stok, harga_beli, harga_jual FROM barang WHERE id = :id LIMIT 1 FOR UPDATE');
                    $stmtBarang->execute(['id' => $refId]);
                    $barang = $stmtBarang->fetch(PDO::FETCH_ASSOC);
                    if (!is_array($barang) || $qty > (int) ($barang['stok'] ?? 0)) {
                        throw new RuntimeException('Stok barang tidak mencukupi saat checkout.');
                    }
                    $jual = max(0, (int) ($barang['harga_jual'] ?? $jual));
                    $beli = max(0, (int) ($barang['harga_beli'] ?? $beli));
                    $barangKode = trim((string) ($item['id_barang'] ?? ''));
                    $diskonAktif = $this->activeDiskonByBarangRef($pdo, $barangKode, $refId);
                    if ($diskonAktif > 0) {
                        $diskon = $diskonAktif;
                    }
                    // Diskon tidak boleh melebihi harga jual
                    if ($diskon > $jual) {
                        $diskon = $jual;
                    }
                    $pdo->prepare('UPDATE barang SET stok = stok - :qty, updated_at = NOW() WHERE id = :id')->execute(['qty' => $qty, 'id' => $refId]);
                } else {
                    $jasa = $this->findJasa($pdo, $refId);
                    if ($jasa !== null) {
                        $jual = max(0, (int) ($jasa['jual'] ?? $jual));
                    }
                    $beli = 0;
                }

                $lineTotal = max(0, ($jual - $diskon) * $qty);
                $normalized[] = [
                    'item_type' => $itemType,
                    'item_ref_id' => $refId,
                    'code' => (string) ($item['id_barang'] ?? ''),
                    'name' => (string) ($item['nama_barang'] ?? ''),
                    'qty' => $qty,
                    'beli' => $beli,
                    'jual' => $jual,
                    'diskon' => $diskon,
                    'total' => $lineTotal,
                ];
                $totalQty += $qty;
                $totalBeli += ($beli * $qty);
                $grandTotal += $lineTotal;
            }

            if ($grandTotal <= 0) {
                throw new RuntimeException('Grand total transaksi harus lebih dari 0.');
            }
            if ($bayar <= 0) {
                throw new RuntimeException('Nominal bayar harus lebih dari 0.');
            }
            if ($bayar < $grandTotal) {
                throw new RuntimeException('Nominal bayar tidak boleh kurang dari grand total.');
            }

            $noTrx = $this->generateNoTrx($pdo);
            $statusBayar = 'Lunas';
            $tanggal = date('Y-m-d');
            $periode = date('Ym');
            $insHead = $pdo->prepare('INSERT INTO penjualan (no_trx, id_member, id_pelanggan, jumlah, beli, total, bayar, status_bayar, payment_method, keterangan, tanggal_input, created_at, updated_at, periode) VALUES (:no_trx, :id_member, :id_pelanggan, :jumlah, :beli, :total, :bayar, :status_bayar, :payment_method, :keterangan, :tanggal_input, NOW(), NOW(), :periode)');
            $insHead->execute([
                'no_trx' => $noTrx,
                'id_member' => $memberId,
                'id_pelanggan' => $idPelanggan > 0 ? $idPelanggan : 0,
                'jumlah' => $totalQty,
                'beli' => $totalBeli,
                'total' => $grandTotal,
                'bayar' => $bayar,
                'status_bayar' => $statusBayar,
                'payment_method' => $paymentMethod,
                'keterangan' => $keterangan !== '' ? $keterangan : null,
                'tanggal_input' => $tanggal,
                'periode' => $periode,
            ]);
            $penjualanId = (int) $pdo->lastInsertId();

            $insDet = $pdo->prepare('INSERT INTO penjualan_detail (no_trx, id_barang, item_type, idb, nama_barang, beli, jual, qty, diskon, total, status_bayar, tgl_input, periode, id_member, created_at, updated_at) VALUES (:no_trx, :id_barang, :item_type, :idb, :nama_barang, :beli, :jual, :qty, :diskon, :total, :status_bayar, :tgl_input, :periode, :id_member, NOW(), NOW())');
            foreach ($normalized as $item) {
                $insDet->execute([
                    'no_trx' => $noTrx,
                    'id_barang' => $item['item_type'] === 'barang' ? (int) $item['item_ref_id'] : 0,
                    'item_type' => $item['item_type'],
                    'idb' => $item['code'],
                    'nama_barang' => $item['name'],
                    'beli' => (int) $item['beli'],
                    'jual' => (int) $item['jual'],
                    'qty' => (int) $item['qty'],
                    'diskon' => (int) $item['diskon'],
                    'total' => (int) $item['total'],
                    'status_bayar' => $paymentMethod,
                    'tgl_input' => $tanggal,
                    'periode' => $periode,
                    'id_member' => $memberId,
                ]);
            }

            if ($activeHoldId > 0) {
                $holdTable = $this->resolveHoldTable($pdo);
                $holdItemsTable = $this->resolveHoldItemsTable($pdo);
                if ($holdTable !== null && $holdItemsTable !== null) {
                    $pdo->prepare('DELETE FROM `' . $holdItemsTable . '` WHERE hold_id = :hold_id')->execute(['hold_id' => $activeHoldId]);
                    $pdo->prepare('DELETE FROM `' . $holdTable . '` WHERE id = :id AND (id_member = :id_member OR id_member IS NULL OR id_member = 0)')->execute(['id' => $activeHoldId, 'id_member' => $memberId]);
                }
            }

            $pdo->prepare('DELETE FROM keranjang WHERE id_member = :id_member')->execute(['id_member' => $memberId]);
            unset($_SESSION['penjualan_active_hold_id']);
            $pdo->commit();

            try {
                $akunKasId = (new KeuanganService($pdo))->resolveAkunIdByKode('1101');
                if ($akunKasId <= 0) {
                    throw new RuntimeException('Akun kas/bank belum tersedia.');
                }
                (new KeuanganService($pdo))->catatMutasi([
                    'tanggal' => $tanggal,
                    'no_ref' => $noTrx,
                    'akun_keuangan_id' => $akunKasId,
                    'jenis' => 'debit',
                    'tipe_arus' => 'pemasukan',
                    'nominal' => $grandTotal,
                    'sumber_tipe' => 'penjualan',
                    'sumber_id' => $penjualanId,
                    'metode_pembayaran' => $paymentMethod,
                    'deskripsi' => 'Penjualan ' . $noTrx,
                    'status' => 'posted',
                    'created_by' => $memberId,
                ]);
            } catch (Throwable) {
                toast_add('Penjualan tersimpan, namun mutasi keuangan gagal.', 'warning');
            }

            $pelangganNama = 'Umum / Non Member';
            if ($idPelanggan > 0) {
                $stmtPelanggan = $pdo->prepare('SELECT nama_pelanggan FROM pelanggan WHERE id = :id LIMIT 1');
                $stmtPelanggan->execute(['id' => $idPelanggan]);
                $rowPelanggan = $stmtPelanggan->fetch(PDO::FETCH_ASSOC);
                if (is_array($rowPelanggan)) {
                    $namaDb = trim((string) ($rowPelanggan['nama_pelanggan'] ?? ''));
                    if ($namaDb !== '') {
                        $pelangganNama = $namaDb;
                    }
                }
            }

            $_SESSION['penjualan_last_receipt'] = [
                'no_trx' => $noTrx,
                'tanggal' => date('Y-m-d H:i:s'),
                'kasir' => (string) ($_SESSION['auth']['name'] ?? $_SESSION['auth']['username'] ?? 'Kasir'),
                'pelanggan' => $pelangganNama,
                'payment_method' => $paymentMethod,
                'total' => $grandTotal,
                'bayar' => $bayar,
                'kembalian' => max(0, $bayar - $grandTotal),
                'keterangan' => $keterangan,
                'items' => $normalized,
            ];

            toast_add('Checkout berhasil. No transaksi: ' . $noTrx . '.', 'success');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Checkout gagal.', 'error');
        }

        return Response::redirect('/transaksi/penjualan');
    }

    public function pembelian(Request $request): Response
    {
        $auth = $this->auth();
        if (!$this->canAccessPenjualan($auth)) {
            toast_add('Anda tidak punya akses ke fitur pembelian.', 'error');
            return Response::redirect('/dashboard');
        }

        $title = menu_generator_title_by_route('transaksi', 'Pembelian');
        $pageTitle = $title === 'Transaksi' ? 'Pembelian' : ($title ?? 'Pembelian');
        $payload = [
            'cartItems' => [],
            'barangOptions' => [],
            'supplierOptions' => [],
            'summary' => ['qty' => 0, 'subtotal' => 0, 'diskon' => 0, 'grand_total' => 0],
            'canViewHargaModal' => false,
            'canEditHargaModal' => false,
            'canManagePo' => false,
            'saldoKas' => 0,
        ];

        try {
            $pdo = Database::connection();
            $memberId = (int) ($auth['id'] ?? 0);
            $payload['cartItems'] = $this->purchaseCartItems($pdo, $memberId);
            $payload['summary'] = $this->purchaseCartSummary($payload['cartItems']);
            $payload['barangOptions'] = $this->listBarang($pdo);
            $payload['supplierOptions'] = $this->listSupplier($pdo);
            $payload['canViewHargaModal'] = $this->canViewHargaModal($auth);
            $payload['canEditHargaModal'] = $this->canEditHargaModal($auth);
            $payload['canManagePo'] = $this->canManagePo($auth);
            try {
                $payload['saldoKas'] = (int) round((new KeuanganService($pdo))->saldoKasSaatIni());
            } catch (Throwable) {
                $payload['saldoKas'] = 0;
            }
        } catch (Throwable) {
            toast_add('Gagal memuat data pembelian.', 'error');
        }

        $html = app()->view()->render('transaksi/pembelian', [
            'title' => $pageTitle,
            'auth' => $auth,
            'activeMenu' => 'transaksi-pembelian',
            'paymentMethods' => self::PURCHASE_PAYMENT_METHODS,
            ...$payload,
        ]);

        return Response::html($html);
    }

    public function addPurchaseCartItem(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $itemId = (int) $request->input('item_id', '0');
        $qty = max(1, (int) $request->input('qty', '1'));
        $inputBeli = max(0, (int) $request->input('beli', '0'));
        $inputJual = max(0, (int) $request->input('jual', '0'));
        if ($itemId <= 0) {
            toast_add('Barang tidak valid.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        try {
            $pdo = Database::connection();
            $pdo->beginTransaction();

            $barang = $this->findBarang($pdo, $itemId);
            if ($barang === null) {
                throw new RuntimeException('Barang tidak ditemukan.');
            }
            $auth = $this->auth();
            $canEditHargaModal = $this->canEditHargaModal($auth);
            $beli = $canEditHargaModal && $inputBeli > 0 ? $inputBeli : (int) ($barang['beli'] ?? 0);
            $jual = $inputJual > 0 ? $inputJual : (int) ($barang['jual'] ?? 0);

            $stmtExisting = $pdo->prepare('SELECT id, jumlah FROM keranjang_beli WHERE id_member = :id_member AND id_barang = :id_barang LIMIT 1');
            $stmtExisting->execute(['id_member' => $memberId, 'id_barang' => $itemId]);
            $existing = $stmtExisting->fetch(PDO::FETCH_ASSOC);
            $newQty = max(0, (int) ($existing['jumlah'] ?? 0)) + $qty;

            if (is_array($existing)) {
                $update = $pdo->prepare('UPDATE keranjang_beli SET jumlah = :jumlah, beli = :beli, jual = :jual WHERE id = :id');
                $update->execute([
                    'jumlah' => (string) $newQty,
                    'beli' => $beli,
                    'jual' => $jual,
                    'id' => (int) ($existing['id'] ?? 0),
                ]);
            } else {
                $insert = $pdo->prepare('INSERT INTO keranjang_beli (id_barang, id_member, nama_barang, jumlah, beli, jual, tanggal_input) VALUES (:id_barang, :id_member, :nama_barang, :jumlah, :beli, :jual, :tanggal_input)');
                $insert->execute([
                    'id_barang' => (string) $itemId,
                    'id_member' => $memberId,
                    'nama_barang' => (string) ($barang['name'] ?? ''),
                    'jumlah' => (string) $newQty,
                    'beli' => $beli,
                    'jual' => $jual,
                    'tanggal_input' => date('Y-m-d'),
                ]);
            }

            $pdo->commit();
            toast_add('Barang berhasil masuk keranjang pembelian.', 'success');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Gagal menambah keranjang pembelian.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    public function updatePurchaseCartItem(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $cartId = (int) $request->input('cart_id', '0');
        $qty = max(0, (int) $request->input('qty', '0'));
        $inputBeli = max(0, (int) $request->input('beli', '0'));
        $inputJual = max(0, (int) $request->input('jual', '0'));
        if ($cartId <= 0) {
            toast_add('Item keranjang pembelian tidak valid.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT id FROM keranjang_beli WHERE id = :id AND id_member = :id_member LIMIT 1');
            $stmt->execute(['id' => $cartId, 'id_member' => $memberId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                throw new RuntimeException('Item keranjang pembelian tidak ditemukan.');
            }

            if ($qty <= 0) {
                $del = $pdo->prepare('DELETE FROM keranjang_beli WHERE id = :id AND id_member = :id_member');
                $del->execute(['id' => $cartId, 'id_member' => $memberId]);
                toast_add('Item keranjang pembelian dihapus.', 'success');
                return Response::redirect('/transaksi/pembelian');
            }

            $auth = $this->auth();
            $canEditHargaModal = $this->canEditHargaModal($auth);
            $setParts = ['jumlah = :jumlah'];
            $params = ['jumlah' => (string) $qty, 'id' => $cartId, 'id_member' => $memberId];
            if ($canEditHargaModal && $inputBeli > 0) {
                $setParts[] = 'beli = :beli';
                $params['beli'] = $inputBeli;
            }
            if ($inputJual > 0) {
                $setParts[] = 'jual = :jual';
                $params['jual'] = $inputJual;
            }

            $upd = $pdo->prepare('UPDATE keranjang_beli SET ' . implode(', ', $setParts) . ' WHERE id = :id AND id_member = :id_member');
            $upd->execute($params);
            toast_add('Jumlah keranjang pembelian diperbarui.', 'success');
        } catch (Throwable $e) {
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Gagal update keranjang pembelian.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    public function removePurchaseCartItem(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $cartId = (int) $request->input('cart_id', '0');
        if ($cartId <= 0) {
            toast_add('Item keranjang pembelian tidak valid.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        try {
            $pdo = Database::connection();
            $del = $pdo->prepare('DELETE FROM keranjang_beli WHERE id = :id AND id_member = :id_member');
            $del->execute(['id' => $cartId, 'id_member' => $memberId]);
            toast_add('Item keranjang pembelian dihapus.', 'success');
        } catch (Throwable) {
            toast_add('Gagal hapus item keranjang pembelian.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    public function clearPurchaseCart(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        try {
            $pdo = Database::connection();
            $del = $pdo->prepare('DELETE FROM keranjang_beli WHERE id_member = :id_member');
            $del->execute(['id_member' => $memberId]);
            toast_add('Keranjang pembelian dikosongkan.', 'success');
        } catch (Throwable) {
            toast_add('Gagal mengosongkan keranjang pembelian.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    public function quickSupplier(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $nama = trim((string) $request->input('nama_supplier', ''));
        $telepon = trim((string) $request->input('telepon_supplier', ''));
        $alamat = trim((string) $request->input('alamat_supplier', ''));
        $email = trim((string) $request->input('email_supplier', ''));
        if ($nama === '') {
            toast_add('Nama supplier wajib diisi.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        try {
            $pdo = Database::connection();
            $insertColumns = ['nama_supplier', 'alamat_supplier', 'telepon_supplier', 'email_supplier'];
            $params = [
                'nama_supplier' => $nama,
                'alamat_supplier' => $alamat !== '' ? $alamat : null,
                'telepon_supplier' => $telepon !== '' ? $telepon : null,
                'email_supplier' => $email !== '' ? $email : null,
            ];

            if ($this->columnExists($pdo, 'supplier', 'created_at')) {
                $insertColumns[] = 'created_at';
            }

            $columnSql = '`' . implode('`,`', $insertColumns) . '`';
            $valueSqlParts = [];
            foreach ($insertColumns as $column) {
                if ($column === 'created_at') {
                    $valueSqlParts[] = 'NOW()';
                    continue;
                }
                $valueSqlParts[] = ':' . $column;
            }
            $valueSql = implode(',', $valueSqlParts);

            $stmt = $pdo->prepare('INSERT INTO supplier (' . $columnSql . ') VALUES (' . $valueSql . ')');
            $stmt->execute($params);
            toast_add('Supplier baru berhasil ditambahkan.', 'success');
        } catch (Throwable) {
            toast_add('Gagal menambah supplier baru.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    public function quickModalPembelian(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $nominal = max(0, (int) $request->input('nominal_modal', '0'));
        $tanggal = trim((string) $request->input('tanggal_modal', ''));
        $deskripsi = trim((string) $request->input('keterangan_modal', ''));
        if ($nominal <= 0) {
            toast_add('Nominal tambah modal harus lebih dari 0.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        if ($tanggal === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $tanggal = date('Y-m-d');
        }

        try {
            $pdo = Database::connection();
            $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
            $debtService = new SupplierDebtService($pdo);
            $akunKasId = $debtService->resolveKasAkunId();
            if ($akunKasId <= 0) {
                throw new RuntimeException('Akun KAS belum tersedia. Cek master akun keuangan.');
            }

            (new KeuanganService($pdo))->catatMutasi([
                'tanggal' => $tanggal,
                'no_ref' => 'MODAL-' . date('YmdHis'),
                'akun_keuangan_id' => $akunKasId,
                'jenis' => 'debit',
                'tipe_arus' => 'pemasukan',
                'nominal' => $nominal,
                'sumber_tipe' => 'modal_cepat_pembelian',
                'metode_pembayaran' => 'Cash',
                'deskripsi' => $deskripsi !== '' ? $deskripsi : 'Tambah modal cepat ke akun kas dari halaman pembelian',
                'status' => 'posted',
                'created_by' => $memberId,
            ]);

            toast_add('Tambah modal berhasil dicatat ke pemasukan akun KAS.', 'success');
        } catch (Throwable $e) {
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Tambah modal cepat gagal.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    public function purchasePoDatatable(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return Response::json([
                'draw' => max(0, (int) ($request->all()['draw'] ?? 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Akses ditolak.',
            ], 403);
        }

        try {
            $pdo = Database::connection();
            if (
                !$this->columnExists($pdo, 'pembelian', 'po_status')
                || !$this->columnExists($pdo, 'pembelian', 'po_no_reg')
                || !$this->columnExists($pdo, 'pembelian', 'po_deleted_at')
            ) {
                return Response::json([
                    'draw' => max(0, (int) ($request->all()['draw'] ?? 0)),
                    'recordsTotal' => 0,
                    'recordsFiltered' => 0,
                    'data' => [],
                    'error' => 'Kolom PO belum tersedia. Jalankan migration PO.',
                ]);
            }
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

            $orderMap = [
                0 => 'p.id',
                1 => 'p.po_no_reg',
                2 => 'p.no_trx',
                3 => 'p.keterangan',
                4 => 'p.po_status',
            ];
            $orderIndex = (int) ($params['order'][0]['column'] ?? 0);
            $orderColumn = $orderMap[$orderIndex] ?? 'p.id';
            $orderDir = strtolower((string) ($params['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

            $whereSql = " WHERE COALESCE(p.po_status, '') <> '' AND p.po_deleted_at IS NULL";
            $bindings = [];
            if ($search !== '') {
                $whereSql .= ' AND (p.po_no_reg LIKE :search OR p.no_trx LIKE :search OR p.keterangan LIKE :search OR p.nm_supplier LIKE :search)';
                $bindings['search'] = '%' . $search . '%';
            }

            $stmtTotal = $pdo->query("SELECT COUNT(*) FROM pembelian p WHERE COALESCE(p.po_status, '') <> '' AND p.po_deleted_at IS NULL");
            $recordsTotal = (int) $stmtTotal->fetchColumn();
            if ($search === '') {
                $recordsFiltered = $recordsTotal;
            } else {
                $stmtFiltered = $pdo->prepare('SELECT COUNT(*) FROM pembelian p' . $whereSql);
                foreach ($bindings as $key => $value) {
                    $stmtFiltered->bindValue(':' . $key, $value);
                }
                $stmtFiltered->execute();
                $recordsFiltered = (int) $stmtFiltered->fetchColumn();
            }

            $sql = 'SELECT p.id, p.po_no_reg, p.no_trx, p.nm_supplier, p.keterangan, p.po_status, p.status_bayar, p.beli, p.tanggal_input, p.po_review_note
                FROM pembelian p'
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

            foreach ($rows as &$row) {
                $status = strtolower(trim((string) ($row['po_status'] ?? 'pending')));
                if (!in_array($status, ['pending', 'diterima', 'ditolak'], true)) {
                    $status = 'pending';
                }
                $row['po_status'] = $status;
                $row['po_status_label'] = match ($status) {
                    'diterima' => 'Diterima',
                    'ditolak' => 'Ditolak',
                    default => 'Pending',
                };
            }
            unset($row);

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
                'error' => 'Gagal memuat daftar PO.',
            ], 500);
        }
    }

    public function updatePurchasePo(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $auth = $this->auth();
        if (!$this->canManagePo($auth)) {
            toast_add('Anda tidak punya akses untuk approve/tolak PO.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        $poId = max(0, (int) $request->input('po_id', '0'));
        $poAction = strtolower(trim((string) $request->input('po_action', '')));
        $poNote = trim((string) $request->input('po_note', ''));
        if ($poId <= 0 || !in_array($poAction, ['diterima', 'ditolak'], true)) {
            toast_add('Data PO tidak valid.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        try {
            $pdo = Database::connection();
            if (
                !$this->columnExists($pdo, 'pembelian', 'po_status')
                || !$this->columnExists($pdo, 'pembelian', 'po_no_reg')
                || !$this->columnExists($pdo, 'pembelian', 'po_deleted_at')
            ) {
                throw new RuntimeException('Fitur PO belum aktif. Jalankan migration PO terlebih dahulu.');
            }
            $pdo->beginTransaction();
            $stmtPo = $pdo->prepare('SELECT id, no_trx, beli, po_status, po_deleted_at, supplier_id, nm_supplier, due_date FROM pembelian WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmtPo->execute(['id' => $poId]);
            $po = $stmtPo->fetch(PDO::FETCH_ASSOC);
            if (!is_array($po) || (string) ($po['po_deleted_at'] ?? '') !== '') {
                throw new RuntimeException('Data PO tidak ditemukan.');
            }

            $currentPoStatus = strtolower(trim((string) ($po['po_status'] ?? 'pending')));
            if ($currentPoStatus === 'diterima') {
                throw new RuntimeException('PO sudah diterima sebelumnya.');
            }

            $memberId = (int) ($auth['id'] ?? 0);
            if ($poAction === 'ditolak') {
                $stmtReject = $pdo->prepare("UPDATE pembelian SET po_status = 'ditolak', po_review_note = :po_review_note, po_review_by = :po_review_by, po_review_at = NOW() WHERE id = :id");
                $stmtReject->execute([
                    'id' => $poId,
                    'po_review_note' => $poNote !== '' ? $poNote : null,
                    'po_review_by' => $memberId > 0 ? $memberId : null,
                ]);
                $pdo->commit();
                toast_add('PO berhasil ditolak.', 'success');
                return Response::redirect('/transaksi/pembelian');
            }

            $totalPo = max(0, (int) ($po['beli'] ?? 0));
            if ($totalPo <= 0) {
                throw new RuntimeException('Nilai PO tidak valid.');
            }

            $stmtDet = $pdo->prepare('SELECT id_barang, qty, beli FROM pembelian_detail WHERE no_trx = :no_trx ORDER BY id ASC');
            $stmtDet->execute(['no_trx' => (string) ($po['no_trx'] ?? '')]);
            $details = $stmtDet->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($details) || $details === []) {
                throw new RuntimeException('Detail PO tidak ditemukan.');
            }

            $supplierId = max(0, (int) ($po['supplier_id'] ?? 0));
            if ($supplierId <= 0) {
                $supplierName = trim((string) ($po['nm_supplier'] ?? ''));
                if ($supplierName !== '') {
                    $stmtSupplier = $pdo->prepare('SELECT id FROM supplier WHERE nama_supplier = :nama_supplier LIMIT 1');
                    $stmtSupplier->execute(['nama_supplier' => $supplierName]);
                    $supplierRow = $stmtSupplier->fetch(PDO::FETCH_ASSOC);
                    $supplierId = is_array($supplierRow) ? (int) ($supplierRow['id'] ?? 0) : 0;
                }
            }
            if ($supplierId <= 0) {
                throw new RuntimeException('Supplier PO belum valid. Lengkapi supplier terlebih dahulu.');
            }

            $stmtUpdateBarang = $pdo->prepare('UPDATE barang SET stok = stok + :qty, harga_beli = :harga_beli, updated_at = NOW() WHERE id = :id');
            foreach ($details as $detail) {
                if (!is_array($detail)) {
                    continue;
                }
                $barangId = max(0, (int) ($detail['id_barang'] ?? 0));
                if ($barangId <= 0) {
                    continue;
                }
                $stmtUpdateBarang->execute([
                    'id' => $barangId,
                    'qty' => max(0, (int) ($detail['qty'] ?? 0)),
                    'harga_beli' => max(0, (int) ($detail['beli'] ?? 0)),
                ]);
            }

            $dueDate = trim((string) ($po['due_date'] ?? ''));
            if ($dueDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                $dueDate = date('Y-m-d', strtotime('+14 days'));
            }

            $stmtAccept = $pdo->prepare("UPDATE pembelian
                SET status_bayar = 'Hutang',
                    payment_status = 'unpaid',
                    paid_amount = 0,
                    remaining_amount = :remaining_amount,
                    due_date = :due_date,
                    po_status = 'diterima',
                    po_review_note = :po_review_note,
                    po_review_by = :po_review_by,
                    po_review_at = NOW()
                WHERE id = :id");
            $stmtAccept->execute([
                'id' => $poId,
                'remaining_amount' => $totalPo,
                'due_date' => $dueDate,
                'po_review_note' => $poNote !== '' ? $poNote : null,
                'po_review_by' => $memberId > 0 ? $memberId : null,
            ]);

            $stmtSyncDet = $pdo->prepare("UPDATE pembelian_detail SET status_bayar = 'Hutang' WHERE no_trx = :no_trx");
            $stmtSyncDet->execute(['no_trx' => (string) ($po['no_trx'] ?? '')]);

            $debtService = new SupplierDebtService($pdo);
            $debtService->createDebt([
                'purchase_id' => $poId,
                'supplier_id' => $supplierId,
                'debt_no' => 'UTANG-' . (string) ($po['no_trx'] ?? $poId),
                'debt_date' => date('Y-m-d'),
                'total_amount' => $totalPo,
                'paid_amount' => 0,
                'due_date' => $dueDate,
                'status' => 'unpaid',
                'notes' => $poNote !== '' ? $poNote : (string) ($po['nm_supplier'] ?? ''),
            ]);

            $pdo->commit();
            toast_add('PO berhasil diterima. Stok ditambahkan dan hutang supplier dicatat.', 'success');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Gagal memproses PO.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    public function deletePurchasePo(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $auth = $this->auth();
        if (!$this->canManagePo($auth)) {
            toast_add('Anda tidak punya akses untuk hapus PO.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        $poId = max(0, (int) $request->input('po_id', '0'));
        if ($poId <= 0) {
            toast_add('Data PO tidak valid.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        try {
            $pdo = Database::connection();
            if (
                !$this->columnExists($pdo, 'pembelian', 'po_status')
                || !$this->columnExists($pdo, 'pembelian', 'po_deleted_at')
            ) {
                throw new RuntimeException('Fitur PO belum aktif. Jalankan migration PO terlebih dahulu.');
            }
            $memberId = (int) ($auth['id'] ?? 0);
            $stmtState = $pdo->prepare('SELECT po_status FROM pembelian WHERE id = :id LIMIT 1');
            $stmtState->execute(['id' => $poId]);
            $stateRow = $stmtState->fetch(PDO::FETCH_ASSOC);
            $currentStatus = strtolower(trim((string) ($stateRow['po_status'] ?? 'pending')));
            if ($currentStatus === 'diterima') {
                throw new RuntimeException('PO yang sudah diterima tidak boleh dihapus. Gunakan reversal pada phase berikutnya.');
            }

            $stmtDelete = $pdo->prepare('UPDATE pembelian SET po_deleted_at = NOW(), po_deleted_by = :po_deleted_by WHERE id = :id AND po_deleted_at IS NULL');
            $stmtDelete->execute([
                'id' => $poId,
                'po_deleted_by' => $memberId > 0 ? $memberId : null,
            ]);
            toast_add('PO berhasil dihapus (soft delete).', 'success');
        } catch (Throwable) {
            toast_add('Gagal menghapus PO.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    public function checkoutPembelian(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $supplierId = max(0, (int) $request->input('supplier_id', '0'));
        $paymentMethod = trim((string) $request->input('payment_method', ''));
        $bayar = max(0, (int) $request->input('bayar', '0'));
        $dueDate = trim((string) $request->input('due_date', ''));
        $keterangan = trim((string) $request->input('keterangan', ''));
        if (!in_array($paymentMethod, self::PURCHASE_PAYMENT_METHODS, true)) {
            toast_add('Metode pembayaran pembelian tidak valid.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }
        if ($supplierId <= 0) {
            toast_add('Supplier wajib dipilih.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }
        if ($paymentMethod === 'Termin' && ($dueDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate))) {
            toast_add('Tanggal jatuh tempo wajib diisi untuk pembelian termin.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        try {
            $pdo = Database::connection();
            $items = $this->purchaseCartItems($pdo, $memberId);
            if ($items === []) {
                throw new RuntimeException('Keranjang pembelian kosong.');
            }

            $supplier = $this->findSupplier($pdo, $supplierId);
            if ($supplier === null) {
                throw new RuntimeException('Supplier tidak ditemukan.');
            }
            $supplierName = (string) ($supplier['nama_supplier'] ?? 'Supplier Umum');

            $pdo->beginTransaction();
            $normalized = [];
            $totalQty = 0;
            $grandTotal = 0;
            $auth = $this->auth();
            $canEditHargaModal = $this->canEditHargaModal($auth);
            $debtService = new SupplierDebtService($pdo);

            foreach ($items as $item) {
                $barangId = (int) ($item['id_barang'] ?? 0);
                $qty = max(1, (int) ($item['jumlah'] ?? 1));
                $beliInput = max(0, (int) ($item['beli'] ?? 0));
                $jualInput = max(0, (int) ($item['jual'] ?? 0));

                $stmtBarang = $pdo->prepare('SELECT id, id_barang, nama_barang, harga_beli, harga_jual, stok FROM barang WHERE id = :id LIMIT 1 FOR UPDATE');
                $stmtBarang->execute(['id' => $barangId]);
                $barang = $stmtBarang->fetch(PDO::FETCH_ASSOC);
                if (!is_array($barang)) {
                    throw new RuntimeException('Barang tidak ditemukan saat checkout pembelian.');
                }

                $beli = $canEditHargaModal && $beliInput > 0 ? $beliInput : max(0, (int) ($barang['harga_beli'] ?? 0));
                $jual = $jualInput > 0 ? $jualInput : max(0, (int) ($barang['harga_jual'] ?? 0));
                $lineTotal = $beli * $qty;
                $normalized[] = [
                    'barang_id' => $barangId,
                    'code' => (string) ($barang['id_barang'] ?? ''),
                    'name' => (string) ($barang['nama_barang'] ?? ''),
                    'qty' => $qty,
                    'beli' => $beli,
                    'jual' => $jual,
                    'total' => $lineTotal,
                ];
                $totalQty += $qty;
                $grandTotal += $lineTotal;
            }

            if ($grandTotal <= 0) {
                throw new RuntimeException('Total pembelian harus lebih dari 0.');
            }
            if ($paymentMethod === 'Cash' && $bayar !== $grandTotal) {
                throw new RuntimeException('Nominal bayar cash harus sama dengan total pembelian.');
            }
            if ($paymentMethod === 'Termin' && $bayar > $grandTotal) {
                throw new RuntimeException('Nominal bayar termin tidak boleh melebihi total pembelian.');
            }

            $paidAmount = min($grandTotal, $bayar);
            $remainingAmount = max(0, $grandTotal - $paidAmount);
            $paymentStatus = 'unpaid';
            if ($remainingAmount <= 0) {
                $paymentStatus = 'paid';
            } elseif ($paidAmount > 0) {
                $paymentStatus = 'partial';
            }
            $statusBayar = $paymentStatus === 'paid' ? 'Lunas' : 'Hutang';

            $saldoKas = 0.0;
            try {
                $saldoKas = (new KeuanganService($pdo))->saldoKasSaatIni();
            } catch (Throwable) {
                $saldoKas = 0.0;
            }
            if ($paidAmount > 0 && $saldoKas < $paidAmount) {
                throw new RuntimeException(
                    'Saldo kas tidak mencukupi untuk pembayaran awal. '
                        . 'Saldo saat ini: Rp ' . number_format($saldoKas, 0, ',', '.') . ', '
                        . 'dibutuhkan: Rp ' . number_format($paidAmount, 0, ',', '.') . '.'
                );
            }

            $updStok = $pdo->prepare('UPDATE barang SET stok = stok + :qty, harga_beli = :harga_beli, harga_jual = :harga_jual, updated_at = NOW() WHERE id = :id');
            foreach ($normalized as $item) {
                $updStok->execute([
                    'qty' => (int) $item['qty'],
                    'harga_beli' => (int) $item['beli'],
                    'harga_jual' => (int) $item['jual'],
                    'id' => (int) $item['barang_id'],
                ]);
            }

            $noTrx = $this->generateNoTrxPembelian($pdo);
            $tanggal = date('Y-m-d');
            $periode = date('Ym');

            $keteranganFinal = $keterangan;
            if ($keteranganFinal !== '') {
                $keteranganFinal .= ' | ';
            }
            $keteranganFinal .= 'Metode: ' . $paymentMethod;
            if ($paymentStatus !== 'paid') {
                $keteranganFinal .= ' | Hutang supplier: Rp ' . number_format($remainingAmount, 0, ',', '.');
            }
            if ($paymentMethod === 'Termin') {
                $keteranganFinal .= ' | Jatuh tempo: ' . $dueDate;
            }

            $insHead = $pdo->prepare('INSERT INTO pembelian (nm_supplier, supplier_id, no_trx, id_member, jumlah, beli, paid_amount, remaining_amount, due_date, payment_status, keterangan, status_bayar, payment_method, tanggal_input, created_at, periode) VALUES (:nm_supplier, :supplier_id, :no_trx, :id_member, :jumlah, :beli, :paid_amount, :remaining_amount, :due_date, :payment_status, :keterangan, :status_bayar, :payment_method, :tanggal_input, NOW(), :periode)');
            $insHead->execute([
                'nm_supplier' => $supplierName,
                'supplier_id' => $supplierId,
                'no_trx' => $noTrx,
                'id_member' => $memberId,
                'jumlah' => $totalQty,
                'beli' => $grandTotal,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'due_date' => $paymentMethod === 'Termin' ? $dueDate : null,
                'payment_status' => $paymentStatus,
                'keterangan' => $keteranganFinal,
                'status_bayar' => $statusBayar,
                'payment_method' => $paymentMethod,
                'tanggal_input' => $tanggal,
                'periode' => $periode,
            ]);
            $pembelianId = (int) $pdo->lastInsertId();

            $insDet = $pdo->prepare('INSERT INTO pembelian_detail (no_trx, id_barang, idb, nama_barang, beli, qty, total, tgl_input, status_bayar, periode, id_member, created_at) VALUES (:no_trx, :id_barang, :idb, :nama_barang, :beli, :qty, :total, :tgl_input, :status_bayar, :periode, :id_member, NOW())');
            foreach ($normalized as $item) {
                $insDet->execute([
                    'no_trx' => $noTrx,
                    'id_barang' => (int) $item['barang_id'],
                    'idb' => $item['code'],
                    'nama_barang' => $item['name'],
                    'beli' => (int) $item['beli'],
                    'qty' => (int) $item['qty'],
                    'total' => (int) $item['total'],
                    'tgl_input' => $tanggal,
                    'status_bayar' => $statusBayar,
                    'periode' => $periode,
                    'id_member' => $memberId,
                ]);
            }

            if ($paidAmount > 0) {
                $debtService->postCashOutflow([
                    'tanggal' => $tanggal,
                    'no_ref' => $noTrx,
                    'sumber_tipe' => 'pembelian_supplier',
                    'sumber_id' => $pembelianId,
                    'metode_pembayaran' => $paymentMethod,
                    'amount' => $paidAmount,
                    'deskripsi' => 'Pembayaran pembelian supplier ' . $noTrx . ' kepada ' . $supplierName,
                    'created_by' => $memberId,
                ]);
            }

            if ($remainingAmount > 0) {
                $debtId = $debtService->createDebt([
                    'purchase_id' => $pembelianId,
                    'supplier_id' => $supplierId,
                    'debt_no' => 'UTANG-' . $noTrx,
                    'debt_date' => $tanggal,
                    'total_amount' => $grandTotal,
                    'paid_amount' => $paidAmount,
                    'due_date' => $paymentMethod === 'Termin' ? $dueDate : null,
                    'status' => $paymentStatus,
                    'notes' => $keteranganFinal,
                ]);
                $pdo->prepare('UPDATE pembelian SET payment_status = :payment_status, remaining_amount = :remaining_amount, paid_amount = :paid_amount, status_bayar = :status_bayar WHERE id = :id')->execute([
                    'payment_status' => $paymentStatus,
                    'remaining_amount' => $remainingAmount,
                    'paid_amount' => $paidAmount,
                    'status_bayar' => $statusBayar,
                    'id' => $pembelianId,
                ]);
                if ($debtId > 0) {
                    $keteranganFinal .= ' | Hutang # ' . $debtId;
                }
            }

            $pdo->prepare('DELETE FROM keranjang_beli WHERE id_member = :id_member')->execute(['id_member' => $memberId]);
            $pdo->commit();

            if ($paymentStatus !== 'paid') {
                toast_add(
                    'Pembelian berhasil disimpan dengan status ' . strtoupper($paymentStatus) . '. '
                        . 'Sisa hutang supplier: Rp ' . number_format($remainingAmount, 0, ',', '.') . '.',
                    'warning'
                );
            } else {
                toast_add('Checkout pembelian berhasil. No transaksi: ' . $noTrx . '.', 'success');
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Checkout pembelian gagal.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    public function salesHistoryDaily(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return Response::json(['error' => 'Akses ditolak.'], 403);
        }

        try {
            $pdo = Database::connection();
            $today = date('Y-m-d');

            $sql = 'SELECT p.id, p.no_trx, p.tanggal_input, p.created_at, p.total, p.bayar, p.payment_method, p.status_bayar,
                        p.id_pelanggan, COALESCE(pl.nama_pelanggan, \'Umum / Non Member\') AS pelanggan,
                        COALESCE(u.name, u.user, \'Kasir\') AS kasir
                    FROM penjualan p
                    LEFT JOIN pelanggan pl ON pl.id = p.id_pelanggan
                    LEFT JOIN users u ON u.id = p.id_member
                    WHERE p.tanggal_input = :today
                    ORDER BY p.tanggal_input DESC, p.id DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['today' => $today]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                $rows = [];
            }

            $grouped = [];
            $totalNominal = 0;
            foreach ($rows as $row) {
                $tanggal = trim((string) ($row['tanggal_input'] ?? ''));
                if ($tanggal === '') {
                    $tanggal = date('Y-m-d');
                }
                if (!isset($grouped[$tanggal])) {
                    $grouped[$tanggal] = [
                        'date' => $tanggal,
                        'label' => date('d M Y', strtotime($tanggal)),
                        'rows' => [],
                    ];
                }
                $grouped[$tanggal]['rows'][] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'no_trx' => (string) ($row['no_trx'] ?? ''),
                    'tanggal_input' => $tanggal,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'pelanggan' => (string) ($row['pelanggan'] ?? 'Umum / Non Member'),
                    'kasir' => (string) ($row['kasir'] ?? 'Kasir'),
                    'payment_method' => (string) ($row['payment_method'] ?? ''),
                    'status_bayar' => (string) ($row['status_bayar'] ?? ''),
                    'total' => max(0, (int) ($row['total'] ?? 0)),
                    'bayar' => max(0, (int) ($row['bayar'] ?? 0)),
                ];
                $totalNominal += max(0, (int) ($row['total'] ?? 0));
            }

            return Response::json([
                'scope' => 'today',
                'period_label' => date('d M Y', strtotime($today)),
                'total_transactions' => count($rows),
                'total_nominal' => $totalNominal,
                'groups' => array_values($grouped),
            ]);
        } catch (Throwable) {
            return Response::json([
                'scope' => 'today',
                'period_label' => date('d M Y'),
                'total_transactions' => 0,
                'total_nominal' => 0,
                'groups' => [],
                'error' => 'Gagal memuat histori penjualan.',
            ], 500);
        }
    }

    public function salesReceipt(Request $request): Response
    {
        $guard = $this->guardPenjualan();
        if ($guard !== null) {
            return Response::json(['error' => 'Akses ditolak.'], 403);
        }

        $noTrx = trim((string) $request->input('no_trx', ''));
        if ($noTrx === '') {
            return Response::json(['error' => 'No transaksi wajib diisi.'], 422);
        }

        try {
            $pdo = Database::connection();
            $receipt = $this->buildSalesReceiptByNoTrx($pdo, $noTrx);
            if ($receipt === null) {
                return Response::json(['error' => 'Data transaksi tidak ditemukan.'], 404);
            }

            return Response::json([
                'success' => true,
                'data' => $receipt,
            ]);
        } catch (Throwable) {
            return Response::json(['error' => 'Gagal memuat data nota.'], 500);
        }
    }

    public function purchaseHistoryDaily(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return Response::json(['error' => 'Akses ditolak.'], 403);
        }

        try {
            $pdo = Database::connection();
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
            $hasPoStatus = $this->columnExists($pdo, 'pembelian', 'po_status');
            $hasPoDeletedAt = $this->columnExists($pdo, 'pembelian', 'po_deleted_at');

            $where = ['p.tanggal_input >= :month_start', 'p.tanggal_input <= :month_end'];
            if ($hasPoDeletedAt) {
                $where[] = 'p.po_deleted_at IS NULL';
            }
            if ($hasPoStatus) {
                $where[] = '(COALESCE(p.po_status, \'\') = \'\' OR p.po_status = \'diterima\')';
            }

            $sql = 'SELECT p.id, p.no_trx, p.nm_supplier, p.beli, p.jumlah, p.status_bayar, p.payment_status, p.paid_amount, p.remaining_amount, p.due_date, p.keterangan, p.tanggal_input, p.created_at,
                        COALESCE(u.name, u.user, \'Kasir\') AS kasir'
                . ($hasPoStatus ? ', p.po_status' : ', \'\' AS po_status')
                . ' FROM pembelian p
                    LEFT JOIN users u ON u.id = p.id_member
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY p.tanggal_input DESC, p.id DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['month_start' => $monthStart, 'month_end' => $monthEnd]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                $rows = [];
            }

            $grouped = [];
            $totalNominal = 0;
            foreach ($rows as $row) {
                $tanggal = trim((string) ($row['tanggal_input'] ?? ''));
                if ($tanggal === '') {
                    $tanggal = date('Y-m-d');
                }
                if (!isset($grouped[$tanggal])) {
                    $grouped[$tanggal] = [
                        'date' => $tanggal,
                        'label' => date('d M Y', strtotime($tanggal)),
                        'rows' => [],
                    ];
                }
                $grouped[$tanggal]['rows'][] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'no_trx' => (string) ($row['no_trx'] ?? ''),
                    'tanggal_input' => $tanggal,
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'nm_supplier' => (string) ($row['nm_supplier'] ?? 'Supplier Umum'),
                    'kasir' => (string) ($row['kasir'] ?? 'Kasir'),
                    'status_bayar' => (string) ($row['status_bayar'] ?? ''),
                    'payment_status' => (string) ($row['payment_status'] ?? ''),
                    'po_status' => (string) ($row['po_status'] ?? ''),
                    'jumlah' => max(0, (int) ($row['jumlah'] ?? 0)),
                    'total' => max(0, (int) ($row['beli'] ?? 0)),
                    'paid_amount' => max(0, (int) ($row['paid_amount'] ?? 0)),
                    'remaining_amount' => max(0, (int) ($row['remaining_amount'] ?? 0)),
                    'due_date' => (string) ($row['due_date'] ?? ''),
                    'keterangan' => (string) ($row['keterangan'] ?? ''),
                ];
                $totalNominal += max(0, (int) ($row['beli'] ?? 0));
            }

            return Response::json([
                'scope' => 'month',
                'period_label' => date('F Y'),
                'total_transactions' => count($rows),
                'total_nominal' => $totalNominal,
                'groups' => array_values($grouped),
            ]);
        } catch (Throwable) {
            return Response::json([
                'scope' => 'month',
                'period_label' => date('F Y'),
                'total_transactions' => 0,
                'total_nominal' => 0,
                'groups' => [],
                'error' => 'Gagal memuat histori pembelian.',
            ], 500);
        }
    }

    public function purchaseDebtDatatable(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return Response::json([
                'draw' => max(0, (int) ($request->all()['draw'] ?? 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Akses ditolak.',
            ], 403);
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

            $where = ' WHERE sd.remaining_amount > 0';
            $bindings = [];
            if ($search !== '') {
                $where .= ' AND (sd.debt_no LIKE :search OR s.nama_supplier LIKE :search OR p.no_trx LIKE :search OR sd.status LIKE :search)';
                $bindings['search'] = '%' . $search . '%';
            }

            $baseFrom = ' FROM supplier_debts sd LEFT JOIN supplier s ON s.id = sd.supplier_id LEFT JOIN pembelian p ON p.id = sd.purchase_id';
            $stmtTotal = $pdo->query('SELECT COUNT(*) FROM supplier_debts WHERE remaining_amount > 0');
            $recordsTotal = (int) $stmtTotal->fetchColumn();
            if ($search === '') {
                $recordsFiltered = $recordsTotal;
            } else {
                $stmtCount = $pdo->prepare('SELECT COUNT(*)' . $baseFrom . $where);
                foreach ($bindings as $key => $value) {
                    $stmtCount->bindValue(':' . $key, $value);
                }
                $stmtCount->execute();
                $recordsFiltered = (int) $stmtCount->fetchColumn();
            }

            $sql = 'SELECT sd.id, sd.debt_no, sd.debt_date, sd.total_amount, sd.paid_amount, sd.remaining_amount, sd.due_date, sd.status, sd.notes, sd.purchase_id, sd.supplier_id, s.nama_supplier, p.no_trx, p.payment_status, p.payment_method'
                . $baseFrom . $where
                . ' ORDER BY sd.due_date IS NULL, sd.due_date ASC, sd.id DESC LIMIT :limit OFFSET :offset';
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
        } catch (Throwable $e) {
            return Response::json([
                'draw' => max(0, (int) ($request->all()['draw'] ?? 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function purchaseDebtDetail(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return Response::json(['error' => 'Akses ditolak.'], 403);
        }

        $debtId = max(0, (int) $request->input('debt_id', '0'));
        if ($debtId <= 0) {
            return Response::json(['error' => 'Data hutang tidak valid.'], 422);
        }

        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT sd.id, sd.debt_no, sd.debt_date, sd.total_amount, sd.paid_amount, sd.remaining_amount, sd.due_date, sd.status, sd.notes,
                        sd.purchase_id, sd.supplier_id, s.nama_supplier, s.telepon_supplier, s.alamat_supplier,
                        p.no_trx, p.tanggal_input, p.payment_status, p.payment_method, p.keterangan
                 FROM supplier_debts sd
                 LEFT JOIN supplier s ON s.id = sd.supplier_id
                 LEFT JOIN pembelian p ON p.id = sd.purchase_id
                 WHERE sd.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $debtId]);
            $debt = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($debt)) {
                return Response::json(['error' => 'Data hutang tidak ditemukan.'], 404);
            }

            $stmtPay = $pdo->prepare(
                'SELECT sp.id, sp.payment_no, sp.payment_date, sp.payment_method, sp.amount, sp.reference_no, sp.notes, sp.created_at,
                        COALESCE(u.name, u.user, \'Kasir\') AS created_by_name
                 FROM supplier_debt_payments sp
                 LEFT JOIN users u ON u.id = sp.created_by
                 WHERE sp.supplier_debt_id = :supplier_debt_id
                 ORDER BY sp.payment_date DESC, sp.id DESC'
            );
            $stmtPay->execute(['supplier_debt_id' => $debtId]);
            $payments = $stmtPay->fetchAll(PDO::FETCH_ASSOC);
            if (!is_array($payments)) {
                $payments = [];
            }

            return Response::json([
                'success' => true,
                'data' => $debt,
                'payments' => $payments,
            ]);
        } catch (Throwable $e) {
            return Response::json(['error' => $e->getMessage()], 500);
        }
    }

    public function paySupplierDebt(Request $request): Response
    {
        $guard = $this->guardPembelian();
        if ($guard !== null) {
            return $guard;
        }

        $memberId = (int) ($_SESSION['auth']['id'] ?? 0);
        $debtId = max(0, (int) $request->input('supplier_debt_id', '0'));
        $amount = max(0, (int) $request->input('amount', '0'));
        $notes = trim((string) $request->input('notes', ''));
        if ($debtId <= 0 || $amount <= 0) {
            toast_add('Data pembayaran hutang tidak valid.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        try {
            $pdo = Database::connection();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT sd.id, sd.purchase_id, sd.supplier_id, sd.debt_no, sd.remaining_amount, sd.status, sd.total_amount,
                        p.no_trx, p.tanggal_input, p.payment_status, p.payment_method, p.due_date, p.nm_supplier
                 FROM supplier_debts sd
                 LEFT JOIN pembelian p ON p.id = sd.purchase_id
                 WHERE sd.id = :id
                 LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['id' => $debtId]);
            $debt = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($debt)) {
                throw new RuntimeException('Hutang supplier tidak ditemukan.');
            }

            $remaining = max(0, (int) ($debt['remaining_amount'] ?? 0));
            if ($remaining <= 0 || strtolower((string) ($debt['status'] ?? '')) === 'paid') {
                throw new RuntimeException('Hutang supplier sudah lunas.');
            }
            if ($amount > $remaining) {
                throw new RuntimeException('Nominal bayar tidak boleh melebihi sisa hutang.');
            }

            $saldoKas = 0.0;
            try {
                $saldoKas = (new KeuanganService($pdo))->saldoKasSaatIni();
            } catch (Throwable) {
                $saldoKas = 0.0;
            }
            if ($saldoKas < $amount) {
                throw new RuntimeException('Saldo kas tidak mencukupi untuk pembayaran hutang.');
            }

            $debtService = new SupplierDebtService($pdo);
            $paymentDate = date('Y-m-d');
            $debtService->recordDebtPayment([
                'supplier_debt_id' => $debtId,
                'purchase_id' => (int) ($debt['purchase_id'] ?? 0),
                'supplier_id' => (int) ($debt['supplier_id'] ?? 0),
                'payment_no' => 'BYR-' . (string) ($debt['debt_no'] ?? 'DEBT') . '-' . date('YmdHis'),
                'payment_date' => $paymentDate,
                'payment_method' => 'Cash',
                'kas_akun_id' => $debtService->resolveKasAkunId(),
                'amount' => $amount,
                'reference_no' => (string) ($debt['no_trx'] ?? ''),
                'notes' => $notes !== '' ? $notes : 'Pembayaran hutang supplier',
                'created_by' => $memberId,
            ]);

            $debtService->postCashOutflow([
                'tanggal' => $paymentDate,
                'no_ref' => 'BAYAR-' . (string) ($debt['debt_no'] ?? $debtId),
                'sumber_tipe' => 'pembayaran_hutang_supplier',
                'sumber_id' => $debtId,
                'metode_pembayaran' => 'Cash',
                'amount' => $amount,
                'deskripsi' => 'Pembayaran hutang supplier ' . (string) ($debt['nama_supplier'] ?? ''),
                'created_by' => $memberId,
            ]);

            $stmtSum = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM supplier_debt_payments WHERE supplier_debt_id = :id');
            $stmtSum->execute(['id' => $debtId]);
            $paidTotal = min(max(0, (int) ($debt['total_amount'] ?? 0)), (int) $stmtSum->fetchColumn());
            $newRemaining = max(0, (int) ($debt['total_amount'] ?? 0) - $paidTotal);
            $newStatus = $newRemaining <= 0 ? 'paid' : ($paidTotal > 0 ? 'partial' : 'unpaid');

            $stmtUpdate = $pdo->prepare('UPDATE supplier_debts SET paid_amount = :paid_amount, remaining_amount = :remaining_amount, status = :status, updated_at = NOW() WHERE id = :id');
            $stmtUpdate->execute([
                'paid_amount' => $paidTotal,
                'remaining_amount' => $newRemaining,
                'status' => $newStatus,
                'id' => $debtId,
            ]);

            $pdo->prepare('UPDATE pembelian SET paid_amount = :paid_amount, remaining_amount = :remaining_amount, payment_status = :payment_status, status_bayar = :status_bayar WHERE id = :id')->execute([
                'paid_amount' => $paidTotal,
                'remaining_amount' => $newRemaining,
                'payment_status' => $newStatus,
                'status_bayar' => $newRemaining <= 0 ? 'Lunas' : 'Hutang',
                'id' => (int) ($debt['purchase_id'] ?? 0),
            ]);
            $pdo->prepare('UPDATE pembelian_detail SET status_bayar = :status_bayar WHERE no_trx = :no_trx')->execute([
                'status_bayar' => $newRemaining <= 0 ? 'Lunas' : 'Hutang',
                'no_trx' => (string) ($debt['no_trx'] ?? ''),
            ]);

            $pdo->commit();
            toast_add('Pembayaran hutang supplier berhasil disimpan.', 'success');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            toast_add($e->getMessage() !== '' ? $e->getMessage() : 'Gagal membayar hutang supplier.', 'error');
        }

        return Response::redirect('/transaksi/pembelian');
    }

    /**
     * @return array<string, mixed>
     */
    private function auth(): array
    {
        return is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
    }

    /**
     * @param array<string, mixed> $auth
     */
    private function canAccessPenjualan(array $auth): bool
    {
        $role = strtolower(trim((string) ($auth['role'] ?? '')));
        return in_array($role, ['kasir', 'admin', 'administrator', 'owner', 'superadmin', 'super-admin'], true);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function canViewHargaModal(array $auth): bool
    {
        $role = strtolower(trim((string) ($auth['role'] ?? '')));
        return in_array($role, ['admin', 'administrator', 'owner', 'spv', 'superadmin', 'super-admin'], true);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function canEditHargaModal(array $auth): bool
    {
        return $this->canViewHargaModal($auth);
    }

    /**
     * @param array<string,mixed> $auth
     */
    private function canManagePo(array $auth): bool
    {
        $role = strtolower(trim((string) ($auth['role'] ?? '')));
        return in_array($role, ['admin', 'administrator', 'owner', 'spv', 'superadmin', 'super-admin'], true);
    }

    private function guardPenjualan(): ?Response
    {
        $auth = $this->auth();
        if (!$this->canAccessPenjualan($auth)) {
            toast_add('Anda tidak punya akses ke fitur penjualan.', 'error');
            return Response::redirect('/dashboard');
        }
        if ((int) ($auth['id'] ?? 0) <= 0) {
            toast_add('Sesi pengguna tidak valid.', 'error');
            return Response::redirect('/transaksi/penjualan');
        }

        return null;
    }

    private function guardPembelian(): ?Response
    {
        $auth = $this->auth();
        if (!$this->canAccessPenjualan($auth)) {
            toast_add('Anda tidak punya akses ke fitur pembelian.', 'error');
            return Response::redirect('/dashboard');
        }
        if ((int) ($auth['id'] ?? 0) <= 0) {
            toast_add('Sesi pengguna tidak valid.', 'error');
            return Response::redirect('/transaksi/pembelian');
        }

        return null;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function resolveHoldTable(PDO $pdo): ?string
    {
        $candidates = ['penjualan_hold', 'pejualan_hold'];
        foreach ($candidates as $table) {
            if ($this->tableExists($pdo, $table)) {
                return $table;
            }
        }
        return null;
    }

    private function resolveHoldItemsTable(PDO $pdo): ?string
    {
        $candidates = ['penjualan_hold_items', 'penjualan_hold_item'];
        foreach ($candidates as $table) {
            if ($this->tableExists($pdo, $table)) {
                return $table;
            }
        }
        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function cartItems(PDO $pdo, int $memberId): array
    {
        $stmt = $pdo->prepare('SELECT id, id_barang, item_type, item_ref_id, id_member, nama_barang, diskon, jumlah, beli, jual, tanggal_input FROM keranjang WHERE id_member = :id_member ORDER BY id ASC');
        $stmt->execute(['id_member' => $memberId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    private function cartSummary(array $items): array
    {
        $qty = 0;
        $subtotal = 0;
        $diskon = 0;
        $grand = 0;
        foreach ($items as $item) {
            $q = max(1, (int) ($item['jumlah'] ?? 1));
            $jual = max(0, (int) ($item['jual'] ?? 0));
            $dis = max(0, (int) ($item['diskon'] ?? 0));
            $qty += $q;
            $subtotal += ($jual * $q);
            $diskon += ($dis * $q);
            $grand += max(0, ($jual - $dis) * $q);
        }
        return ['qty' => $qty, 'subtotal' => $subtotal, 'diskon' => $diskon, 'grand_total' => $grand];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function purchaseCartItems(PDO $pdo, int $memberId): array
    {
        $stmt = $pdo->prepare('SELECT id, id_barang, id_member, nama_barang, jumlah, beli, jual, tanggal_input FROM keranjang_beli WHERE id_member = :id_member ORDER BY id ASC');
        $stmt->execute(['id_member' => $memberId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    private function purchaseCartSummary(array $items): array
    {
        $qty = 0;
        $subtotal = 0;
        foreach ($items as $item) {
            $q = max(1, (int) ($item['jumlah'] ?? 1));
            $beli = max(0, (int) ($item['beli'] ?? 0));
            $qty += $q;
            $subtotal += ($beli * $q);
        }

        return ['qty' => $qty, 'subtotal' => $subtotal, 'diskon' => 0, 'grand_total' => $subtotal];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listBarang(PDO $pdo): array
    {
        $rows = $pdo->query(
            'SELECT b.id, b.id_barang, b.nama_barang, b.harga_beli, b.harga_jual, b.stok, b.gambar, fm.path AS gambar_path
             FROM barang b
             LEFT JOIN filemanager fm ON fm.id = b.gambar AND fm.deleted_at IS NULL
             WHERE b.deleted_at IS NULL
             ORDER BY b.nama_barang ASC, b.id ASC
             LIMIT 500'
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['diskon_aktif'] = $this->activeDiskonByBarangRef(
                $pdo,
                (string) ($row['id_barang'] ?? ''),
                (int) ($row['id'] ?? 0)
            );
            $row['harga_jual_diskon'] = max(0, (int) ($row['harga_jual'] ?? 0) - ($row['diskon_aktif']));
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listJasa(PDO $pdo): array
    {
        $rows = $pdo->query(
            'SELECT j.id, j.id_jasa, j.nama, j.harga, j.gambar_img, fm.path AS gambar_path
             FROM jasa j
             LEFT JOIN filemanager fm ON fm.id = j.gambar_img AND fm.deleted_at IS NULL
             WHERE j.deleted_at IS NULL
             ORDER BY j.nama ASC, j.id ASC
             LIMIT 500'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function frequentBarang(PDO $pdo): array
    {
        if (!$this->tableExists($pdo, 'penjualan_detail')) {
            return [];
        }

        $rows = $pdo->query(
            'SELECT b.id, b.id_barang, b.nama_barang, b.harga_jual, b.stok, b.gambar, fm.path AS gambar_path, SUM(pd.qty) AS total_qty
             FROM penjualan_detail pd
             INNER JOIN barang b ON b.id = pd.idb AND b.deleted_at IS NULL
             LEFT JOIN filemanager fm ON fm.id = b.gambar AND fm.deleted_at IS NULL
             WHERE pd.item_type = \'barang\'
             GROUP BY b.id, b.id_barang, b.nama_barang, b.harga_jual, b.stok, b.gambar, fm.path
             ORDER BY total_qty DESC, b.nama_barang ASC
             LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function frequentJasa(PDO $pdo): array
    {
        if (!$this->tableExists($pdo, 'penjualan_detail')) {
            return [];
        }

        $rows = $pdo->query(
            'SELECT j.id, j.id_jasa, j.nama, j.harga, j.gambar_img, fm.path AS gambar_path, SUM(pd.qty) AS total_qty
             FROM penjualan_detail pd
             INNER JOIN jasa j ON j.id = pd.idb AND j.deleted_at IS NULL
             LEFT JOIN filemanager fm ON fm.id = j.gambar_img AND fm.deleted_at IS NULL
             WHERE pd.item_type = \'jasa\'
             GROUP BY j.id, j.id_jasa, j.nama, j.harga, j.gambar_img, fm.path
             ORDER BY total_qty DESC, j.nama ASC
             LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listPelanggan(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT id, kode_pelanggan, nama_pelanggan FROM pelanggan WHERE deleted_at IS NULL ORDER BY nama_pelanggan ASC, id ASC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listSupplier(PDO $pdo): array
    {
        $where = '';
        if ($this->columnExists($pdo, 'supplier', 'deleted_at')) {
            $where = ' WHERE deleted_at IS NULL';
        }
        $rows = $pdo->query('SELECT id, nama_supplier, telepon_supplier FROM supplier' . $where . ' ORDER BY nama_supplier ASC, id ASC LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSupplier(PDO $pdo, int $supplierId): ?array
    {
        $where = ' WHERE id = :id';
        if ($this->columnExists($pdo, 'supplier', 'deleted_at')) {
            $where .= ' AND deleted_at IS NULL';
        }
        $stmt = $pdo->prepare('SELECT id, nama_supplier FROM supplier' . $where . ' LIMIT 1');
        $stmt->execute(['id' => $supplierId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBarang(PDO $pdo, int $itemId): ?array
    {
        $stmt = $pdo->prepare('SELECT id, id_barang, nama_barang, harga_beli, harga_jual, stok FROM barang WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return [
            'code' => (string) ($row['id_barang'] ?? ''),
            'name' => (string) ($row['nama_barang'] ?? ''),
            'beli' => max(0, (int) ($row['harga_beli'] ?? 0)),
            'jual' => max(0, (int) ($row['harga_jual'] ?? 0)),
            'stok' => max(0, (int) ($row['stok'] ?? 0)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findJasa(PDO $pdo, int $itemId): ?array
    {
        $stmt = $pdo->prepare('SELECT id, id_jasa, nama, harga FROM jasa WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return [
            'code' => (string) ($row['id_jasa'] ?? ''),
            'name' => (string) ($row['nama'] ?? ''),
            'beli' => 0,
            'jual' => max(0, (int) ($row['harga'] ?? 0)),
        ];
    }

    private function generateNoTrx(PDO $pdo): string
    {
        $prefix = 'PJ' . date('Ymd');
        $stmt = $pdo->prepare('SELECT no_trx FROM penjualan WHERE no_trx LIKE :prefix ORDER BY id DESC LIMIT 1');
        $stmt->execute(['prefix' => $prefix . '%']);
        $last = (string) $stmt->fetchColumn();
        $seq = 1;
        if ($last !== '' && preg_match('/(\\d{4})$/', $last, $m) === 1) {
            $seq = ((int) ($m[1] ?? 0)) + 1;
        }
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateNoTrxPembelian(PDO $pdo): string
    {
        $prefix = 'OR' . date('Ymd');
        $stmt = $pdo->prepare('SELECT no_trx FROM pembelian WHERE no_trx LIKE :prefix ORDER BY id DESC LIMIT 1');
        $stmt->execute(['prefix' => $prefix . '%']);
        $last = (string) $stmt->fetchColumn();
        $seq = 1;
        if ($last !== '' && preg_match('/(\\d{4})$/', $last, $m) === 1) {
            $seq = ((int) ($m[1] ?? 0)) + 1;
        }
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function generateNoRegPo(PDO $pdo): string
    {
        $prefix = 'PO' . date('Ymd');
        $stmt = $pdo->prepare('SELECT po_no_reg FROM pembelian WHERE po_no_reg LIKE :prefix ORDER BY id DESC LIMIT 1');
        $stmt->execute(['prefix' => $prefix . '%']);
        $last = (string) $stmt->fetchColumn();
        $seq = 1;
        if ($last !== '' && preg_match('/(\\d{4})$/', $last, $m) === 1) {
            $seq = ((int) ($m[1] ?? 0)) + 1;
        }
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function activeDiskonByBarangRef(PDO $pdo, string $barangCode, int $barangId): int
    {
        $code = $this->normalizeBarangDiscountKey($barangCode);
        $idRef = $barangId > 0 ? $this->normalizeBarangDiscountKey((string) $barangId) : '';
        if ($code === '' && $idRef === '') {
            return 0;
        }
        try {
            $today = date('Y-m-d');
            $sql = 'SELECT diskon FROM diskon
                WHERE deleted_at IS NULL
                  AND (tgl_start IS NULL OR tgl_start <= :today_start)
                  AND (tgl_end IS NULL OR tgl_end >= :today_end)';
            $bind = ['today_start' => $today, 'today_end' => $today];
            if ($code !== '' && $idRef !== '') {
                $sql .= ' AND (UPPER(TRIM(barang_id)) = :barang_code OR UPPER(TRIM(barang_id)) = :barang_id_ref)';
                $bind['barang_code'] = $code;
                $bind['barang_id_ref'] = $idRef;
            } elseif ($code !== '') {
                $sql .= ' AND UPPER(TRIM(barang_id)) = :barang_code';
                $bind['barang_code'] = $code;
            } else {
                $sql .= ' AND UPPER(TRIM(barang_id)) = :barang_id_ref';
                $bind['barang_id_ref'] = $idRef;
            }
            $sql .= ' ORDER BY id DESC LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bind);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? max(0, (int) ($row['diskon'] ?? 0)) : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    private function normalizeBarangDiscountKey(string $value): string
    {
        return strtoupper(trim($value));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function buildSalesReceiptByNoTrx(PDO $pdo, string $noTrx): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT p.no_trx, p.tanggal_input, p.created_at, p.payment_method, p.total, p.bayar, p.keterangan,
                COALESCE(pl.nama_pelanggan, \'Umum / Non Member\') AS pelanggan,
                COALESCE(u.name, u.user, \'Kasir\') AS kasir
            FROM penjualan p
            LEFT JOIN pelanggan pl ON pl.id = p.id_pelanggan
            LEFT JOIN users u ON u.id = p.id_member
            WHERE p.no_trx = :no_trx
            LIMIT 1'
        );
        $stmt->execute(['no_trx' => $noTrx]);
        $head = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($head)) {
            return null;
        }

        $stmtItems = $pdo->prepare(
            'SELECT nama_barang, qty, jual, diskon, total
            FROM penjualan_detail
            WHERE no_trx = :no_trx
            ORDER BY id ASC'
        );
        $stmtItems->execute(['no_trx' => $noTrx]);
        $rows = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $items[] = [
                    'name' => (string) ($row['nama_barang'] ?? '-'),
                    'qty' => max(0, (int) ($row['qty'] ?? 0)),
                    'jual' => max(0, (int) ($row['jual'] ?? 0)),
                    'diskon' => max(0, (int) ($row['diskon'] ?? 0)),
                    'total' => max(0, (int) ($row['total'] ?? 0)),
                ];
            }
        }

        $createdAt = trim((string) ($head['created_at'] ?? ''));
        $tanggalInput = trim((string) ($head['tanggal_input'] ?? ''));
        $tanggal = $createdAt !== '' ? $createdAt : $tanggalInput;

        $total = max(0, (int) ($head['total'] ?? 0));
        $bayar = max(0, (int) ($head['bayar'] ?? 0));

        return [
            'no_trx' => (string) ($head['no_trx'] ?? ''),
            'tanggal' => $tanggal,
            'kasir' => (string) ($head['kasir'] ?? 'Kasir'),
            'pelanggan' => (string) ($head['pelanggan'] ?? 'Umum / Non Member'),
            'payment_method' => (string) ($head['payment_method'] ?? ''),
            'total' => $total,
            'bayar' => $bayar,
            'kembalian' => max(0, $bayar - $total),
            'keterangan' => (string) ($head['keterangan'] ?? ''),
            'items' => $items,
        ];
    }
}
