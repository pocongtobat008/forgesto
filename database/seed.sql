-- =====================================================================
--  StockForge - Demo seed data
--    mysql -u root -p stockforge < database/seed.sql
--
--  Scenario:
--    Group Alpha   (GRP-A) : 100% dihitung, sebagian diaudit, 1 selisih
--    Group Bravo   (GRP-B) : baru sebagian dihitung
--    Group Charlie (GRP-C) : 100% dihitung & 100% diaudit
--    Group Delta   (GRP-D) : belum dihitung sama sekali
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE `import_logs`;
TRUNCATE TABLE `activity_logs`;
TRUNCATE TABLE `notifications`;
TRUNCATE TABLE `discrepancies`;
TRUNCATE TABLE `audits`;
TRUNCATE TABLE `items`;
TRUNCATE TABLE `devices`;
TRUNCATE TABLE `locations`;
TRUNCATE TABLE `user_groups`;
TRUNCATE TABLE `users`;
TRUNCATE TABLE `groups`;
TRUNCATE TABLE `sto`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
--  STO
-- ---------------------------------------------------------------------
INSERT INTO `sto` (`id`, `code`, `name`, `address`) VALUES
(1, 'STO-JKT', 'STO Jakarta Pusat', 'Jl. Merdeka No. 1, Jakarta');

-- ---------------------------------------------------------------------
--  Groups
-- ---------------------------------------------------------------------
INSERT INTO `groups` (`id`, `sto_id`, `code`, `name`, `description`) VALUES
(1, 1, 'GRP-A', 'Group Alpha',   'Tim operasional area utara'),
(2, 1, 'GRP-B', 'Group Bravo',   'Tim operasional area selatan'),
(3, 1, 'GRP-C', 'Group Charlie', 'Tim operasional area timur'),
(4, 1, 'GRP-D', 'Group Delta',   'Tim operasional area barat');

-- ---------------------------------------------------------------------
--  Users
-- ---------------------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `username`, `email`, `role`, `is_active`) VALUES
(1, 'Pak Power Admin',    'poweradmin', 'power@stockforge.test', 'poweradmin', 1),
(2, 'Bu Admin STO',       'adminsto',   'adminsto@stockforge.test', 'adminsto', 1),
(3, 'Audit Alpha-Bravo',  'audit.ab',   'audit.ab@stockforge.test', 'audit', 1),
(4, 'Audit Charlie-Delta','audit.cd',   'audit.cd@stockforge.test', 'audit', 1),
(5, 'Observer Alpha',     'obs.alpha',  'obs.alpha@stockforge.test', 'observer', 1),
(6, 'Observer Bravo',     'obs.bravo',  'obs.bravo@stockforge.test', 'observer', 1),
(7, 'Observer Charlie',   'obs.charlie','obs.charlie@stockforge.test', 'observer', 1),
(8, 'Observer Delta',     'obs.delta',  'obs.delta@stockforge.test', 'observer', 1);

-- ---------------------------------------------------------------------
--  Delegasi user ke group
-- ---------------------------------------------------------------------
INSERT INTO `user_groups` (`user_id`, `group_id`) VALUES
(3, 1), (3, 2),
(4, 3), (4, 4),
(5, 1),
(6, 2),
(7, 3),
(8, 4);

-- ---------------------------------------------------------------------
--  Locations
-- ---------------------------------------------------------------------
INSERT INTO `locations` (`id`, `group_id`, `code`, `name`, `address`) VALUES
(1, 1, 'LOC-A1', 'Gudang Alpha 1', 'Blok A No.1'),
(2, 1, 'LOC-A2', 'Gudang Alpha 2', 'Blok A No.2'),
(3, 2, 'LOC-B1', 'Gudang Bravo 1', 'Blok B No.1'),
(4, 2, 'LOC-B2', 'Gudang Bravo 2', 'Blok B No.2'),
(5, 3, 'LOC-C1', 'Gudang Charlie 1', 'Blok C No.1'),
(6, 4, 'LOC-D1', 'Gudang Delta 1', 'Blok D No.1');

-- ---------------------------------------------------------------------
--  Devices (tablet lokasi dll)
-- ---------------------------------------------------------------------
INSERT INTO `devices` (`id`, `location_id`, `code`, `name`, `type`, `serial_number`) VALUES
(1, 1, 'DEV-A1', 'Tablet lokasi Alpha 1', 'tablet', 'TBL-A1-0001'),
(2, 2, 'DEV-A2', 'Tablet lokasi Alpha 2', 'tablet', 'TBL-A2-0002'),
(3, 3, 'DEV-B1', 'Tablet lokasi Bravo 1', 'tablet', 'TBL-B1-0003'),
(4, 5, 'DEV-C1', 'Tablet lokasi Charlie 1', 'tablet', 'TBL-C1-0004'),
(5, 6, 'DEV-D1', 'Tablet lokasi Delta 1', 'tablet', 'TBL-D1-0005');

