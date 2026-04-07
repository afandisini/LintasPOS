# AitiCore Flex

**AitiCore Flex - Lightweight & Secure PHP Framework**

![AitiCore Flex Preview](https://aiti-solutions.com/storage/filemanager/1/75e9c3cc2ad4828cf312c307e9d3095c.webp)

AitiCore Flex adalah framework PHP fullstack baseline keamanan modern: escape output default, CSRF middleware untuk route web, dan session hardening.

## Requirements

- PHP 8.2+
- Composer
- ext-pdo, ext-mbstring, ext-openssl

## Quick Start

Linux/macOS:

```bash
cp .env.example .env
composer install
php aiti key:generate
php aiti serve
```

Windows CMD:

```bat
copy .env.example .env
composer install
php aiti key:generate
php aiti serve
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
composer install
php aiti key:generate
php aiti serve
```

Buka `http://127.0.0.1:8000`.

## Folder Structure

```text
app/
  Config/
  Controllers/
  Middleware/
  Requests/
  Services/
  Models/
  Views/
  Helpers/
bootstrap/
public/
routes/
storage/
system/
tests/
```

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
php aiti upgrade:check
php aiti upgrade:apply
php aiti migrate update
php aiti migrate drop
php aiti migrate status
php aiti migrate rollback --step=1
php aiti preset:bootstrap
php aiti optimize
php aiti config:clear
php aiti view:clear
```

### Laravel Mapping

| Laravel                      | AitiCore Flex           |
| ---------------------------- | ----------------------- |
| `php artisan optimize:clear` | `php aiti optimize`     |
| `php artisan config:clear`   | `php aiti config:clear` |
| `php artisan route:cache`    | `php aiti route:cache`  |
| `php artisan route:clear`    | `php aiti route:clear`  |
| `php artisan view:clear`     | `php aiti view:clear`   |
| `php artisan migrate`        | `php aiti migrate update` |
| `php artisan migrate:fresh`  | `php aiti migrate drop` |
| `php artisan migrate:status` | `php aiti migrate status` |
| `php artisan migrate:rollback --step=1` | `php aiti migrate rollback --step=1` |

## Routing Notes

- `php aiti serve` sekarang selalu menjalankan `router.php`, jadi semua request masuk ke router yang sama.
- Request `HEAD` otomatis dipetakan ke route `GET`, tetapi body response tidak dikirim.
- Static asset seperti `/storage/...` atau file lain di `public/` akan dilayani langsung oleh PHP built-in server.

## Maintenance

- `php aiti optimize` menjalankan clear berurutan untuk cache config, routes, dan views.
- Command maintenance hanya menyentuh `storage/cache/*`.
- Logs (`storage/logs`), sessions (`storage/sessions`), dan uploads (`storage/uploads`) tidak dihapus.

## Safe Upgrade Policy

### Core vs User Ownership

- Framework core: `system/`, `bootstrap/`, `public/`, root tooling (`aiti`, `composer.json`, dll).
- User app: `app/`, `routes/`, `database/`.
- Updater **tidak boleh overwrite** path milik user.

### SemVer Rules

- `PATCH`: bugfix, no breaking change.
- `MINOR`: fitur baru kompatibel ke belakang.
- `MAJOR`: breaking change diperbolehkan dengan guide migrasi.
- Setiap release wajib punya changelog + upgrade guide.

### Upgrade Commands

- `php aiti upgrade:check [--from=vX] [--target=vY]`
  - read-only
  - cek jalur upgrade
  - scan konflik file
  - tampilkan breaking/risk/deprecation list

- `php aiti upgrade:apply [--from=vX] [--target=vY]`
  - default `dry-run` (tidak ubah file)
  - pakai `--apply` untuk eksekusi nyata
  - simpan backup `*.bak.YmdHis` sebelum menyentuh file
  - path user custom tetap di-skip

### Upgrade Metadata

- Catalog path: `upgrade-guides/index.php`
- Guide per versi: `upgrade-guides/vX-to-vY.md`
- Stub/patch template: `upgrade-guides/stubs/...`
- Gunakan `strategy=replace` atau `strategy=marker_merge` untuk patch terkontrol.

## Bootstrap Preset (Local Assets)

Preset Bootstrap dan Bootstrap Icons dibundle di repo pada:
`system/Presets/bootstrap`.

Command ini menyalin aset bundled ke:
`public/assets/vendor/...` tanpa butuh Node atau internet.

```bash
php aiti preset:bootstrap
```

Untuk developer, jika aset internal hilang, command akan fallback ke `node_modules`.
End user tidak perlu menjalankan `npm install`.

## Security Defaults

- Escaped output default di view (`<?= $var ?>` aman via escaper wrapper).
- CSRF aktif pada grup route `web`.
- Cookie session: HttpOnly + SameSite, Secure saat HTTPS/konfigurasi.
- Tidak ada query concat dari user input (gunakan prepared statement/binding).

## Tests

```bash
composer test
```

Coverage minimal awal:

- router happy path
- view escaping
- csrf token + blocking request invalid

## Donasi

### Donasi & Beli Kopi

Kalau AitiGo ngebantu kerjaanmu dan bikin hidup sedikit lebih waras,
boleh traktir kopi biar maintainer kuat begadang.

- [☕(saweria)](https://saweria.co/aitisolutions)
- [☕(Buymeacoffee)](https://buymeacoffee.com/aitisolutions)
- QRIS tersedia (Hubungi saya)
