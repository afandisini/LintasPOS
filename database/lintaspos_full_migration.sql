-- Full schema migration snapshot for LintasPos
-- Generated on 2026-04-08 18:14:54
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- TABLE: hak_akses
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `hak_akses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hak_akses` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- TABLE: filemanager
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `filemanager` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `create_time` datetime DEFAULT NULL COMMENT 'Create Time',
  `name` varchar(255) DEFAULT NULL,
  `module` varchar(64) NOT NULL DEFAULT 'general',
  `ref_id` varchar(64) NOT NULL DEFAULT '0',
  `path` varchar(500) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `mime_type` varchar(150) DEFAULT NULL,
  `extension` varchar(20) DEFAULT NULL,
  `size_bytes` bigint unsigned DEFAULT NULL,
  `visibility` varchar(20) NOT NULL DEFAULT 'private',
  `uploaded_by` int DEFAULT NULL,
  `checksum_sha1` char(40) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_filemanager_module_ref` (`module`,`ref_id`),
  KEY `idx_filemanager_deleted_at` (`deleted_at`),
  KEY `idx_filemanager_uploaded_by` (`uploaded_by`),
  KEY `idx_filemanager_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- TABLE: users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telepon` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_general_ci,
  `avatar` int unsigned DEFAULT NULL,
  `user` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pass` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `hak_akses_id` int DEFAULT NULL,
  `active` varchar(11) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_users_avatar` (`avatar`),
  KEY `idx_users_hak_akses_id` (`hak_akses_id`),
  CONSTRAINT `fk_users_avatar_filemanager` FOREIGN KEY (`avatar`) REFERENCES `filemanager` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- TABLE: toko
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `toko` (
  `id` int NOT NULL AUTO_INCREMENT,
  `app_name` varchar(255) NOT NULL DEFAULT 'LintasPos',
  `nama_toko` varchar(255) NOT NULL,
  `alamat_toko` text NOT NULL,
  `tlp` varchar(255) NOT NULL,
  `nama_pemilik` varchar(255) NOT NULL,
  `logo` int unsigned DEFAULT NULL,
  `icons` varchar(255) DEFAULT '255',
  PRIMARY KEY (`id`),
  KEY `idx_toko_logo` (`logo`),
  CONSTRAINT `fk_toko_logo_filemanager` FOREIGN KEY (`logo`) REFERENCES `filemanager` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: kategori
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kategori` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) DEFAULT NULL,
  `ket` text,
  `tgl_input` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: satuan
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `satuan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(255) DEFAULT NULL,
  `ket` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: supplier
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supplier` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_supplier` varchar(255) DEFAULT NULL,
  `alamat_supplier` varchar(255) DEFAULT NULL,
  `telepon_supplier` varchar(25) DEFAULT NULL,
  `email_supplier` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_nama` (`nama_supplier`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: pelanggan
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pelanggan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode_pelanggan` varchar(255) DEFAULT NULL,
  `nama_pelanggan` varchar(255) DEFAULT NULL,
  `alamat_pelanggan` text,
  `telepon_pelanggan` varchar(25) DEFAULT NULL,
  `email_pelanggan` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pelanggan_kode` (`kode_pelanggan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: barang
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `barang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_barang` varchar(255) NOT NULL,
  `kategori_id` int NOT NULL,
  `satuan_id` int DEFAULT NULL,
  `gambar` int unsigned DEFAULT NULL,
  `nama_barang` varchar(255) DEFAULT NULL,
  `merk` varchar(255) NOT NULL,
  `harga_beli` int NOT NULL,
  `harga_jual` int NOT NULL,
  `stok` int NOT NULL,
  `exp_date` date DEFAULT NULL,
  `tgl_input` date DEFAULT NULL,
  `tgl_update` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_barang_id_barang` (`id_barang`),
  KEY `idx_barang_nama_barang` (`nama_barang`),
  KEY `idx_barang_gambar` (`gambar`),
  KEY `idx_barang_kategori_id` (`kategori_id`),
  KEY `idx_barang_satuan_id` (`satuan_id`),
  CONSTRAINT `fk_barang_gambar_filemanager` FOREIGN KEY (`gambar`) REFERENCES `filemanager` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: jasa
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jasa` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_jasa` varchar(255) NOT NULL,
  `kategori_id` int NOT NULL,
  `satuan_id` int DEFAULT NULL,
  `gambar_img` int unsigned DEFAULT NULL,
  `nama` varchar(255) DEFAULT NULL,
  `harga` int NOT NULL,
  `tgl_input` date DEFAULT NULL,
  `tgl_update` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_barang_id_barang` (`id_jasa`),
  KEY `idx_barang_nama_barang` (`nama`),
  KEY `idx_barang_gambar` (`gambar_img`),
  KEY `idx_barang_kategori_id` (`kategori_id`),
  KEY `idx_barang_satuan_id` (`satuan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: diskon
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `diskon` (
  `id` int NOT NULL AUTO_INCREMENT,
  `barang_id` varchar(255) DEFAULT NULL,
  `diskon` int NOT NULL,
  `tgl_start` date DEFAULT NULL,
  `tgl_end` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_diskon_id_barang` (`barang_id`),
  KEY `idx_diskon_tanggal` (`tgl_start`,`tgl_end`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: keranjang
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `keranjang` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_barang` varchar(255) NOT NULL,
  `item_type` enum('barang','jasa') NOT NULL DEFAULT 'barang',
  `item_ref_id` int DEFAULT NULL,
  `id_member` int NOT NULL,
  `nama_barang` varchar(255) DEFAULT NULL,
  `diskon` int DEFAULT '0',
  `jumlah` varchar(255) NOT NULL,
  `beli` int NOT NULL,
  `jual` int NOT NULL,
  `tanggal_input` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_keranjang_member_barang` (`id_member`,`id_barang`),
  KEY `idx_keranjang_member_type_ref` (`id_member`,`item_type`,`item_ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: keranjang_beli
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `keranjang_beli` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_barang` varchar(255) NOT NULL,
  `id_member` int NOT NULL,
  `nama_barang` varchar(255) DEFAULT NULL,
  `jumlah` varchar(255) NOT NULL,
  `beli` int NOT NULL,
  `jual` int NOT NULL,
  `tanggal_input` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_keranjang_beli_member_barang` (`id_member`,`id_barang`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: penjualan_hold
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `penjualan_hold` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hold_code` varchar(40) NOT NULL,
  `id_member` int NOT NULL,
  `id_pelanggan` int DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `catatan` text,
  `status` enum('hold','resumed','cancelled') NOT NULL DEFAULT 'hold',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_penjualan_hold_code` (`hold_code`),
  KEY `idx_penjualan_hold_member_status` (`id_member`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: penjualan_hold_items
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `penjualan_hold_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hold_id` int NOT NULL,
  `item_type` enum('barang','jasa') NOT NULL DEFAULT 'barang',
  `item_ref_id` int DEFAULT NULL,
  `item_code` varchar(255) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `qty` int NOT NULL DEFAULT '1',
  `beli` int NOT NULL DEFAULT '0',
  `jual` int NOT NULL DEFAULT '0',
  `diskon` int NOT NULL DEFAULT '0',
  `total` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_penjualan_hold_items_hold_id` (`hold_id`),
  CONSTRAINT `fk_penjualan_hold_items_hold_id` FOREIGN KEY (`hold_id`) REFERENCES `penjualan_hold` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: penjualan
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `penjualan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_trx` varchar(255) DEFAULT NULL,
  `id_member` int NOT NULL,
  `id_pelanggan` int NOT NULL,
  `jumlah` int NOT NULL,
  `beli` int DEFAULT NULL,
  `total` int NOT NULL,
  `bayar` int NOT NULL,
  `status_bayar` enum('Lunas','Hutang') DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `keterangan` text,
  `tanggal_input` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `periode` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_penjualan_no_trx` (`no_trx`),
  KEY `idx_penjualan_member_pelanggan` (`id_member`,`id_pelanggan`),
  KEY `idx_penjualan_tanggal_periode` (`tanggal_input`,`periode`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: penjualan_detail
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `penjualan_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_trx` varchar(255) DEFAULT NULL,
  `id_barang` int NOT NULL,
  `item_type` enum('barang','jasa') NOT NULL DEFAULT 'barang',
  `idb` varchar(255) DEFAULT NULL,
  `nama_barang` varchar(255) DEFAULT NULL,
  `beli` int NOT NULL,
  `jual` int NOT NULL,
  `qty` int NOT NULL,
  `diskon` int NOT NULL,
  `total` int NOT NULL,
  `status_bayar` varchar(50) DEFAULT NULL,
  `tgl_input` date DEFAULT NULL,
  `periode` varchar(255) DEFAULT NULL,
  `id_member` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_penjualan_detail_no_trx` (`no_trx`),
  KEY `idx_penjualan_detail_id_barang` (`id_barang`),
  KEY `idx_penjualan_detail_periode` (`periode`),
  CONSTRAINT `fk_penjualan_detail_no_trx` FOREIGN KEY (`no_trx`) REFERENCES `penjualan` (`no_trx`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: pembelian
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pembelian` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nm_supplier` varchar(255) DEFAULT NULL,
  `no_trx` varchar(255) DEFAULT NULL,
  `po_no_reg` varchar(64) DEFAULT NULL,
  `id_member` int NOT NULL,
  `jumlah` int NOT NULL,
  `beli` int DEFAULT NULL,
  `keterangan` text,
  `status_bayar` enum('Lunas','Hutang') DEFAULT NULL,
  `po_status` enum('pending','diterima','ditolak') DEFAULT NULL,
  `po_review_note` text,
  `po_review_by` int DEFAULT NULL,
  `po_review_at` datetime DEFAULT NULL,
  `po_deleted_at` timestamp NULL DEFAULT NULL,
  `po_deleted_by` int DEFAULT NULL,
  `tanggal_input` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `periode` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pembelian_no_trx` (`no_trx`),
  KEY `idx_pembelian_member` (`id_member`),
  KEY `idx_pembelian_tanggal_periode` (`tanggal_input`,`periode`),
  KEY `idx_pembelian_po_status` (`po_status`),
  KEY `idx_pembelian_po_deleted_at` (`po_deleted_at`),
  KEY `idx_pembelian_po_no_reg` (`po_no_reg`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: pembelian_detail
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pembelian_detail` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_trx` varchar(255) DEFAULT NULL,
  `id_barang` int NOT NULL,
  `idb` varchar(255) DEFAULT NULL,
  `nama_barang` varchar(255) DEFAULT NULL,
  `beli` int NOT NULL,
  `qty` int NOT NULL,
  `total` int NOT NULL,
  `tgl_input` date DEFAULT NULL,
  `status_bayar` enum('Lunas','Hutang') DEFAULT NULL,
  `periode` varchar(255) DEFAULT NULL,
  `id_member` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pembelian_detail_no_trx` (`no_trx`),
  KEY `idx_pembelian_detail_id_barang` (`id_barang`),
  KEY `idx_pembelian_detail_periode` (`periode`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: akun_keuangan
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `akun_keuangan` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'Primary Key',
  `create_time` datetime DEFAULT NULL COMMENT 'Create Time',
  `name` varchar(255) DEFAULT NULL,
  `kode_akun` varchar(30) DEFAULT NULL,
  `nama_akun` varchar(150) DEFAULT NULL,
  `kategori` enum('aset','liabilitas','ekuitas','pendapatan','beban','lainnya') NOT NULL DEFAULT 'lainnya',
  `tipe_arus` enum('pemasukan','pengeluaran','netral') NOT NULL DEFAULT 'netral',
  `is_kas` tinyint(1) NOT NULL DEFAULT '0',
  `is_modal` tinyint(1) NOT NULL DEFAULT '0',
  `deskripsi` text,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_akun_keuangan_kode_akun` (`kode_akun`),
  KEY `idx_akun_keuangan_kategori` (`kategori`),
  KEY `idx_akun_keuangan_tipe_arus` (`tipe_arus`),
  KEY `idx_akun_keuangan_is_kas` (`is_kas`),
  KEY `idx_akun_keuangan_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ---------------------------------------------------------------------------
-- TABLE: keuangan
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `keuangan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tanggal` date DEFAULT NULL,
  `no_ref` varchar(64) DEFAULT NULL,
  `akun_keuangan_id` int DEFAULT NULL,
  `jenis` enum('debit','kredit') NOT NULL DEFAULT 'debit',
  `tipe_arus` enum('pemasukan','pengeluaran','netral') NOT NULL DEFAULT 'pengeluaran',
  `nominal` decimal(18,2) NOT NULL DEFAULT '0.00',
  `saldo_setelah` decimal(18,2) DEFAULT NULL,
  `sumber_tipe` varchar(50) DEFAULT NULL,
  `sumber_id` bigint DEFAULT NULL,
  `metode_pembayaran` varchar(50) DEFAULT NULL,
  `deskripsi` text,
  `status` enum('draft','posted','void') NOT NULL DEFAULT 'posted',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `nama_operasional` varchar(255) DEFAULT NULL,
  `akun_keunagan_id` int DEFAULT NULL,
  `harga_operasional` int NOT NULL,
  `ket_operasional` text,
  `tgl_input` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `id_users` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_keuangan_tanggal` (`tanggal`),
  KEY `idx_keuangan_akun_keuangan_id` (`akun_keuangan_id`),
  KEY `idx_keuangan_tipe_arus` (`tipe_arus`),
  KEY `idx_keuangan_sumber` (`sumber_tipe`,`sumber_id`),
  KEY `idx_keuangan_status` (`status`),
  KEY `idx_keuangan_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_keuangan_akun_keuangan_id` FOREIGN KEY (`akun_keuangan_id`) REFERENCES `akun_keuangan` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ---------------------------------------------------------------------------
-- TABLE: menu_generator
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_generator` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_slug` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `controller_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `view_folder` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_prefix` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `menu_title` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_menu_key` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_order` int NOT NULL DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `layout_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `datatable_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `datatable_mode` enum('server_side','client_side') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'server_side',
  `datatable_ajax_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datatable_default_order_column` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT 'id',
  `datatable_default_order_dir` enum('asc','desc') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'desc',
  `datatable_page_length` int NOT NULL DEFAULT '10',
  `signed_id_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `signed_id_driver` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hmac',
  `delete_method` enum('POST') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'POST',
  `use_create` tinyint(1) NOT NULL DEFAULT '1',
  `use_edit` tinyint(1) NOT NULL DEFAULT '1',
  `use_delete` tinyint(1) NOT NULL DEFAULT '1',
  `use_detail` tinyint(1) NOT NULL DEFAULT '0',
  `use_bulk_delete` tinyint(1) NOT NULL DEFAULT '0',
  `use_soft_delete` tinyint(1) NOT NULL DEFAULT '1',
  `use_modal_form` tinyint(1) NOT NULL DEFAULT '1',
  `helper_relation_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `helper_image_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `helper_file_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `helper_date_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `helper_currency_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('draft','generated','disabled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `last_generated_at` datetime DEFAULT NULL,
  `last_generated_by` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_menu_generator_module_slug` (`module_slug`),
  UNIQUE KEY `uk_menu_generator_table_name` (`table_name`),
  KEY `idx_menu_generator_status` (`status`),
  KEY `idx_menu_generator_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- TABLE: menu_generator_fields
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_generator_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `menu_generator_id` bigint unsigned NOT NULL,
  `field_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_label` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_length` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `db_default` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_nullable` tinyint(1) NOT NULL DEFAULT '1',
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_unique` tinyint(1) NOT NULL DEFAULT '0',
  `html_type` enum('text','number','email','password','hidden','textarea','select','select2','date','datetime-local','time','file','image','checkbox','radio','editor') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text',
  `field_order` int NOT NULL DEFAULT '0',
  `placeholder_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `help_text` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `show_in_index` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_form` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_create` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_edit` tinyint(1) NOT NULL DEFAULT '1',
  `show_in_detail` tinyint(1) NOT NULL DEFAULT '1',
  `datatable_visible` tinyint(1) NOT NULL DEFAULT '1',
  `datatable_searchable` tinyint(1) NOT NULL DEFAULT '1',
  `datatable_sortable` tinyint(1) NOT NULL DEFAULT '1',
  `datatable_class` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datatable_render` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_relation` tinyint(1) NOT NULL DEFAULT '0',
  `relation_type` enum('belongs_to','image_ref','file_ref') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_table` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_key` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_label_field` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_where_json` json DEFAULT NULL,
  `relation_order_by` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `relation_helper` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upload_type` enum('image','file') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `upload_dir` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allowed_extensions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allowed_mime_types` text COLLATE utf8mb4_unicode_ci,
  `max_file_size_kb` int DEFAULT NULL,
  `helper_format` enum('none','relation','image','file','date_id','datetime_id','currency_id','number_id','badge','status') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `auto_rule` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system_field` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mg_fields_generator_id` (`menu_generator_id`),
  KEY `idx_mg_fields_field_name` (`field_name`),
  CONSTRAINT `fk_mg_fields_generator` FOREIGN KEY (`menu_generator_id`) REFERENCES `menu_generator` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- TABLE: menu_generator_files
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_generator_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `menu_generator_id` bigint unsigned NOT NULL,
  `file_type` enum('controller','model','view_index','view_form','view_modal_create','view_modal_edit','view_modal_delete','route','service','helper','partial','script','style') COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checksum_sha1` char(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_generated` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mg_files_generator_id` (`menu_generator_id`),
  CONSTRAINT `fk_mg_files_generator` FOREIGN KEY (`menu_generator_id`) REFERENCES `menu_generator` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- TABLE: menu_generator_logs
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_generator_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `menu_generator_id` bigint unsigned NOT NULL,
  `action_type` enum('scan_table','generate','regenerate','delete_generated','disable','enable','sync_fields') COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_note` text COLLATE utf8mb4_unicode_ci,
  `snapshot_json` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mg_logs_generator_id` (`menu_generator_id`),
  KEY `idx_mg_logs_action_type` (`action_type`),
  CONSTRAINT `fk_mg_logs_generator` FOREIGN KEY (`menu_generator_id`) REFERENCES `menu_generator` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
