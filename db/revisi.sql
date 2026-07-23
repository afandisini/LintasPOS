-- LintasPOS database revision
--
-- Aman di-import melalui phpMyAdmin:
-- - hanya membuat tabel yang belum ada
-- - tidak menghapus atau menimpa data yang sudah ada
-- - tidak menyalin data produksi dari dump hosting
--
-- Tabel ini diambil dari db/ucxrutw7_lintaspos_online.sql dan diperlukan
-- agar database lokal memiliki schema yang sama untuk Security Monitor,
-- hak akses fitur, dan hutang supplier.

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_name` varchar(100) NOT NULL DEFAULT 'api-client',
  `device_uuid` varchar(191) DEFAULT NULL,
  `platform` varchar(30) DEFAULT NULL,
  `app_version` varchar(50) DEFAULT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_tokens_hash` (`token_hash`),
  KEY `idx_api_tokens_user` (`user_id`),
  KEY `idx_api_tokens_active` (`token_hash`,`revoked_at`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) NOT NULL,
  `module_name` varchar(80) NOT NULL DEFAULT '',
  `action_name` varchar(40) NOT NULL DEFAULT '',
  `target_type` varchar(80) NOT NULL DEFAULT '',
  `target_id` varchar(40) NOT NULL DEFAULT '',
  `before_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_snapshot`)),
  `after_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_snapshot`)),
  `diff_summary` text DEFAULT NULL,
  `is_sensitive` tinyint(1) NOT NULL DEFAULT 0,
  `risk_score` tinyint(4) NOT NULL DEFAULT 0,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `request_id` varchar(36) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_aal_occurred_at` (`occurred_at`),
  KEY `idx_aal_user_id` (`user_id`),
  KEY `idx_aal_module` (`module_name`),
  KEY `idx_aal_action` (`action_name`),
  KEY `idx_aal_target` (`target_type`,`target_id`),
  KEY `idx_aal_sensitive` (`is_sensitive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_activity` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  `auth_event` varchar(40) NOT NULL,
  `result` enum('success','failed','blocked') NOT NULL DEFAULT 'failed',
  `user_id` int(11) DEFAULT NULL,
  `identifier_masked` varchar(80) NOT NULL DEFAULT '',
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `user_agent_hash` varchar(64) NOT NULL DEFAULT '',
  `session_hash` varchar(64) NOT NULL DEFAULT '',
  `attempt_count` tinyint(4) NOT NULL DEFAULT 1,
  `risk_score` tinyint(4) NOT NULL DEFAULT 0,
  `request_id` varchar(36) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_aa_occurred_at` (`occurred_at`),
  KEY `idx_aa_auth_event` (`auth_event`),
  KEY `idx_aa_ip` (`ip_address`),
  KEY `idx_aa_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fitur_akses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(100) NOT NULL,
  `label` varchar(150) NOT NULL,
  `group` varchar(100) NOT NULL DEFAULT 'Umum',
  `sort` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fitur_akses_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `request_activity` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` varchar(36) NOT NULL,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `user_agent` varchar(512) NOT NULL DEFAULT '',
  `method` varchar(10) NOT NULL DEFAULT '',
  `path` varchar(512) NOT NULL DEFAULT '',
  `status_code` smallint(6) NOT NULL DEFAULT 0,
  `response_time_ms` int(11) NOT NULL DEFAULT 0,
  `query_fingerprint` varchar(64) NOT NULL DEFAULT '',
  `body_fingerprint` varchar(64) NOT NULL DEFAULT '',
  `is_suspicious` tinyint(1) NOT NULL DEFAULT 0,
  `risk_score` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_request_id` (`request_id`),
  KEY `idx_ra_occurred_at` (`occurred_at`),
  KEY `idx_ra_ip` (`ip_address`),
  KEY `idx_ra_user_id` (`user_id`),
  KEY `idx_ra_suspicious` (`is_suspicious`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `security_blocks` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `block_type` enum('ip','user','session') NOT NULL DEFAULT 'ip',
  `block_value` varchar(128) NOT NULL,
  `reason_code` varchar(80) NOT NULL DEFAULT '',
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `blocked_by` varchar(80) NOT NULL DEFAULT 'system',
  `unblocked_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sb_block_value` (`block_value`),
  KEY `idx_sb_active` (`is_active`),
  KEY `idx_sb_expires` (`expires_at`),
  KEY `idx_sb_type_value` (`block_type`,`block_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `security_event_rules` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `rule_code` varchar(80) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT '',
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `detection_type` enum('pattern','threshold','anomaly') NOT NULL DEFAULT 'pattern',
  `threshold_count` int(11) NOT NULL DEFAULT 1,
  `window_seconds` int(11) NOT NULL DEFAULT 60,
  `pattern_text` text DEFAULT NULL,
  `default_action` varchar(50) NOT NULL DEFAULT 'logged',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rule_code` (`rule_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `security_events` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  `event_code` varchar(80) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT '',
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `risk_score` tinyint(4) NOT NULL DEFAULT 0,
  `actor_type` enum('guest','user') NOT NULL DEFAULT 'guest',
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `path` varchar(512) NOT NULL DEFAULT '',
  `detection_stage` enum('before_login','after_login') NOT NULL DEFAULT 'before_login',
  `detection_source` varchar(80) NOT NULL DEFAULT '',
  `payload_summary` text DEFAULT NULL,
  `action_taken` varchar(100) NOT NULL DEFAULT 'logged',
  `request_id` varchar(36) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_se_occurred_at` (`occurred_at`),
  KEY `idx_se_event_code` (`event_code`),
  KEY `idx_se_severity` (`severity`),
  KEY `idx_se_ip` (`ip_address`),
  KEY `idx_se_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_debts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `debt_no` varchar(64) NOT NULL,
  `debt_date` date NOT NULL,
  `total_amount` int(11) NOT NULL DEFAULT 0,
  `paid_amount` int(11) NOT NULL DEFAULT 0,
  `remaining_amount` int(11) NOT NULL DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_supplier_debts_debt_no` (`debt_no`),
  KEY `idx_supplier_debts_purchase_id` (`purchase_id`),
  KEY `idx_supplier_debts_supplier_id` (`supplier_id`),
  KEY `idx_supplier_debts_status` (`status`),
  KEY `idx_supplier_debts_due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_debt_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_debt_id` int(11) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `payment_no` varchar(64) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) NOT NULL DEFAULT 'Cash',
  `kas_akun_id` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `reference_no` varchar(64) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_supplier_debt_payments_payment_no` (`payment_no`),
  KEY `idx_supplier_debt_payments_supplier_debt_id` (`supplier_debt_id`),
  KEY `idx_supplier_debt_payments_purchase_id` (`purchase_id`),
  KEY `idx_supplier_debt_payments_supplier_id` (`supplier_id`),
  KEY `idx_supplier_debt_payments_payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_fitur_akses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `fitur_key` varchar(100) NOT NULL,
  `can_access` tinyint(1) NOT NULL DEFAULT 1,
  `can_create` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_fitur` (`user_id`,`fitur_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
