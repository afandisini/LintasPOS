# PLAN ANDROID LintasPOS

## 1. Tujuan

Membangun aplikasi Android LintasPOS berbasis **Capacitor** dengan tampilan mobile khusus yang menyerupai aplikasi Android, bukan sekadar membuka halaman web LintasPOS di dalam WebView.

Aplikasi Android mengonsumsi REST API versi 1 dari backend AitiCore Flex:

```text
https://lintaspos.ddev.site/api_v1
```

Target produksi nantinya mengikuti domain produksi, misalnya:

```text
https://lintaspos.co-id.id/api_v1
```

Aplikasi Android harus mampu menjalankan fungsi operasional utama LintasPOS:

- login dan logout;
- dashboard;
- transaksi penjualan;
- transaksi pembelian;
- purchase order;
- hutang supplier dan pembayaran;
- master barang, jasa, kategori, satuan, pelanggan, supplier, dan diskon;
- keuangan;
- laporan;
- profil pengguna;
- file dan foto terkait data;
- pembatasan menu dan aksi berdasarkan role/hak akses.

Admin teknis seperti Menu Generator, audit keamanan, pengaturan hak akses kompleks, serta administrasi sistem tetap diprioritaskan untuk aplikasi web desktop.

---

## 2. Keputusan Arsitektur

### 2.1 Backend tetap satu sumber data

Backend web dan Android tetap memakai:

- database MariaDB yang sama;
- service bisnis yang sama;
- aturan stok yang sama;
- aturan kas dan ledger yang sama;
- aturan PO dan hutang supplier yang sama;
- file manager yang sama;
- hak akses pengguna yang sama.

Dilarang menyalin logika transaksi penting secara terpisah ke controller API karena berisiko menimbulkan perbedaan hasil antara web dan Android.

### 2.2 Pisahkan controller web dan controller API

Controller web saat ini mengembalikan HTML, redirect, flash message, toast, dan memakai session. Endpoint Android harus dibuat terpisah dan selalu mengembalikan JSON.

Struktur yang disarankan:

```text
app/
  Controllers/
    Api/
      V1/
        AuthController.php
        DashboardController.php
        BarangController.php
        JasaController.php
        KategoriController.php
        SatuanController.php
        PelangganController.php
        SupplierController.php
        DiskonController.php
        PenjualanController.php
        PembelianController.php
        PurchaseOrderController.php
        HutangSupplierController.php
        KeuanganController.php
        LaporanController.php
        ProfileController.php
        MediaController.php
  Middleware/
    ApiAuthenticate.php
    ApiPermission.php
    ApiRateLimit.php
    ApiCors.php
  Services/
    Auth/
    Inventory/
    Sales/
    Purchasing/
    Finance/
    Reports/
    Media/
  Support/
    ApiResponse.php
    ApiPaginator.php
    ApiToken.php
routes/
  web.php
  api_v1.php
```

### 2.3 Gunakan service layer bersama

Refactor bertahap logika dari controller lama ke service, contohnya:

```text
SalesService
  addItem()
  validateStock()
  calculateCart()
  checkout()
  hold()
  resume()
  cancel()

PurchasingService
  calculateCart()
  checkoutCash()
  checkoutCredit()
  createPurchaseOrder()
  approvePurchaseOrder()
  realizePurchaseOrder()

SupplierDebtService
  getOutstandingDebts()
  getDebtDetail()
  payDebt()

FinanceService
  postLedgerEntry()
  getCashBalance()
  createIncome()
  createExpense()
```

Controller web boleh tetap memakai session cart, sedangkan API memakai payload/cart milik client. Namun proses final checkout harus memanggil service transaksi yang sama.

---

## 3. Prinsip API

### 3.1 Base URL

```text
/api_v1
```

Contoh:

```text
GET  /api_v1/health
POST /api_v1/auth/login
GET  /api_v1/dashboard
GET  /api_v1/barang
POST /api_v1/penjualan/checkout
GET  /api_v1/laporan/penjualan
```

