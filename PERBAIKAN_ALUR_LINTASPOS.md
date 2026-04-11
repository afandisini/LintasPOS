# PERBAIKAN ALUR LINTASPOS (PHASE 1)

## TUJUAN

Merancang ulang alur:

- Pembelian
- PO (Purchase Order)
- Hutang Supplier
- Pembayaran Hutang
- Integrasi Kas/Saldo

Agar:

- Stok benar
- Kas benar
- Hutang jelas
- Laporan tidak kacau

---

## MASALAH SAAT INI

> Status terkini setelah perapihan flow transaksi:
>
> - alur inti penjualan, pembelian, hutang supplier, dan kas sudah jauh lebih sinkron
> - PO sudah tidak boleh diperlakukan sebagai pelunasan final
> - laporan sudah dipisah antara pembelian final, PO, dan hutang
> - sisa penyimpangan utama ada di edge case dan penyempurnaan laporan

1. Pembelian cash harus benar-benar mengurangi kas
2. Pembelian hutang harus membentuk hutang supplier yang bisa dibayar bertahap
3. PO tidak boleh dianggap pembelian final
4. Approve PO tidak boleh dianggap pelunasan
5. Hutang supplier harus punya histori pembayaran nyata
6. Laporan harus memisahkan pembelian final, PO, dan hutang
7. Ledger harus mencerminkan cashflow nyata, bukan status transaksi

---

## KEPUTUSAN ALUR FINAL

### PO

- Tidak mengubah stok
- Tidak mengubah kas
- Tidak membuat hutang

### Pembelian

- Stok bertambah saat barang diterima
- Bisa:
  - Lunas
  - Partial
  - Hutang

### Hutang Supplier

- Terbentuk dari pembelian yang belum lunas

### Pembayaran Hutang

- Mengurangi kas
- Mengurangi hutang
- Tidak mengubah stok

### Laporan

- Laporan pembelian hanya berisi receipt/pembelian final
- Laporan PO hanya berisi dokumen PO
- Laporan hutang hanya berisi outstanding debt
- Laporan kas hanya berisi mutasi kas nyata

---

## STATUS IMPLEMENTASI SAAT INI

### Sudah sinkron

- Penjualan menurunkan stok saat checkout
- Penjualan mencatat pemasukan kas ke ledger
- Pembelian cash memotong kas
- Pembelian hutang membentuk hutang supplier
- Pembayaran hutang supplier mengurangi kas dan mengurangi hutang
- PO tidak lagi diperlakukan sebagai pelunasan otomatis
- Laporan sudah dipisah menjadi penjualan, pembelian, PO, dan hutang

### Masih perlu dirapikan

- Retur pembelian dan retur penjualan
- Reversal transaksi final yang sudah mempengaruhi stok/kas
- Partial receipt untuk PO yang datang sebagian
- Overdue / aging hutang supplier
- Validasi stock movement yang lebih ketat pada update transaksi lama
- Penyempurnaan sinkronisasi laporan mutasi dan ringkasan kas

### Audit prioritas file yang masih berpotensi menyimpang

1. `app/Controllers/TransaksiController.php`
   - Masih ada guard role yang hardcoded.
   - `guardPembelian()` masih bergantung ke `canAccessPenjualan()`.
   - `canManagePo()` dan `canViewHargaModal()` belum membaca matrix akses dari `user_fitur_akses`.
   - Ini prioritas paling tinggi karena menyentuh akses nyata ke transaksi.

2. `app/Controllers/LaporanController.php`
   - `canAccessLaporan()` dan `canViewModal()` masih hardcoded ke daftar role.
   - Perlu dipastikan konsisten dengan role `Owner`, `SPV`, dan default permission yang sudah diseed.
   - Ini prioritas kedua karena mempengaruhi siapa yang bisa melihat laporan sensitif.

3. `app/Controllers/HakAksesController.php`
   - Sudah mendukung `Owner`, tetapi fallback full-access masih berbasis role string.
   - Aman untuk fase sekarang, tetapi idealnya nanti disatukan ke satu sumber izin yang sama.

4. `app/Controllers/UsersController.php`
   - Struktur data role sudah benar.
   - Yang perlu dipantau hanyalah sinkronisasi label role dan seeding `user_fitur_akses`.

### Urutan benerin paling aman

1. Kunci laporan dan role akses controller.
2. Audit transaksi yang masih memakai role hardcoded.
3. Baru lanjut edge case: retur, reversal, partial receipt, dan aging hutang.

---

## TASK UNTUK CODEX

### 1. RAPIKAN LAPORAN TERLEBIH DAHULU

Paling mudah dan paling aman:

- pisahkan tampilan laporan pembelian, PO, dan hutang
- rapikan filter periode, supplier, dan status
- pastikan export PDF konsisten dengan tabel layar

### 2. VALIDASI PEMBELIAN CASH

- Wajib cek saldo kas
- Jika cukup -> kurangi kas
- Jika tidak -> tolak transaksi

### 3. VALIDASI PEMBELIAN HUTANG / TERMIN

- Simpan:
  - paid_amount
  - remaining_amount
- Jika DP:
  - kurangi kas
- Buat data hutang supplier

### 4. BUAT MODUL HUTANG SUPPLIER

- Tabel: supplier_debts
- Tabel: supplier_debt_payments
- Feature:
  - list hutang
  - detail hutang
  - pembayaran
  - histori

### 5. RAPIKAN PO

- Approve != Lunas
- Approve != Kas keluar
- PO tidak masuk laporan pembelian

### 6. RAPIKAN REPORTING & CASHFLOW

- Pembelian hanya dari transaksi nyata
- PO pending tidak ikut
- Hutang berdasarkan remaining_amount

### 7. TANGANI EDGE CASE

- retur pembelian
- retur penjualan
- partial receipt PO
- overdue hutang supplier
- reversal transaksi final
- pembulatan pembayaran hutang

---

## CHECKLIST VERIFIKASI

- [x] Pembelian cash mengurangi kas
- [x] Pembelian hutang membentuk hutang
- [x] Pembayaran hutang mengurangi kas
- [x] Hutang berkurang setelah bayar
- [x] PO tidak dianggap lunas
- [x] Laporan tidak tercampur
- [ ] Retur dan reversal transaksi final
- [ ] Partial receipt PO
- [ ] Overdue / aging hutang supplier
- [ ] Audit trail perubahan transaksi

---

## OUTPUT CODEX/AMAZON Q

Wajib format:

# ANALYZE

# PLAN

# EXECUTE

# DATABASE

# VERIFY

# NOTES

---

## CATATAN

Fokus phase 1:

- perbaiki logika
- jangan overhaul besar
- jangan over-engineering

---

## URUTAN KERJA BERIKUTNYA

1. Kunci laporan agar tidak menampilkan data ganda / tercampur.
2. Tambahkan edge case paling aman: retur dan reversal.
3. Rapikan PO partial receipt.
4. Tambahkan overdue hutang supplier.
5. Baru evaluasi refactor tabel PO terpisah jika masih diperlukan.
