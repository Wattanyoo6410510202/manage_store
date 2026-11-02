-- ประวัติการดำเนินการของใบขอซื้อ (ใช้แสดง timeline)
CREATE TABLE IF NOT EXISTS `pr_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_id` int(11) NOT NULL,
  `action` varchar(30) NOT NULL COMMENT 'created, edited, resubmitted, approved, fully_approved, po_created, rejected, received, partial_received',
  `stage` varchar(30) DEFAULT NULL COMMENT 'ขั้นอนุมัติ: approved_by_0, approved_by, approved_by_1, approved_by_2, approved_by_3',
  `actor_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pr_activity_pr` (`pr_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
