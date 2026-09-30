-- ผู้ลงนามของใบขออนุมัติโครงการ + ลายเซ็นออนไลน์ (ต้องรัน add_budget_projects.sql ก่อน)
-- ลำดับการเซ็น (step): 1 ผู้รับผิดชอบ -> 2 ผู้ตรวจสอบ -> 3 ผู้เห็นชอบ -> 4 ผู้อนุมัติ -> 5 รับทราบ HR / บัญชี (หลังอนุมัติ)
CREATE TABLE IF NOT EXISTS `budget_project_signers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `role_key` enum('responsible','checker','endorser','approver','hr','accounting') NOT NULL COMMENT 'ช่องลงนามในแบบฟอร์ม',
  `step` tinyint(4) NOT NULL COMMENT 'ลำดับการเซ็น',
  `user_id` int(11) NOT NULL COMMENT 'ผู้ที่ต้องเซ็น',
  `signature_path` varchar(255) DEFAULT NULL COMMENT 'ไฟล์ PNG ลายเซ็น',
  `signed_at` datetime DEFAULT NULL,
  `decision` enum('approved','rejected') DEFAULT NULL COMMENT 'ผลการพิจารณา (เฉพาะผู้อนุมัติ)',
  `comment` text DEFAULT NULL COMMENT 'หมายเหตุ / เหตุผลที่ไม่อนุมัติ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bps_project_role` (`project_id`, `role_key`),
  KEY `idx_bps_user_pending` (`user_id`, `signed_at`),
  CONSTRAINT `fk_bps_project` FOREIGN KEY (`project_id`) REFERENCES `budget_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
