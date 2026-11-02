-- ใบเทียบราคาใน PR: 1 PR มีได้หลายร้าน (pr_quotes) แต่ละร้านมีราคารายบรรทัด (pr_quote_items)
-- ร้านที่ถูกเลือก (is_selected = 1) จะถูกคัดลอกราคาลง pr_items และ pr.store_id ตอนบันทึก

CREATE TABLE IF NOT EXISTS `pr_quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL COMMENT 'stores.id ร้านที่เสนอราคา',
  `quote_no` varchar(100) DEFAULT NULL COMMENT 'เลขที่ใบเสนอราคาของร้าน',
  `delivery_days` int(11) DEFAULT NULL COMMENT 'ระยะเวลาส่งของ (วัน)',
  `payment_term` varchar(100) DEFAULT NULL COMMENT 'เงื่อนไขชำระเงิน/เครดิต',
  `service_note` varchar(255) DEFAULT NULL COMMENT 'การบริการ/การรับประกัน/อื่นๆ',
  `attachment` varchar(255) DEFAULT NULL COMMENT 'ไฟล์ใบเสนอราคา uploads/pr_quotes/',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `vat` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `is_selected` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pr_quotes_pr` (`pr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pr_quote_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `line_no` int(11) NOT NULL COMMENT 'ลำดับบรรทัด ตรงกับลำดับ pr_items (เริ่ม 0)',
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_pr_quote_items_quote` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ราคากลางต่อหน่วยของแต่ละบรรทัด (ไม่บังคับ)
CREATE TABLE IF NOT EXISTS `pr_quote_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pr_id` int(11) NOT NULL,
  `line_no` int(11) NOT NULL COMMENT 'ลำดับบรรทัด ตรงกับ pr_items (เริ่ม 0)',
  `ref_price` decimal(15,2) DEFAULT NULL COMMENT 'ราคากลางต่อหน่วย',
  PRIMARY KEY (`id`),
  KEY `idx_pr_quote_lines_pr` (`pr_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `pr`
  ADD COLUMN IF NOT EXISTS `quote_selection_reason` text DEFAULT NULL COMMENT 'เหตุผลที่ไม่เลือกร้านที่ราคาถูกที่สุด';

-- เผื่อ pr_quotes ถูกสร้างจากไฟล์รุ่นก่อนที่ยังไม่มีคอลัมน์นี้
ALTER TABLE `pr_quotes`
  ADD COLUMN IF NOT EXISTS `service_note` varchar(255) DEFAULT NULL COMMENT 'การบริการ/การรับประกัน/อื่นๆ' AFTER `payment_term`;