### 3.2 Format response sukses

```json
{
  "success": true,
  "message": "Data berhasil dimuat.",
  "data": {},
  "meta": {
    "request_id": "uuid",
    "timestamp": "2026-07-23T10:00:00+07:00"
  }
}
```

### 3.3 Format response gagal

```json
{
  "success": false,
  "message": "Validasi gagal.",
  "errors": {
    "nama_barang": ["Nama barang wajib diisi."]
  },
  "meta": {
    "request_id": "uuid",
    "timestamp": "2026-07-23T10:00:00+07:00"
  }
}
```

### 3.4 HTTP status

```text
200 OK
201 Created
204 No Content
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Validation Error
429 Too Many Requests
500 Internal Server Error
```

### 3.5 Pagination dan filter

Gunakan pola konsisten:

```text
GET /api_v1/barang?page=1&per_page=20&search=oli&sort=id&direction=desc
```

Response:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 125,
      "last_page": 7
    }
  }
}
```

Batas maksimum `per_page` disarankan 100.

---

## 4. Autentikasi Android

### 4.1 Jangan memakai session web sebagai autentikasi utama Android

Gunakan Bearer Token:

```http
Authorization: Bearer <access_token>
```

### 4.2 Tabel token

Buat migration baru:

```text
api_tokens
- id
- user_id
- token_hash
- device_name
- device_uuid
- platform
- app_version
- last_used_at
- expires_at
- revoked_at
- created_at
- updated_at
```

Token asli hanya dikirim satu kali saat login. Database menyimpan hash token, bukan token mentah.

### 4.3 Endpoint auth

```text
POST /api_v1/auth/login
POST /api_v1/auth/logout
POST /api_v1/auth/logout-all
GET  /api_v1/auth/me
POST /api_v1/auth/refresh
```

Payload login:

```json
{
  "identity": "admin",
  "password": "secret",
  "device_name": "Poco X6",
  "device_uuid": "generated-device-id",
  "platform": "android",
  "app_version": "1.0.0"
}
```

Response login minimal:

```json
{
  "success": true,
  "data": {
    "access_token": "token",
    "token_type": "Bearer",
    "expires_in": 2592000,
    "user": {},
    "permissions": []
  }
}
```

### 4.4 Penyimpanan token di Android

Gunakan penyimpanan aman:

- `@capacitor/preferences` hanya untuk data non-rahasia;
- token disimpan menggunakan plugin secure storage/Android Keystore;
- jangan menyimpan password;
- hapus token saat logout atau menerima status 401 yang tidak dapat diperbarui.

### 4.5 Permission

API wajib memeriksa permission di server. Menyembunyikan tombol di Android bukan pengamanan.

Contoh permission:

```text
barang.view
barang.create
barang.update
barang.delete
penjualan.view
penjualan.checkout
pembelian.view
pembelian.checkout
po.approve
hutang.pay
laporan.view
laporan.export
keuangan.view
keuangan.create
```

---

## 5. Daftar Endpoint API V1

## 5.1 Sistem

```text
GET /api_v1/health
GET /api_v1/version
GET /api_v1/config/mobile
```

`config/mobile` dapat memuat nama toko, logo, metode pembayaran, batas upload, feature flags, dan versi minimum aplikasi.

## 5.2 Dashboard

```text
GET /api_v1/dashboard
GET /api_v1/dashboard/sales-summary?period=today
GET /api_v1/dashboard/cash-summary
GET /api_v1/dashboard/low-stock
GET /api_v1/dashboard/debts
```

Data dashboard harus diringkas di backend, bukan mengirim seluruh tabel lalu dihitung di ponsel.

## 5.3 Barang

```text
GET    /api_v1/barang
POST   /api_v1/barang
GET    /api_v1/barang/{id}
PUT    /api_v1/barang/{id}
DELETE /api_v1/barang/{id}
GET    /api_v1/barang/{id}/stock-history
POST   /api_v1/barang/{id}/image
DELETE /api_v1/barang/{id}/image
```

Filter:

```text
search
kategori_id
satuan_id
status
stock_status
min_stock
max_stock
```

## 5.4 Jasa

```text
GET    /api_v1/jasa
POST   /api_v1/jasa
GET    /api_v1/jasa/{id}
PUT    /api_v1/jasa/{id}
DELETE /api_v1/jasa/{id}
```

## 5.5 Master pendukung

```text
/api_v1/kategori
/api_v1/satuan
/api_v1/pelanggan
/api_v1/supplier
/api_v1/diskon
```

Masing-masing menyediakan:

```text
GET    /
POST   /
GET    /{id}
PUT    /{id}
DELETE /{id}
```

Tambahkan endpoint pencarian ringan untuk autocomplete:

```text
GET /api_v1/lookups/barang?search=
GET /api_v1/lookups/jasa?search=
GET /api_v1/lookups/pelanggan?search=
GET /api_v1/lookups/supplier?search=
```

## 5.6 Penjualan

```text
POST /api_v1/penjualan/quote
POST /api_v1/penjualan/checkout
GET  /api_v1/penjualan
GET  /api_v1/penjualan/{id}
GET  /api_v1/penjualan/{id}/receipt
POST /api_v1/penjualan/{id}/cancel
```

Endpoint `quote` menghitung subtotal, diskon, pajak bila ada, total, dan validasi stok tanpa menyimpan transaksi final.

Payload checkout:

```json
{
  "pelanggan_id": 1,
  "payment_method": "cash",
  "paid_amount": 150000,
  "discount_id": null,
  "notes": "",
  "idempotency_key": "uuid",
  "items": [
    {
      "item_type": "barang",
      "item_id": 10,
      "quantity": 2,
      "discount": 0
    },
    {
      "item_type": "jasa",
      "item_id": 3,
      "quantity": 1,
      "discount": 0
    }
  ]
}
```

`idempotency_key` wajib untuk mencegah transaksi ganda saat koneksi buruk atau tombol checkout ditekan berulang.

## 5.7 Hold transaksi

Dua opsi implementasi:

1. Hold lokal di perangkat untuk fase awal.
2. Hold tersimpan di server agar dapat dilanjutkan dari perangkat lain.

Target final memakai server:

```text
GET    /api_v1/penjualan/holds
POST   /api_v1/penjualan/holds
GET    /api_v1/penjualan/holds/{id}
PUT    /api_v1/penjualan/holds/{id}
DELETE /api_v1/penjualan/holds/{id}
POST   /api_v1/penjualan/holds/{id}/checkout
```

## 5.8 Pembelian

```text
POST /api_v1/pembelian/quote
POST /api_v1/pembelian/checkout
GET  /api_v1/pembelian
GET  /api_v1/pembelian/{id}
GET  /api_v1/pembelian/{id}/receipt
POST /api_v1/pembelian/{id}/cancel
```

Checkout harus membedakan:

- cash/lunas;
- termin/hutang;
- DP;
- sumber akun kas;
- penerimaan barang final.

## 5.9 Purchase Order

```text
GET  /api_v1/purchase-orders
POST /api_v1/purchase-orders
GET  /api_v1/purchase-orders/{id}
PUT  /api_v1/purchase-orders/{id}
POST /api_v1/purchase-orders/{id}/submit
POST /api_v1/purchase-orders/{id}/approve
POST /api_v1/purchase-orders/{id}/reject
POST /api_v1/purchase-orders/{id}/realize
POST /api_v1/purchase-orders/{id}/cancel
```

PO tidak boleh langsung menambah stok atau mengurangi kas. Stok dan kas berubah ketika PO direalisasikan menjadi pembelian/receipt.

## 5.10 Hutang supplier

```text
GET  /api_v1/supplier-debts
GET  /api_v1/supplier-debts/{id}
GET  /api_v1/supplier-debts/{id}/payments
POST /api_v1/supplier-debts/{id}/payments
```

Pembayaran harus memakai database transaction dan mengunci saldo hutang yang sedang diperbarui.

## 5.11 Keuangan

```text
GET  /api_v1/finance/accounts
GET  /api_v1/finance/entries
POST /api_v1/finance/entries
GET  /api_v1/finance/entries/{id}
POST /api_v1/finance/entries/{id}/reverse
GET  /api_v1/finance/cash-balance
```

Data transaksi penjualan/pembelian yang sudah membuat ledger tidak boleh dibuat ulang oleh aplikasi Android.

## 5.12 Laporan

```text
GET /api_v1/reports/sales
GET /api_v1/reports/purchases
GET /api_v1/reports/purchase-orders
GET /api_v1/reports/supplier-debts
GET /api_v1/reports/finance
GET /api_v1/reports/profit-loss
GET /api_v1/reports/capital
GET /api_v1/reports/top-products
GET /api_v1/reports/stock
GET /api_v1/reports/summary
```

Filter standar:

```text
start_date
end_date
period
barang_id
pelanggan_id
supplier_id
payment_method
status
page
per_page
```

Export:

```text
GET /api_v1/reports/{report}/export?format=pdf
GET /api_v1/reports/{report}/export?format=csv
```

Aplikasi Android menampilkan ringkasan, grafik, dan daftar. PDF dapat diunduh lalu dibuka melalui native file viewer/share sheet.

## 5.13 Profil dan toko

```text
GET  /api_v1/profile
PUT  /api_v1/profile
POST /api_v1/profile/avatar
GET  /api_v1/store
PUT  /api_v1/store
POST /api_v1/store/logo
```

Pengaturan toko hanya tampil untuk role yang berhak.

## 5.14 Media

```text
POST   /api_v1/media
GET    /api_v1/media/{id}
DELETE /api_v1/media/{id}
```

Upload memakai `multipart/form-data`, MIME whitelist, ukuran maksimum, nama file acak, dan ownership/permission check.

---

## 6. Keamanan API

Wajib diterapkan:

- HTTPS untuk development dan production;
- Bearer token dengan token hash di database;
- expiration dan revoke token;
- rate limit login dan endpoint sensitif;
- audit log login, logout, gagal login, transaksi, perubahan stok, pembayaran hutang, dan reversal;
- server-side permission;
- prepared statement/binding;
- validasi MIME dan ukuran file;
- batas pagination;
- idempotency checkout;
- database transaction untuk checkout, realisasi PO, pembayaran hutang, dan posting ledger;
- CORS hanya untuk origin development web yang diperlukan; aplikasi native Capacitor tidak dijadikan alasan membuka `*` sembarangan;
- jangan percaya harga, subtotal, diskon final, saldo kas, stok, atau role yang dikirim client;
- jangan mengirim password hash dan field internal sensitif;
- response production tidak menampilkan stack trace atau query SQL.

Tambahkan header:

```text
X-Request-Id
X-API-Version: 1
Cache-Control: no-store
```

---

## 7. Struktur Project Android

Disarankan membuat aplikasi mobile dalam folder terpisah agar tidak bercampur dengan PHP:

```text
mobile/
  android/
  src/
    api/
      client.ts
      endpoints.ts
      auth.ts
      barang.ts
      transaksi.ts
      laporan.ts
    components/
    layouts/
    pages/
      auth/
      dashboard/
      barang/
      jasa/
      pelanggan/
      supplier/
      penjualan/
      pembelian/
      purchase-order/
      hutang/
      keuangan/
      laporan/
      profile/
    stores/
    router/
    services/
    composables/
    assets/
    styles/
    types/
  capacitor.config.ts
  vite.config.ts
  package.json
