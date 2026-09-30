-- โปรแกรม/เว็บในบริษัท ที่แสดงในหน้า E-Service พร้อม username/password ของแต่ละคน
CREATE TABLE IF NOT EXISTS `company_programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL COMMENT 'ชื่อโปรแกรม',
  `description` text DEFAULT NULL COMMENT 'คำอธิบาย',
  `url` varchar(500) NOT NULL COMMENT 'ลิงก์เข้าโปรแกรม',
  `image_path` varchar(255) DEFAULT NULL COMMENT 'รูป/โลโก้',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ใครเห็นโปรแกรมไหน + บัญชีเข้าใช้ของคนนั้น (รหัสผ่านเข้ารหัส AES-256-GCM ด้วยกุญแจใน secrets/app_key.php)
CREATE TABLE IF NOT EXISTS `company_program_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `program_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_username` varchar(255) DEFAULT NULL,
  `login_password_enc` text DEFAULT NULL COMMENT 'รหัสผ่านที่เข้ารหัสแล้ว (ห้ามเก็บเป็นข้อความธรรมดา)',
  `note` varchar(255) DEFAULT NULL COMMENT 'หมายเหตุ เช่น สาขา/สิทธิ์',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cpa_program_user` (`program_id`, `user_id`),
  KEY `idx_cpa_user` (`user_id`),
  CONSTRAINT `fk_cpa_program` FOREIGN KEY (`program_id`) REFERENCES `company_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
