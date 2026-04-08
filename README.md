# LintasPOS

**LintasPOS - Sistem Point of Sale berbasis AitiCore Flex**

![LintasPOS Preview](https://aiti-solutions.com/storage/filemanager/1/39/image_login-494ed577d8f68693bedb05935b4498fe.webp)

LintasPOS adalah aplikasi POS (Point of Sale) fullstack yang dibangun di atas AitiCore Flex — framework PHP ringan dengan baseline keamanan modern: escape output default, CSRF middleware, dan session hardening.

## Fitur Utama

- **Transaksi Penjualan** — keranjang, diskon otomatis, hold transaksi, checkout multi metode pembayaran
- **Transaksi Pembelian** — keranjang beli, Purchase Order (PO) otomatis saat saldo tidak cukup, approval PO
- **Manajemen Produk** — Barang (dengan stok) dan Jasa (tanpa stok)
- **Laporan** — penjualan, pembelian, modal, filter periode/produk/pelanggan/supplier, export PDF A4
- **Keuangan** — chart of accounts, ledger mutasi kas terintegrasi ke transaksi
- **File Manager** — upload gambar produk, visibilitas public/private per role
- **Menu Generator** — generate CRUD modul baru tanpa coding manual
- **Multi Role** — Kasir, Admin, SPV, Owner dengan hak akses berbeda

## Rekomendasi Usaha

LintasPOS paling cocok untuk usaha dengan pola **jual-beli stok + kas harian**:

- **Toko ritel kecil-menengah** - sembako, minimarket lokal, alat tulis, toko bangunan kecil
- **Bengkel + sparepart + jasa** - mendukung transaksi barang dan jasa dalam satu alur
- **Toko elektronik / HP / aksesoris** - cocok untuk item cepat mutasi dan kebutuhan laporan periodik
- **Apotek / toko kesehatan non-resep sederhana** - fokus pada kontrol stok, pembelian supplier, dan arus kas
- **Toko fashion / sepatu** - mendukung promo diskon dan pelacakan performa penjualan
- **Grosir skala kecil** - terbantu oleh alur pembelian dan PO saat kas belum mencukupi

Kurang ideal untuk:

- Bisnis murni booking/jadwal tanpa kebutuhan stok kuat
- Restoran kompleks dengan kebutuhan kitchen display, split bill meja, dan recipe costing detail
- E-commerce multi-warehouse/omnichannel skala besar

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
| ------------------ | :---: | :---: | :-: | :---: |
| Penjualan          |  ✅   |  ✅   | ❌  |  ✅   |
| Pembelian          |  ✅   |  ✅   | ❌  |  ✅   |
| Lihat Harga Modal  |  ❌   |  ✅   | ✅  |  ✅   |
| Approve / Tolak PO |  ❌   |  ✅   | ✅  |  ✅   |
| Laporan            |  ❌   |  ✅   | ✅  |  ✅   |
| Keuangan           |  ❌   |  ✅   | ✅  |  ✅   |
| Menu Generator     |  ❌   |  ✅   | ❌  |  ✅   |

## Alur Penjualan

```
Tambah item ke keranjang
  ├─ Barang: validasi stok + diskon aktif
  └─ Jasa: langsung masuk tanpa cek stok
      ↓
[Opsional] Hold transaksi → lanjutkan nanti
      ↓
Checkout (pilih pelanggan, metode bayar, nominal)
  ├─ Kurangi stok barang
  ├─ Simpan penjualan + penjualan_detail
  └─ Catat mutasi kas (pemasukan)
```

## Alur Pembelian

```
Tambah barang ke keranjang beli
      ↓
Checkout pembelian
  ├─ [Saldo cukup]  → Lunas, stok langsung bertambah
  └─ [Saldo kurang] → Dibuat sebagai PO (pending)
                          ↓
                    Approval PO oleh Admin/SPV/Owner
                      ├─ Diterima → stok bertambah + mutasi kas (pengeluaran)
                      └─ Ditolak  → PO dibatalkan
```

## Metode Pembayaran

- Penjualan: `Cash`, `E-wallet`, `QRIS`, `Transfer Bank`
- Pembelian: `Cash`, `Termin`

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
- Semua query menggunakan prepared statement / binding — tidak ada query concat dari input user.
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