-- ---------------------------------------------------------------------
--  Items - Group Alpha (100% dihitung)
-- ---------------------------------------------------------------------
INSERT INTO `items`
(`id`,`group_id`,`location_id`,`device_id`,`code`,`name`,`category`,`unit`,`expected_qty`,`counted_qty`,`count_status`,`audit_status`,`is_locked`,`condition_note`,`counted_by`,`counted_at`,`posted_by`,`posted_at`,`created_by`) VALUES
(1, 1, 1, 1, 'A-001', 'Laptop Dell Latitude',  'Elektronik', 'unit', 8,  8,  'posted',  'audited',     1, NULL, 5, '2026-10-01 09:00:00', 5, '2026-10-01 16:00:00', 2),
(2, 1, 1, 1, 'A-002', 'Monitor LG 24"',        'Elektronik', 'unit', 12, 12, 'posted',  'audited',     1, NULL, 5, '2026-10-01 09:10:00', 5, '2026-10-01 16:00:00', 2),
(3, 1, 2, 2, 'A-003', 'Printer Epson L3210',   'Elektronik', 'unit', 3,  2,  'posted',  'discrepancy', 0, 'Fisik hanya 2 unit, 1 unit belum ditemukan', 5, '2026-10-01 09:20:00', 5, '2026-10-01 16:05:00', 2),
(4, 1, 1, 1, 'A-004', 'Keyboard Logitech',     'Elektronik', 'unit', 20, 20, 'posted',  'audited',     1, NULL, 5, '2026-10-01 09:30:00', 5, '2026-10-01 16:05:00', 2),
(5, 1, 1, 1, 'A-005', 'Mouse Logitech',        'Elektronik', 'unit', 25, 25, 'posted',  'pending',     1, NULL, 5, '2026-10-01 09:40:00', 5, '2026-10-01 16:10:00', 2),
(6, 1, 1, 1, 'A-006', 'Router Cisco RV340',    'Jaringan',   'unit', 4,  4,  'posted',  'audited',     1, NULL, 5, '2026-10-01 09:50:00', 5, '2026-10-01 16:10:00', 2),
(7, 1, 1, 1, 'A-007', 'Switch HP 24 Port',     'Jaringan',   'unit', 6,  6,  'posted',  'audited',     1, NULL, 5, '2026-10-01 10:00:00', 5, '2026-10-01 16:15:00', 2),
(8, 1, 2, 2, 'A-008', 'UPS APC 1200VA',        'Kelistrikan','unit', 5,  5,  'counted', 'pending',     0, NULL, 5, '2026-10-01 10:10:00', NULL, NULL, 2),
(9, 1, 2, 2, 'A-009', 'Kabel UTP (roll)',      'Consumable', 'roll', 15, 15, 'counted', 'pending',     0, NULL, 5, '2026-10-01 10:20:00', NULL, NULL, 2),
(10,1, 2, 2, 'A-010', 'Baterai AA (pack)',     'Consumable', 'pack', 30, 30, 'counted', 'pending',     0, NULL, 5, '2026-10-01 10:30:00', NULL, NULL, 2),
(11,1, 2, 2, 'A-011', 'Tablet Samsung Galaxy', 'Elektronik', 'unit', 7,  7,  'counted', 'pending',     0, NULL, 5, '2026-10-01 10:40:00', NULL, NULL, 2),
(12,1, 1, 1, 'A-012', 'HDD External 1TB',      'Elektronik', 'unit', 9,  9,  'counted', 'pending',     0, NULL, 5, '2026-10-01 10:50:00', NULL, NULL, 2);

