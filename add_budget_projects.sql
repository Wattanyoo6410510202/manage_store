-- ใบขออนุมัติโครงการ อ้างอิงงบประมาณ (budget_types) — แยกจากระบบ PR
CREATE TABLE IF NOT EXISTS `budget_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_type_id` int(11) NOT NULL COMMENT 'งบประมาณที่อ้างอิง (บริษัทมาจากงบ)',
  `project_type` enum('new','additional') NOT NULL DEFAULT 'new' COMMENT 'โครงการใหม่ / โครงการเพิ่มเติม',
  `parent_project_id` int(11) DEFAULT NULL COMMENT 'โครงการเดิม กรณีโครงการเพิ่มเติม',
  `request_date` date NOT NULL COMMENT 'วันที่ขออนุมัติ',
  `department` varchar(150) DEFAULT NULL COMMENT 'แผนก',
  `division` varchar(150) DEFAULT NULL COMMENT 'ฝ่าย',
  `project_no` varchar(50) DEFAULT NULL COMMENT 'เลขที่โครงการ',
  `name` varchar(255) NOT NULL COMMENT 'ชื่อโครงการ',
  `responsible_name` varchar(255) DEFAULT NULL COMMENT 'ผู้รับผิดชอบ',
  `objectives` text DEFAULT NULL COMMENT 'วัตถุประสงค์',
  `duration_value` int(11) DEFAULT NULL COMMENT 'ระยะเวลาดำเนินการ (ประมาณ)',
  `duration_unit` enum('day','month','year') DEFAULT 'month',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'ค่าใช้จ่ายตลอดโครงการ (รวมจากรายการ)',
  `expected_results` text DEFAULT NULL COMMENT 'ผลที่คาดว่าจะได้รับ',
  `file_path` varchar(255) DEFAULT NULL COMMENT 'ไฟล์แนบ (เช่น สแกนใบขออนุมัติ)',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reject_reason` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by_gmacc` int(11) DEFAULT NULL,
  `approved_at_gmacc` datetime DEFAULT NULL,
  `approved_by_mgr` int(11) DEFAULT NULL,
  `approved_at_mgr` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bp_project_no` (`project_no`),
  KEY `idx_bp_budget_type` (`budget_type_id`),
  KEY `idx_bp_parent` (`parent_project_id`),
  KEY `idx_bp_created_by` (`created_by`),
  KEY `idx_bp_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- รายละเอียดของโครงการ (ลำดับ / รายละเอียด / ราคา)
CREATE TABLE IF NOT EXISTS `budget_project_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `line_no` int(11) NOT NULL DEFAULT 1,
  `description` varchar(255) NOT NULL DEFAULT '',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_bpi_project` (`project_id`),
  CONSTRAINT `fk_bpi_project` FOREIGN KEY (`project_id`) REFERENCES `budget_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
