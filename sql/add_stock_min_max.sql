-- =====================================================================
-- Stock Min/Max ตามหลักไฟล์ประมาณการสั่งซื้อ (Class A/B/C + Lead Time)
-- รันใน phpMyAdmin ที่ฐาน procurement_system — รันซ้ำได้
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE stock_products ADD COLUMN IF NOT EXISTS abc_class ENUM('A','B','C') NOT NULL DEFAULT 'B' AFTER unit;
ALTER TABLE stock_products ADD COLUMN IF NOT EXISTS lead_time_days INT NOT NULL DEFAULT 3 AFTER abc_class;
