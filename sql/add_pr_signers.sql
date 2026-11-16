-- ผู้ลงนามใบขอซื้อผ่านลิงก์สาธารณะ (ไม่ต้อง login) — เลือกผู้อนุมัติและลำดับการเซ็นได้เอง
-- stage = ขั้นอนุมัติในระบบที่แถวนี้แทน (approved_by_0 ... approved_by_3) หรือ NULL = ลงนามรับทราบอย่างเดียว
-- เก็บเฉพาะ hash ของ token (sha256) ใช้ได้จนเซ็น / หมดอายุ / สร้างลิงก์ใหม่
CREATE TABLE IF NOT EXISTS `pr_signers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_id` int(11) NOT NULL,
  `step` tinyint(4) NOT NULL COMMENT 'ลำดับการเซ็น (1 = คนแรก)',
  `stage` varchar(30) DEFAULT NULL COMMENT 'approved_by_0, approved_by, approved_by_1, approved_by_2, approved_by_3 หรือ NULL',
  `user_id` int(11) NOT NULL,
  `signature_path` varchar(255) DEFAULT NULL COMMENT 'ไฟล์ PNG ลายเซ็น',
  `signed_at` datetime DEFAULT NULL,
  `decision` enum('approved','rejected') DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `notify_line` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'ส่งลิงก์ทาง LINE เมื่อถึงคิว',
  `sign_token_hash` char(64) DEFAULT NULL,
  `sign_token_expires` datetime DEFAULT NULL,
  `signed_ip` varchar(45) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prs_token` (`sign_token_hash`),
  KEY `idx_prs_pr` (`pr_id`, `step`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
