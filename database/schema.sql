-- =====================================================================
--  StockForge - Inventory Counting & Audit Platform
--  MySQL / MariaDB schema
--
--  Import with:  mysql -u root -p < database/schema.sql
--            or:  mysql -u root -p stockforge < database/schema.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `stockforge`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `stockforge`;

-- ---------------------------------------------------------------------
--  sto : organisasi / unit induk
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `sto`;
CREATE TABLE `sto` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`       VARCHAR(50)  NOT NULL,
    `name`       VARCHAR(150) NOT NULL,
    `address`    VARCHAR(255) DEFAULT NULL,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sto_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  groups : group kerja di dalam sebuah sto
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `groups`;
CREATE TABLE `groups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sto_id`      INT UNSIGNED DEFAULT NULL,
    `code`        VARCHAR(50)  NOT NULL,
    `name`        VARCHAR(150) NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_groups_code` (`code`),
    KEY `idx_groups_sto` (`sto_id`),
    CONSTRAINT `fk_groups_sto` FOREIGN KEY (`sto_id`) REFERENCES `sto` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  users : semua pengguna beserta role aksesnya
--    role = poweradmin | adminsto | audit | observer
--  password nullable karena form login belum diaktifkan (fase testing)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(150) NOT NULL,
    `username`   VARCHAR(80)  NOT NULL,
    `email`      VARCHAR(150) DEFAULT NULL,
    `phone`      VARCHAR(30)  DEFAULT NULL,
    `password`   VARCHAR(255) DEFAULT NULL,
    `role`       ENUM('poweradmin','adminsto','audit','observer') NOT NULL DEFAULT 'observer',
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `last_login_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_username` (`username`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  user_groups : delegasi user ke group (many-to-many)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `user_groups`;
CREATE TABLE `user_groups` (
    `user_id`    INT UNSIGNED NOT NULL,
    `group_id`   INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `group_id`),
    KEY `idx_user_groups_group` (`group_id`),
    CONSTRAINT `fk_user_groups_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_user_groups_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  locations : lokasi fisik milik sebuah group
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id`   INT UNSIGNED NOT NULL,
    `code`       VARCHAR(50)  NOT NULL,
    `name`       VARCHAR(150) NOT NULL,
    `address`    VARCHAR(255) DEFAULT NULL,
    `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_locations_group_code` (`group_id`, `code`),
    CONSTRAINT `fk_locations_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  devices : perangkat di lokasi (mis. tablet lokasi)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `devices`;
CREATE TABLE `devices` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `location_id`   INT UNSIGNED NOT NULL,
    `code`          VARCHAR(50)  NOT NULL,
    `name`          VARCHAR(150) NOT NULL,
    `type`          VARCHAR(80)  DEFAULT NULL,
    `serial_number` VARCHAR(120) DEFAULT NULL,
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_devices_code` (`code`),
    KEY `idx_devices_location` (`location_id`),
    CONSTRAINT `fk_devices_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  items : data yang dihitung (aset / stok) per group/lokasi/device
--
--  count_status : pending -> counted -> posted
--  audit_status : pending -> audited | discrepancy -> resolved
--  is_locked    : 1 setelah posting/lapor ke adminsto, butuh unlock
--                 (auto terbuka jika audit_status = discrepancy)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_id`       INT UNSIGNED NOT NULL,
    `location_id`    INT UNSIGNED DEFAULT NULL,
    `device_id`      INT UNSIGNED DEFAULT NULL,
    `code`           VARCHAR(80)  NOT NULL,
    `name`           VARCHAR(200) NOT NULL,
    `category`       VARCHAR(100) DEFAULT NULL,
    `unit`           VARCHAR(40)  DEFAULT NULL,
    `expected_qty`   INT          NOT NULL DEFAULT 0,
    `counted_qty`    INT          DEFAULT NULL,
    `count_status`   ENUM('pending','counted','posted')  NOT NULL DEFAULT 'pending',
    `audit_status`   ENUM('pending','audited','discrepancy','resolved') NOT NULL DEFAULT 'pending',
    `is_locked`      TINYINT(1)   NOT NULL DEFAULT 0,
    `condition_note` VARCHAR(255) DEFAULT NULL,
    `counted_by`     INT UNSIGNED DEFAULT NULL,
    `counted_at`     DATETIME     DEFAULT NULL,
    `posted_by`      INT UNSIGNED DEFAULT NULL,
    `posted_at`      DATETIME     DEFAULT NULL,
    `created_by`     INT UNSIGNED DEFAULT NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_items_group_code` (`group_id`, `code`),
    KEY `idx_items_group`  (`group_id`),
    KEY `idx_items_status` (`count_status`, `audit_status`),
    KEY `idx_items_loc`    (`location_id`),
    CONSTRAINT `fk_items_group`    FOREIGN KEY (`group_id`)    REFERENCES `groups` (`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_items_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_items_device`   FOREIGN KEY (`device_id`)   REFERENCES `devices` (`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  audits : riwayat pengecekan oleh auditor (minimal 10% per group)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `audits`;
CREATE TABLE `audits` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id`     INT UNSIGNED NOT NULL,
    `group_id`    INT UNSIGNED NOT NULL,
    `auditor_id`  INT UNSIGNED DEFAULT NULL,
    `expected_qty` INT NOT NULL DEFAULT 0,
    `counted_qty` INT NOT NULL DEFAULT 0,
    `audited_qty` INT NOT NULL DEFAULT 0,
    `result`      ENUM('match','discrepancy') NOT NULL DEFAULT 'match',
    `note`        VARCHAR(255) DEFAULT NULL,
    `is_sample`   TINYINT(1) NOT NULL DEFAULT 0,
    `audited_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audits_item`  (`item_id`),
    KEY `idx_audits_group` (`group_id`),
    CONSTRAINT `fk_audits_item`  FOREIGN KEY (`item_id`)  REFERENCES `items` (`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_audits_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  discrepancies : selisih yang harus dicari oleh observer
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `discrepancies`;
CREATE TABLE `discrepancies` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id`          INT UNSIGNED NOT NULL,
    `group_id`         INT UNSIGNED NOT NULL,
    `expected_qty`     INT NOT NULL DEFAULT 0,
    `counted_qty`      INT NOT NULL DEFAULT 0,
    `audited_qty`      INT DEFAULT NULL,
    `difference`       INT NOT NULL DEFAULT 0,
    `status`           ENUM('open','searching','resolved') NOT NULL DEFAULT 'open',
    `note`             VARCHAR(255) DEFAULT NULL,
    `resolution_note`  VARCHAR(255) DEFAULT NULL,
    `created_by`       INT UNSIGNED DEFAULT NULL,
    `resolved_by`      INT UNSIGNED DEFAULT NULL,
    `resolved_at`      DATETIME DEFAULT NULL,
    `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_disc_group`  (`group_id`),
    KEY `idx_disc_status` (`status`),
    KEY `idx_disc_item`   (`item_id`),
    CONSTRAINT `fk_disc_item`  FOREIGN KEY (`item_id`)  REFERENCES `items` (`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_disc_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  notifications : notifikasi per-user atau broadcast ke role/group
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `role`        ENUM('poweradmin','adminsto','audit','observer') DEFAULT NULL,
    `group_id`    INT UNSIGNED DEFAULT NULL,
    `title`       VARCHAR(150) NOT NULL,
    `message`     VARCHAR(500) DEFAULT NULL,
    `type`        ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    `link`        VARCHAR(255) DEFAULT NULL,
    `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user`  (`user_id`),
    KEY `idx_notif_role`  (`role`),
    KEY `idx_notif_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  activity_logs : jejak audit aksi di aplikasi
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `group_id`    INT UNSIGNED DEFAULT NULL,
    `action`      VARCHAR(60) NOT NULL,
    `entity`      VARCHAR(60) DEFAULT NULL,
    `entity_id`   INT UNSIGNED DEFAULT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `ip_address`  VARCHAR(45) DEFAULT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_logs_user` (`user_id`),
    KEY `idx_logs_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  import_logs : hasil import excel/csv
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS `import_logs`;
CREATE TABLE `import_logs` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED DEFAULT NULL,
    `group_id`      INT UNSIGNED DEFAULT NULL,
    `type`          VARCHAR(40) NOT NULL DEFAULT 'items',
    `filename`      VARCHAR(200) DEFAULT NULL,
    `total_rows`    INT NOT NULL DEFAULT 0,
    `success_rows`  INT NOT NULL DEFAULT 0,
    `failed_rows`   INT NOT NULL DEFAULT 0,
    `status`        ENUM('processing','success','partial','failed') NOT NULL DEFAULT 'processing',
    `message`       VARCHAR(500) DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_import_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
