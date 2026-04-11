# LintasPOS

**LintasPOS - Sistem Point of Sale berbasis AitiCore Flex**

![LintasPOS Preview](https://aiti-solutions.com/storage/filemanager/1/39/image_login-494ed577d8f68693bedb05935b4498fe.webp)

LintasPOS adalah aplikasi POS (Point of Sale) fullstack yang dibangun di atas AitiCore Flex - framework PHP ringan dengan baseline keamanan modern: escape output default, CSRF middleware, dan session hardening.

## Fitur Utama

- **Transaksi Penjualan** - keranjang, diskon otomatis, hold transaksi, checkout multi metode pembayaran
- **Transaksi Pembelian** - keranjang beli, pembelian cash, pembelian termin/hutang, dan realisasi PO
- **Manajemen Produk** - Barang (dengan stok) dan Jasa (tanpa stok)
- **Laporan** - penjualan, pembelian final, PO, hutang supplier, modal, filter periode/produk/pelanggan/supplier, export PDF A4
- **Keuangan** - chart of accounts, ledger mutasi kas terintegrasi ke transaksi
- **File Manager** - upload gambar produk, visibilitas public/private per role
- **Menu Generator** - generate CRUD modul baru tanpa coding manual
- **Multi Role** - Kasir, Admin, SPV, Owner dengan hak akses berbeda

## Rekomendasi Usaha

LintasPOS paling cocok untuk usaha dengan pola **jual-beli stok + kas harian**:

- Toko ritel kecil-menengah
- Bengkel + sparepart + jasa
- Toko elektronik / HP / aksesoris
- Apotek / toko kesehatan non-resep sederhana
- Toko fashion / sepatu
- Grosir skala kecil

Kurang ideal untuk:

- Bisnis murni booking/jadwal tanpa kebutuhan stok kuat
- Restoran kompleks dengan kitchen display dan recipe costing detail
- E-commerce multi-warehouse skala besar

## Requirements

- PHP 8.2+
- Composer
- ext-pdo, ext-mbstring, ext-openssl
- MySQL / MariaDB

## Quick Start

Linux/macOS:

```bash
cp .env.example .env
composer install
php aiti key:generate
php aiti migrate update
php aiti serve
```

Windows CMD:

```bat
copy .env.example .env
composer install
php aiti key:generate
php aiti migrate update
php aiti serve
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
composer install
php aiti key:generate
php aiti migrate update
php aiti serve
```

Buka `http://127.0.0.1:8000`.

## Folder Structure

```text
app/
  Controllers/
  Middleware/
  Modules/
  Services/
  Views/
bootstrap/
database/
  update/
  drop/
design_template/
public/
routes/
storage/
  filemanager/
  sessions/
  cache/
system/
tests/
upgrade-guides/
```

## Role & Akses

| Fitur              | Kasir | Admin | SPV | Owner |
| ------------------ | ----- | ----- | --- | ----- |
| Penjualan          | ✅    | ✅    | ❌  | ✅    |
| Pembelian          | ✅    | ✅    | ❌  | ✅    |
| Lihat Harga Modal  | ❌    | ✅    | ✅  | ✅    |
| Approve / Tolak PO | ❌    | ✅    | ✅  | ✅    |
| Laporan            | ❌    | ✅    | ✅  | ✅    |
| Keuangan           | ❌    | ✅    | ✅  | ✅    |
| Menu Generator     | ❌    | ✅    | ❌  | ✅    |

## Alur Proses

### Penjualan

1. Barang/jasa dipilih ke keranjang.
2. Stok barang divalidasi.
3. Checkout menyimpan `penjualan` dan `penjualan_detail`.
4. Mutasi keuangan dicatat sebagai pemasukan kas.
5. Laporan penjualan dan laporan keuangan membaca data yang sama.

### Pembelian

1. Item dipilih ke keranjang beli.
2. Supplier wajib dipilih.
3. Checkout pembelian menentukan:
   - cash/lunas -> cek saldo kas, stok masuk, kas keluar
   - termin/hutang -> stok masuk, hutang supplier terbentuk, DP jika ada memotong kas
4. Riwayat hutang supplier disimpan terpisah.
5. Laporan pembelian hanya berisi receipt/pembelian final.

### PO

1. PO dibuat sebagai dokumen pemesanan.
2. PO tidak menambah stok.
3. PO tidak mengurangi kas.
4. PO yang disetujui masih harus direalisasikan menjadi pembelian/receipt saat barang diterima.
5. PO pending/ditolak tidak masuk laporan pembelian final.

### Keuangan

1. Modal, pemasukan luar kasir, pengeluaran operasional, dan transaksi kas dicatat di ledger.
2. Saldo kas dihitung dari akun kas yang ditandai `is_kas = 1`.
3. Pembayaran hutang supplier mengurangi kas dan mengurangi saldo hutang.
4. Laporan keuangan dan laporan hutang membaca sumber data yang sama.

## Alur Ringkas

```
Penjualan -> stok turun -> kas masuk -> laporan penjualan + laporan keuangan
Pembelian cash -> stok naik -> kas keluar -> laporan pembelian + laporan keuangan
Pembelian termin -> stok naik -> hutang supplier -> pembayaran bertahap
PO -> approval -> realisasi menjadi pembelian/receipt
```

## Metode Pembayaran

- Penjualan: `Cash`, `E-wallet`, `QRIS`, `Transfer Bank`
- Pembelian: `Cash`, `Termin`
- PO: draft, pending, diterima, ditolak
- Hutang supplier: unpaid, partial, paid

## Sinkronisasi Alur

- Stok naik hanya saat barang diterima sebagai pembelian/receipt.
- Stok turun hanya saat penjualan final atau retur/reversal.
- Kas turun hanya saat pembayaran nyata keluar.
- Kas naik hanya saat pemasukan nyata masuk.
- PO tidak diperlakukan sebagai pembelian final.
- Hutang supplier terbentuk dari pembelian yang belum lunas.
- Laporan pembelian, PO, hutang, dan keuangan dibaca dari sumber data yang berbeda tetapi saling terhubung.

## CLI

Semua tool resmi lewat `php aiti ...`.

```bash
php aiti --version
php aiti list
php aiti serve
php aiti route:list
php aiti route:cache
php aiti route:clear
php aiti key:generate
php aiti migrate update
php aiti migrate drop
php aiti migrate status
php aiti migrate rollback --step=1
php aiti preset:bootstrap
php aiti optimize
php aiti config:clear
php aiti view:clear
php aiti upgrade:check
php aiti upgrade:apply
```

### Laravel Mapping

| Laravel                                 | LintasPOS / AitiCore Flex            |
| --------------------------------------- | ------------------------------------ |
| `php artisan optimize:clear`            | `php aiti optimize`                  |
| `php artisan config:clear`              | `php aiti config:clear`              |
| `php artisan route:cache`               | `php aiti route:cache`               |
| `php artisan route:clear`               | `php aiti route:clear`               |
| `php artisan view:clear`                | `php aiti view:clear`                |
| `php artisan migrate`                   | `php aiti migrate update`            |
| `php artisan migrate:fresh`             | `php aiti migrate drop`              |
| `php artisan migrate:status`            | `php aiti migrate status`            |
| `php artisan migrate:rollback --step=1` | `php aiti migrate rollback --step=1` |

## Routing Notes

- `php aiti serve` selalu menjalankan `router.php`, semua request masuk ke router yang sama.
- Request `HEAD` otomatis dipetakan ke route `GET`, body response tidak dikirim.
- Static asset seperti `/storage/...` atau file di `public/` dilayani langsung oleh PHP built-in server.

## Maintenance

- `php aiti optimize` menjalankan clear berurutan untuk cache config, routes, dan views.
- Command maintenance hanya menyentuh `storage/cache/*`.
- Logs, sessions, dan uploads tidak dihapus.

## Security Defaults

- Escaped output default di view (`<?= $var ?>` aman via escaper wrapper).
- CSRF aktif pada semua route `web`.
- Cookie session: HttpOnly + SameSite Lax, Secure saat HTTPS.
- Semua query menggunakan prepared statement / binding - tidak ada query concat dari input user.
- Upload file disimpan di `storage/filemanager/{module}/{role}/{user_id}/` dengan nama acak + MIME whitelist.

## Tests

```bash
composer test
```

## Safe Upgrade Policy

- Framework core (`system/`, `bootstrap/`, `public/`, root tooling) boleh di-update otomatis.
- User app (`app/`, `routes/`, `database/`) tidak pernah di-overwrite oleh updater.
- Setiap update wajib backup `*.bak.YmdHis` sebelum menyentuh file.
- SemVer wajib: PATCH = bugfix, MINOR = fitur baru, MAJOR = breaking change + migration guide.