```

Pilihan frontend yang direkomendasikan:

```text
Vue 3 + TypeScript + Vite + Capacitor
```

Alasan:

- ringan;
- cepat dibuat;
- cocok untuk CRUD dan dashboard;
- komponen mudah dipisah;
- TypeScript membantu menjaga kontrak API;
- tidak mengubah view web desktop yang sudah ada.

Tidak memakai halaman Blade/PHP web sebagai UI aplikasi Android.

---

## 8. Tampilan Android

### 8.1 Prinsip

- mobile-first;
- seluruh layar dibuat khusus untuk ponsel;
- navigasi bawah untuk fitur utama;
- drawer/menu tambahan sesuai role;
- App Bar Android;
- form memakai komponen sentuh minimal tinggi 44–48 px;
- skeleton loading;
- empty state;
- pull to refresh;
- infinite scroll atau pagination;
- dialog konfirmasi untuk transaksi final;
- snackbar/toast yang tidak menutupi tombol;
- dukungan dark mode opsional;
- tidak menampilkan tabel desktop lebar.

### 8.2 Navigasi utama

Contoh bottom navigation:

```text
Dashboard
Penjualan
Pembelian
Laporan
Lainnya
```

Menu `Lainnya`:

```text
Barang
Jasa
Pelanggan
Supplier
PO
Hutang
Keuangan
Profil
Pengaturan
Logout
```

Menu disaring berdasarkan permission dari `/auth/me`.

### 8.3 Halaman penjualan

- search barang/jasa;
- scan barcode;
- kategori horizontal;
- keranjang sebagai bottom sheet;
- ubah qty;
- diskon;
- pilih pelanggan;
- pilih metode pembayaran;
- hitung kembali melalui endpoint quote;
- checkout dengan idempotency key;
- tampilkan struk;
- share/cetak struk.

### 8.4 Halaman laporan

- filter tanggal;
- kartu ringkasan;
- grafik sederhana;
- daftar transaksi;
- drill-down detail;
- export/share PDF atau CSV;
- hindari grafik terlalu berat dan tabel desktop.

---

## 9. Plugin Capacitor

Plugin inti yang diperlukan:

```text
@capacitor/core
@capacitor/cli
@capacitor/android
@capacitor/app
@capacitor/device
@capacitor/network
@capacitor/preferences
@capacitor/status-bar
@capacitor/splash-screen
@capacitor/keyboard
@capacitor/filesystem
@capacitor/share
@capacitor/browser
@capacitor/camera
```

Tambahan sesuai kebutuhan:

- secure storage berbasis Android Keystore;
- barcode scanner;
- printer Bluetooth/ESC-POS;
- local notifications;
- biometric authentication;
- native file opener.

Plugin tambahan harus dipilih yang aktif dirawat dan kompatibel dengan versi Capacitor yang digunakan saat implementasi.

---

## 10. Konfigurasi Environment Mobile

```text
mobile/.env.development
VITE_API_BASE_URL=https://lintaspos.ddev.site/api_v1