-- ---------------------------------------------------------------------
--  Items - Group Bravo (baru sebagian dihitung)
-- ---------------------------------------------------------------------
INSERT INTO `items`
(`id`,`group_id`,`location_id`,`device_id`,`code`,`name`,`category`,`unit`,`expected_qty`,`counted_qty`,`count_status`,`audit_status`,`is_locked`,`counted_by`,`counted_at`,`posted_by`,`posted_at`,`created_by`) VALUES
(13, 2, 3, 3, 'B-001', 'Proyektor Epson',   'Elektronik', 'unit', 2,  2,  'counted', 'pending', 0, 6, '2026-10-02 09:00:00', NULL, NULL, 2),
(14, 2, 3, 3, 'B-002', 'AC Split 1.5 PK',   'Peralatan',  'unit', 6,  6,  'counted', 'pending', 0, 6, '2026-10-02 09:15:00', NULL, NULL, 2),
(15, 2, 4, NULL,'B-003','Genset 5000 Watt', 'Peralatan',  'unit', 1,  1,  'posted',  'pending', 1, 6, '2026-10-02 09:30:00', 6, '2026-10-02 15:00:00', 2),
(16, 2, 4, NULL,'B-004','Meja Kerja',       'Furniture',  'unit', 10, NULL,'pending','pending', 0, NULL, NULL, NULL, NULL, 2),
(17, 2, 4, NULL,'B-005','Kursi Kantor',     'Furniture',  'unit', 12, NULL,'pending','pending', 0, NULL, NULL, NULL, NULL, 2),
(18, 2, 3, 3, 'B-006', 'Lemari Arsip',      'Furniture',  'unit', 4,  NULL,'pending','pending', 0, NULL, NULL, NULL, NULL, 2),
(19, 2, 3, 3, 'B-007', 'CCTV Camera',       'Keamanan',   'unit', 8,  NULL,'pending','pending', 0, NULL, NULL, NULL, NULL, 2),
(20, 2, 3, 3, 'B-008', 'DVR 8 Channel',     'Keamanan',   'unit', 2,  NULL,'pending','pending', 0, NULL, NULL, NULL, NULL, 2);

-- ---------------------------------------------------------------------
--  Items - Group Charlie (100% dihitung & diaudit)
-- ---------------------------------------------------------------------
INSERT INTO `items`
(`id`,`group_id`,`location_id`,`device_id`,`code`,`name`,`category`,`unit`,`expected_qty`,`counted_qty`,`count_status`,`audit_status`,`is_locked`,`counted_by`,`counted_at`,`posted_by`,`posted_at`,`created_by`) VALUES
(21, 3, 5, 4, 'C-001', 'Server Rack',       'IT',        'unit', 2, 2, 'posted', 'audited', 1, 7, '2026-10-03 08:30:00', 7, '2026-10-03 14:00:00', 2),
(22, 3, 5, 4, 'C-002', 'KVM Switch',        'IT',        'unit', 3, 3, 'posted', 'audited', 1, 7, '2026-10-03 08:40:00', 7, '2026-10-03 14:00:00', 2),
(23, 3, 5, 4, 'C-003', 'Firewall Fortinet', 'Jaringan',  'unit', 2, 2, 'posted', 'audited', 1, 7, '2026-10-03 08:50:00', 7, '2026-10-03 14:05:00', 2),
(24, 3, 5, 4, 'C-004', 'Access Point Ubiquiti','Jaringan','unit', 10,10,'posted', 'audited', 1, 7, '2026-10-03 09:00:00', 7, '2026-10-03 14:05:00', 2),
(25, 3, 5, 4, 'C-005', 'NAS Synology',      'IT',        'unit', 1, 1, 'posted', 'audited', 1, 7, '2026-10-03 09:10:00', 7, '2026-10-03 14:10:00', 2),
(26, 3, 5, 4, 'C-006', 'Printer Label',     'Elektronik','unit', 2, 2, 'posted', 'audited', 1, 7, '2026-10-03 09:20:00', 7, '2026-10-03 14:10:00', 2),
(27, 3, 5, 4, 'C-007', 'Scanner Barcode',   'Elektronik','unit', 4, 4, 'posted', 'audited', 1, 7, '2026-10-03 09:30:00', 7, '2026-10-03 14:15:00', 2),
(28, 3, 5, 4, 'C-008', 'Handheld Terminal', 'Elektronik','unit', 6, 6, 'posted', 'audited', 1, 7, '2026-10-03 09:40:00', 7, '2026-10-03 14:15:00', 2),
(29, 3, 5, 4, 'C-009', 'Timbangan Digital', 'Peralatan', 'unit', 3, 3, 'posted', 'audited', 1, 7, '2026-10-03 09:50:00', 7, '2026-10-03 14:20:00', 2),
(30, 3, 5, 4, 'C-010', 'Genset Portable',   'Peralatan', 'unit', 1, 1, 'posted', 'audited', 1, 7, '2026-10-03 10:00:00', 7, '2026-10-03 14:20:00', 2);

