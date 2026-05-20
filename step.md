ณ ตอนนี้ มีตาราง ดังนี้
1	id Primary	int(11)			No	None		AUTO_INCREMENT	Change Change	Drop Drop	
	2	sup_id Index	int(11)			Yes	NULL			Change Change	Drop Drop	
	3	username	varchar(50)	utf8mb4_general_ci		No	None			Change Change	Drop Drop	
	4	password	varchar(255)	utf8mb4_general_ci		No	None			Change Change	Drop Drop	
	5	role	enum(''admin','staff','gm','viewer','gmhok','hok','procure','fin','acc','mgr','mgr2'	utf8mb4_general_ci		Yes	staff			Change Change	Drop Drop	
	6	name	varchar(255)	utf8mb4_general_ci		No	None			Change Change	Drop Drop	

approved_by อยากเพิ่ม ระดับ 0 สำหรับ หัวหน้างงานของแต่ละตำแหน่ง        
HOK หัวหน้าคือ GMHOK
HR หัวหน้าคือ GMHR
staff_shotel หัวหน้าคือ GMshotel
staff_manonta หัวหน้าคือ GMmanonta
staff_nijuni หัวหน้าคือ GMnijuni
ACC หัวหน้าคือ GMACC (เปลี่ยนด้วย จาก acc mี่อนุมติลำดับ approved_by_1 เปลี่ยนเป็น GMACC แทน)
โดยถ้าหัวหน้างานแต่ล่ะอัน ส่งขอซื้อเอง ก็ให้อนุมัติเองเลย

(สิ่งที่สร้างแล้ว
ALTER TABLE pr 
ADD COLUMN approved_by_0 INT(11) NULL DEFAULT NULL AFTER status,
ADD COLUMN approved_at_0 DATETIME NULL DEFAULT NULL AFTER approved_by_0;

  1 ALTER TABLE users MODIFY COLUMN role ENUM(
   2     'admin', 'staff', 'gm', 'viewer', 
   3     'gmhok', 'hok', 'procure', 'fin', 'acc', 
   4     'mgr', 'mgr2', 'gmhr', 'staff_hr', 'gmshotel', 
   5     'staff_shotel', 'gmmanonta', 'staff_manonta', 
   6     'gmnijuni', 'staff_nijuni', 'gmacc'
   7 ) DEFAULT 'staff'; )

✦ การปรับปรุงระบบการอนุมัติตาม step.md สามารถแบ่งออกเป็น 4 ขั้นตอนหลัก ดังนี้ครับ:

  Step 1: ปรับโครงสร้างฐานข้อมูล (Database)
  เราต้องเพิ่มช่องสำหรับเก็บข้อมูลการอนุมัติ "ระดับ 0" (หัวหน้างาน) ในตาราง pr
   * เพิ่ม column: approved_by_0 (int) - เก็บ ID คนอนุมัติ
   * เพิ่ม column: approved_at_0 (datetime) - เก็บเวลาที่อนุมัติ
   * (ถ้ายังไม่มี) เพิ่ม Role ใหม่ๆ เข้าไปใน Enum ของตาราง users เช่น gmhok, gmhr, gmacc, gmshotel, gmmanonta, gmnijuni

  Step 2: ปรับปรุง Logic การอนุมัติ (api/update_pr_status_new.php)
  ส่วนนี้คือ "หัวใจ" ของงานนี้ ต้องแก้ไขโค้ดเพื่อ:
   1. สร้างแผนผังหัวหน้างาน: เขียนโค้ดตรวจสอบว่าคนขอซื้ออยู่สายงานไหน (เช่น สาย HOK ต้องให้ gmhok อนุมัติระดับ 0)
   2. ระบบอนุมัติด้วยตนเอง (Self-Approval): เพิ่มเงื่อนไขว่า ถ้าคนขอซื้อมี Role เป็น "ระดับหัวหน้า" อยู่แล้ว (เช่น gmhok ขอซื้อเอง) ให้ระบบลงชื่อในช่อง approved_by_0 ให้อัตโนมัติ
      หรือให้กดอนุมัติเองได้เลย
   3. เปลี่ยนสายบัญชี: ปรับจากการใช้ Role acc เป็น gmacc สำหรับการอนุมัติในลำดับถัดไป (approved_by_1)
   4. ปรับเงื่อนไขการจบงาน (is_fully): ต้องรอให้มีการอนุมัติระดับ 0 ก่อน ถึงจะถือว่าผ่านขั้นตอนแรก

  Step 3: ปรับปรุงการแสดงผล (UI - view_pr_new.php)
  เพื่อให้ผู้ใช้งานและคนอนุมัติเห็นสถานะใหม่:
   1. เพิ่มช่องลงนาม: เพิ่มส่วนแสดงผล "ผู้อนุมัติ (ระดับหัวหน้างาน)" ในหน้าดูรายละเอียด PR
   2. ปุ่มอนุมัติ: แก้ไขเงื่อนไขการแสดงปุ่ม "อนุมัติ" ให้แสดงเฉพาะเมื่อ Role ของผู้ใช้งานตรงกับสายงานที่ต้องอนุมัติในระดับ 0

  Step 4: ตรวจสอบและสร้าง PO อัตโนมัติ
   * ตรวจสอบว่าเมื่ออนุมัติครบทุกระดับ (รวมถึงระดับ 0 และระดับบริหารตามวงเงิน) ระบบยังสามารถสร้างใบ PO อัตโนมัติได้ถูกต้องเหมือนเดิม
 * ALTER TABLE budget_types ADD COLUMN budget_amount DECIMAL(15, 2) DEFAULT 0.00;
 * 
* CREATE TABLE budget_adjustments (
 id INT AUTO_INCREMENT PRIMARY KEY,
budget_type_id INT NOT NULL,
amount DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
adjustment_type ENUM('addition', 'reduction', 'transfer') NOT NULL,
reason TEXT,
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
created_by INT,
  FOREIGN KEY (budget_type_id) REFERENCES budget_types(id) ON DELETE CASCADE
);


CREATE INDEX idx_adj_budget_type ON budget_adjustments(budget_type_id);