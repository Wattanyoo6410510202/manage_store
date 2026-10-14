-- =====================================================================
-- ProSystem (S12) SSO pilot: ผูก users เดิมกับ ID กลางของ IT Control Center
-- รันใน phpMyAdmin ที่ฐาน procurement_system — รันซ้ำได้
-- username ด้านล่างเป็นของจริงใน ProSystem (บางตัวไม่ตรงกับที่เก็บใน IT Control Center)
-- =====================================================================
SET NAMES utf8mb4;

ALTER TABLE users ADD COLUMN IF NOT EXISTS central_id VARCHAR(20) NULL;
ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS uq_users_central_id (central_id);

UPDATE users SET central_id = 'SSF0005' WHERE username = 'tonkaw' AND central_id IS NULL;  -- ต้นข้าว (ITCC เก็บเป็น TONKAWN)
UPDATE users SET central_id = 'SSF0008' WHERE username = 'may'    AND central_id IS NULL;  -- ลักษณ์ณารา เพชรบังเกิด
UPDATE users SET central_id = 'SSF0009' WHERE username = 'fon'    AND central_id IS NULL;  -- วิชุตา เพ็งเกตุ
UPDATE users SET central_id = 'SSF0010' WHERE username = 'arm'    AND central_id IS NULL;  -- พี่อาร์ม
UPDATE users SET central_id = 'SSF0011' WHERE username = 'admin'  AND central_id IS NULL;  -- วทัญญู ต้นจาน (BOAT)
UPDATE users SET central_id = 'SSF0014' WHERE username = 'poom'   AND central_id IS NULL;  -- พี่ภูมิ (ITCC เก็บเป็น pom)
UPDATE users SET central_id = 'SSF0015' WHERE username = 'jane'   AND central_id IS NULL;  -- เจนวิทย์ คชศิริ
UPDATE users SET central_id = 'SSF0017' WHERE username = 'amm'    AND central_id IS NULL;  -- พี่แอ๋ม

-- ณัชพล (SSF0012): ยังไม่พบบัญชีใน ProSystem — ใส่ username จริงเมื่อทราบ
-- UPDATE users SET central_id = 'SSF0012' WHERE username = '<username>' AND central_id IS NULL;

SELECT id, username, name, central_id FROM users ORDER BY central_id IS NULL, central_id, username;