-- ---------------------------------------------------------------------
--  Items - Group Delta (belum dihitung sama sekali)
-- ---------------------------------------------------------------------
INSERT INTO `items`
(`id`,`group_id`,`location_id`,`device_id`,`code`,`name`,`category`,`unit`,`expected_qty`,`count_status`,`audit_status`,`is_locked`,`created_by`) VALUES
(31, 4, 6, 5, 'D-001', 'Meja Operator',   'Furniture',  'unit', 6,  'pending', 'pending', 0, 2),
(32, 4, 6, 5, 'D-002', 'Kursi Operator',  'Furniture',  'unit', 6,  'pending', 'pending', 0, 2),
(33, 4, 6, 5, 'D-003', 'Komputer Desktop', 'Elektronik','unit', 4,  'pending', 'pending', 0, 2),
(34, 4, 6, 5, 'D-004', 'Monitor 19"',     'Elektronik', 'unit', 4,  'pending', 'pending', 0, 2),
(35, 4, 6, 5, 'D-005', 'AC Portable',     'Peralatan',  'unit', 2,  'pending', 'pending', 0, 2),
(36, 4, 6, 5, 'D-006', 'Rak Besi',        'Furniture',  'unit', 3,  'pending', 'pending', 0, 2);

-- ---------------------------------------------------------------------
--  Audits (minimal 10% per group)
-- ---------------------------------------------------------------------
INSERT INTO `audits`
(`item_id`,`group_id`,`auditor_id`,`expected_qty`,`counted_qty`,`audited_qty`,`result`,`note`,`is_sample`,`audited_at`) VALUES
(1, 1, 3,  8,  8,  8,  'match',       NULL, 1, '2026-10-02 10:00:00'),
(2, 1, 3,  12, 12, 12, 'match',       NULL, 1, '2026-10-02 10:05:00'),
(3, 1, 3,  3,  2,  2,  'discrepancy', 'Selisih 1 unit printer', 1, '2026-10-02 10:10:00'),
(4, 1, 3,  20, 20, 20, 'match',       NULL, 1, '2026-10-02 10:15:00'),
(6, 1, 3,  4,  4,  4,  'match',       NULL, 0, '2026-10-02 10:20:00'),
(7, 1, 3,  6,  6,  6,  'match',       NULL, 0, '2026-10-02 10:25:00'),
(21,3, 4,  2,  2,  2,  'match',       NULL, 1, '2026-10-04 09:00:00'),
(22,3, 4,  3,  3,  3,  'match',       NULL, 1, '2026-10-04 09:05:00'),
(23,3, 4,  2,  2,  2,  'match',       NULL, 1, '2026-10-04 09:10:00'),
(24,3, 4,  10, 10, 10, 'match',       NULL, 1, '2026-10-04 09:15:00'),
(25,3, 4,  1,  1,  1,  'match',       NULL, 0, '2026-10-04 09:20:00'),
(26,3, 4,  2,  2,  2,  'match',       NULL, 0, '2026-10-04 09:25:00'),
(27,3, 4,  4,  4,  4,  'match',       NULL, 0, '2026-10-04 09:30:00'),
(28,3, 4,  6,  6,  6,  'match',       NULL, 0, '2026-10-04 09:35:00'),
(29,3, 4,  3,  3,  3,  'match',       NULL, 0, '2026-10-04 09:40:00'),
(30,3, 4,  1,  1,  1,  'match',       NULL, 0, '2026-10-04 09:45:00');

-- ---------------------------------------------------------------------
--  Discrepancies
-- ---------------------------------------------------------------------
INSERT INTO `discrepancies`
(`id`,`item_id`,`group_id`,`expected_qty`,`counted_qty`,`audited_qty`,`difference`,`status`,`note`,`created_by`,`created_at`) VALUES
(1, 3, 1, 3, 2, 2, -1, 'open', 'Printer Epson kurang 1 unit dari seharusnya', 3, '2026-10-02 10:10:00');

-- ---------------------------------------------------------------------
--  Notifications
-- ---------------------------------------------------------------------
INSERT INTO `notifications` (`user_id`,`role`,`group_id`,`title`,`message`,`type`,`link`,`is_read`,`created_at`) VALUES
(NULL, 'observer', 1, 'Selisih perlu dicari', 'Item A-003 (Printer Epson) selisih 1 unit. Mohon dicari dan diperbarui.', 'warning', '/discrepancies', 0, '2026-10-02 10:11:00'),
(NULL, 'audit',    NULL, 'Data siap diaudit', 'Group Bravo memiliki 2 item terposting yang menunggu audit.', 'info', '/audit', 0, '2026-10-02 15:01:00'),
(2, NULL, NULL, 'Observer Bravo menghitung', 'Observer Bravo telah menghitung 3 item di Group Bravo.', 'success', '/items?group_id=2', 0, '2026-10-02 15:02:00'),
(NULL, 'poweradmin', NULL, 'Selamat datang di StockForge', 'Gunakan menu Group, User, Lokasi, dan Device untuk menyiapkan data STO.', 'info', '/dashboard', 1, '2026-10-01 08:00:00');