mobile/.env.production
VITE_API_BASE_URL=https://lintaspos.co-id.id/api_v1
```

Jangan hard-code domain dalam setiap file endpoint.

Untuk Android emulator, sertifikat lokal DDEV dapat memerlukan konfigurasi trust khusus. Pengujian perangkat fisik lebih aman menggunakan domain development yang dapat diakses jaringan atau tunnel HTTPS terpercaya. Jangan mematikan validasi SSL di build production.

---

## 11. Offline dan Koneksi Buruk

Fase pertama:

- aplikasi membutuhkan internet untuk transaksi final;
- cache data lookup dan daftar terakhir untuk mempercepat tampilan;
- draft keranjang boleh tersimpan lokal;
- checkout tidak dianggap berhasil sebelum server mengembalikan sukses;
- tampilkan status offline yang jelas;
- tombol retry tidak boleh membuat transaksi ganda karena menggunakan idempotency key.

Fase lanjutan opsional:

- antrean draft offline;
- sinkronisasi background;
- conflict resolution;
- version field/updated_at untuk mendeteksi data berubah.

Jangan membuat transaksi penjualan final sepenuhnya offline pada fase awal karena sinkronisasi stok, kas, diskon, dan nomor transaksi berisiko bentrok.

---

## 12. Kontrak Data dan Dokumentasi

Buat dokumentasi OpenAPI:

```text
docs/openapi/api_v1.yaml
```

Gunakan sebagai sumber kontrak untuk:

- payload request;
- response;
- error;
- tipe data TypeScript;
- dokumentasi endpoint;
- pengujian Postman/Bruno;
- sinkronisasi backend dan mobile.

Tambahkan koleksi pengujian:

```text
docs/api/LintasPOS_API_V1.postman_collection.json
```

atau Bruno:

```text
docs/api/bruno/
```

---

## 13. Tahapan Implementasi

## Fase 0 — Audit dan pemisahan logika

- inventaris controller, tabel, route, permission, dan transaksi;
- identifikasi logika yang masih tertanam di controller web;
- pindahkan logika kritis ke service tanpa mengubah perilaku web;
- tambahkan integration test untuk stok, kas, hutang, dan PO;
- pastikan aplikasi web tetap berjalan.

**Output:** service bisnis reusable dan daftar kontrak data.

## Fase 1 — Fondasi API

- `routes/api_v1.php`;
- bootstrap route API;
- `ApiResponse`;
- exception/error handler JSON;
- request ID;
- Bearer token;
- middleware auth, permission, dan rate limit;
- endpoint health, login, logout, me;
- migration `api_tokens`;
- OpenAPI awal.

**Output:** login Android dan endpoint terlindungi berjalan.

## Fase 2 — Master data CRUD

- kategori;
- satuan;
- barang;
- jasa;
- pelanggan;
- supplier;
- diskon;
- lookup endpoint;
- upload media;
- pagination, search, filter, sort;
- test permission dan validasi.

**Output:** seluruh master data dapat dikelola dari Android.

## Fase 3 — Kerangka aplikasi Android

- Vue 3 + TypeScript + Vite;
- Capacitor Android;
- environment API;
- router;
- auth store;
- secure token storage;
- API client/interceptor;
- global loading dan error state;
- layout Android;
- role-based navigation;
- splash screen dan icon.

**Output:** APK development dapat login dan membuka dashboard/master data.

## Fase 4 — Penjualan

- pencarian barang/jasa;
- barcode;
- keranjang;
- quote;
- pelanggan cepat;
- diskon;
- metode pembayaran;
- idempotent checkout;
- riwayat penjualan;
- detail dan struk;
- hold transaksi server.

**Output:** proses penjualan Android lengkap dan konsisten dengan web.

## Fase 5 — Pembelian, PO, dan hutang

- cart pembelian;
- supplier cepat;
- cash/termin/DP;
- checkout;
- PO lifecycle;
- approval berdasarkan permission;
- realisasi PO;
- daftar hutang;
- detail dan pembayaran hutang.

**Output:** alur purchasing lengkap.

## Fase 6 — Keuangan dan laporan

- akun keuangan;
- pemasukan/pengeluaran manual;
- ledger;
- saldo kas;
- seluruh laporan utama;
- filter;
- grafik;
- export PDF/CSV;
- share file.

**Output:** dashboard manajemen dan laporan tersedia di Android.

## Fase 7 — Hardening dan rilis

- test unit, integration, dan end-to-end;
- test role/permission;
- test koneksi buruk dan retry;
- test transaksi ganda;
- test upload;
- audit token storage;
- audit log;
- optimasi bundle;
- build signed APK/AAB;
- versioning dan release notes;
- backup dan rollback plan.

**Output:** build production siap distribusi.

---

## 14. Pengujian Wajib

### Backend

- login benar/salah;
- token expired/revoked;
- permission ditolak;
- validasi CRUD;
- pagination dan search;
- upload MIME palsu;
- stok tidak boleh minus;
- checkout ganda dengan idempotency key;
- rollback ketika ledger gagal;
- pembelian cash gagal bila saldo tidak cukup;
- pembelian termin membuat hutang;
- pembayaran hutang mengurangi kas dan saldo hutang;
- PO tidak mengubah stok sebelum realisasi;
- laporan sama dengan sumber transaksi.

### Android

- fresh install;
- restore session;
- logout;
- token expired;
- offline saat membuka daftar;
- offline saat checkout;
- retry checkout;
- rotate/recreate activity;
- keyboard pada form;
- permission kamera/file;
- upload gambar;
- download dan share laporan;
- back button Android;
- deep link bila nanti digunakan.

---

## 15. Definition of Done

Sebuah modul dianggap selesai bila:

- endpoint terdokumentasi di OpenAPI;
- response mengikuti format API V1;
- validasi server tersedia;
- permission server tersedia;
- integration test lulus;
- halaman Android memiliki loading, empty, error, dan success state;
- tidak ada perhitungan finansial final yang hanya dilakukan di client;
- tidak ada query langsung dari Android ke database;
- tidak ada password/token mentah di log;
- web lama tidak rusak;
- hasil transaksi dan laporan konsisten antara web dan Android.

---

## 16. Batasan Scope Awal

Tidak dikerjakan pada rilis pertama kecuali benar-benar dibutuhkan:

- operasi transaksi final penuh secara offline;
- sinkronisasi multi-master;
- Menu Generator dari Android;
- pengelolaan permission kompleks dari Android;
- audit keamanan lengkap dari Android;
- dashboard super-admin teknis;
- kitchen display/restoran;
- multi-warehouse tingkat lanjut;
- integrasi payment gateway otomatis.

---

## 17. Urutan Pengerjaan yang Disarankan untuk Codex

Codex tidak boleh langsung membuat seluruh endpoint sekaligus.

Urutan aman:

1. audit repository dan tulis daftar tabel serta controller terkait;
2. buat branch khusus;
3. implementasikan fondasi API dan auth;
4. tambahkan test;
5. implementasikan satu CRUD sederhana, misalnya kategori;
6. review pola dan kontrak response;
7. lanjutkan master data lain;
8. refactor service transaksi;
9. implementasikan penjualan;
10. implementasikan pembelian/PO/hutang;
11. implementasikan keuangan/laporan;
12. buat aplikasi Capacitor per modul;
13. jalankan regression test web pada setiap fase.

Setiap fase harus menghasilkan commit kecil dan dapat diuji. Jangan membuat satu commit raksasa berisi API, Android, migrasi, dan refactor seluruh aplikasi sekaligus—itu bukan commit, itu lokasi kejadian perkara.

---

## 18. Checklist Awal

```text
[ ] Audit schema database terbaru
[ ] Audit seluruh route web
[ ] Audit role dan permission
[ ] Audit alur stok
[ ] Audit alur kas/ledger
[ ] Audit alur PO dan hutang
[ ] Buat service layer reusable
[ ] Buat routes/api_v1.php
[ ] Buat ApiResponse
[ ] Buat migration api_tokens
[ ] Buat middleware Bearer token
[ ] Buat OpenAPI
[ ] Buat endpoint auth
[ ] Buat CRUD master data
[ ] Buat endpoint transaksi
[ ] Buat endpoint laporan
[ ] Scaffold Vue + TypeScript + Capacitor
[ ] Buat secure token storage
[ ] Buat UI Android per modul
[ ] Test perangkat fisik
[ ] Build signed APK/AAB
```

---

## 19. Hasil Akhir yang Diharapkan

```text
LintasPOS Web
  -> tetap digunakan untuk desktop dan administrasi lengkap

LintasPOS API V1
  -> satu pintu JSON untuk aplikasi Android
  -> memakai service bisnis yang sama dengan web

LintasPOS Android
  -> UI mobile khusus melalui Capacitor
  -> CRUD dan transaksi melalui API V1
  -> laporan ringkas, detail, export, dan share
  -> aman berdasarkan token dan permission
```

Arsitektur ini mempertahankan AitiCore Flex sebagai backend utama, menghindari duplikasi aturan bisnis, dan memungkinkan Android berkembang tanpa mengorbankan aplikasi web yang sudah berjalan.
