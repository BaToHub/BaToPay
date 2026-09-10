-- BaToPay schema part 2 — forms, webhooks, misc
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `form_submissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` BIGINT UNSIGNED DEFAULT NULL,
  `page_id` INT UNSIGNED DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'new',
  `user_ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `data` LONGTEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_fs_tx` (`transaction_id`), KEY `idx_fs_page` (`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `form_submission_values` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` BIGINT UNSIGNED NOT NULL,
  `field_id` INT UNSIGNED DEFAULT NULL,
  `field_key` VARCHAR(100) NOT NULL,
  `field_name` VARCHAR(100) DEFAULT NULL,
  `field_label` VARCHAR(200) DEFAULT NULL,
  `field_value` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_fsv_submission` (`submission_id`), KEY `idx_fsv_field` (`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transaction_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` BIGINT UNSIGNED NOT NULL,
  `event` VARCHAR(50) NOT NULL,
  `message` TEXT,
  `context` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_tl_tx` (`transaction_id`), KEY `idx_tl_event` (`event`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gateway_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gateway_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(50) DEFAULT NULL,
  `request_summary` TEXT,
  `response_summary` TEXT,
  `http_code` INT DEFAULT NULL,
  `latency_ms` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_gl_gateway` (`gateway_id`), KEY `idx_gl_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `merchant_applications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `shop_name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `telegram_username` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `description` TEXT,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_app_email` (`email`), KEY `idx_app_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `merchant_webhooks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `merchant_id` INT UNSIGNED NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `secret` VARCHAR(128) NOT NULL,
  `events` VARCHAR(500) DEFAULT 'payment.paid,payment.failed,payment.expired',
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `fail_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_success_at` DATETIME DEFAULT NULL,
  `last_fail_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_mw_merchant` (`merchant_id`), KEY `idx_mw_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `merchant_webhook_deliveries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `webhook_id` INT UNSIGNED NOT NULL,
  `event` VARCHAR(50) NOT NULL,
  `payload` MEDIUMTEXT,
  `response_code` INT DEFAULT NULL,
  `response_body` TEXT,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `latency_ms` INT UNSIGNED DEFAULT NULL,
  `retry_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_mwd_webhook` (`webhook_id`), KEY `idx_mwd_event` (`event`), KEY `idx_mwd_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `page_fields` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `page_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `field_key` VARCHAR(100),
  `label` VARCHAR(200) NOT NULL,
  `field_type` VARCHAR(50) NOT NULL DEFAULT 'text',
  `type` VARCHAR(50) NOT NULL DEFAULT 'text',
  `placeholder` VARCHAR(255),
  `default_value` TEXT,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `required` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_pf_page` (`page_id`), KEY `idx_pf_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active',
  `description` TEXT,
  `meta_title` VARCHAR(255),
  `meta_description` VARCHAR(255),
  `logo` VARCHAR(500),
  `button_text` VARCHAR(100),
  `success_message` TEXT,
  `min_amount` INT UNSIGNED NOT NULL DEFAULT 1000,
  `max_amount` INT UNSIGNED,
  `primary_gateway_id` INT UNSIGNED,
  `secondary_gateway_id` INT UNSIGNED,
  `failover_enabled` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_pages_slug` (`slug`), KEY `idx_pages_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `merchants` ADD COLUMN `total_transactions` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `status`;
ALTER TABLE `merchants` ADD COLUMN `callback_url` VARCHAR(500) AFTER `phone`;
ALTER TABLE `merchants` ADD COLUMN `last_login_ip` VARCHAR(45) AFTER `last_login_at`;

ALTER TABLE `transactions` ADD COLUMN `callback_data` LONGTEXT AFTER `webhook_data`;
ALTER TABLE `transactions` ADD COLUMN `verified_at` DATETIME AFTER `paid_at`;

ALTER TABLE `login_attempts` ADD COLUMN `username` VARCHAR(50) AFTER `ip_address`;

ALTER TABLE `gateway_credentials` ADD COLUMN `credential_encrypted` TEXT AFTER `credentials_enc`;
