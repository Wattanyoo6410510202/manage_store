-- =============================================================
-- SQL Script: เพิ่มคอลัมน์ LINE ในตาราง users
-- สำหรับ LINE Notify + LINE OA (Messaging API)
-- =============================================================
-- รันครั้งเดียวเท่านั้น (IF NOT EXISTS ป้องกันซ้อน)
-- =============================================================

-- 1. เพิ่มคอลัมน์ line_token (LINE Notify)
ALTER TABLE users 
  ADD COLUMN IF NOT EXISTS line_token VARCHAR(255) DEFAULT NULL 
  AFTER phone;

-- 2. เพิ่มคอลัมน์ line_user_id (LINE OA ส่งตาม userId)
ALTER TABLE users 
  ADD COLUMN IF NOT EXISTS line_user_id VARCHAR(255) DEFAULT NULL 
  AFTER line_token;

-- 3. เพิ่มค่าตั้งค่า LINE OA Channel Access Token (ถ้ายังไม่มี)
INSERT IGNORE INTO system_settings (setting_key, setting_value, category, description)
VALUES ('LINE_CHANNEL_ACCESS_TOKEN', '', 'API', 'LINE OA Channel Access Token สำหรับส่งข้อความ Push');

-- =============================================================
-- วิธีตั้งค่า LINE OA (Messaging API):
-- 1. ไปที่ https://developers.line.biz/console/
-- 2. เลือก Provider → Channel (Messaging API)
-- 3. ไปที่ Messaging API tab → Issue Channel Access Token
-- 4. คัดลอก Token
-- 5. นำไปใส่ใน ตั้งค่าระบบ → LINE_CHANNEL_ACCESS_TOKEN
--
-- วิธีหา User ID:
-- 1. เพิ่ม LINE OA เป็นเพื่อน
-- 2. ใช้ Webhook หรือ LINE Developers Console ดู userId
-- 3. หรือใช้ Reply API ตอนมีคนส่งข้อความมา
-- 4. นำ userId ไปใส่ใน ตั้งค่าผู้ใช้งาน → LINE OA User ID
--
-- ระบบจะพยายามส่ง LINE OA ก่อน ถ้าไม่มีจะ fallback ไป LINE Notify
-- =============================================================
