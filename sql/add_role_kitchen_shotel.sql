-- เพิ่ม role ครัว SHotel (kitchen_shotel) — GM Hotel / GM SHotel อนุมัติได้
ALTER TABLE users MODIFY COLUMN role ENUM(
    'admin','staff','gm','viewer','gmhok','hok','procure','fin','acc','mgr','mgr2',
    'gmhr','staff_hr','gmshotel','staff_shotel','maid_shotel','tech_shotel','cater_shotel',
    'gmmanonta','staff_manonta','gmnijuni','staff_nijuni','gmacc','gm_sale','sale','marketing',
    'gm_hotel','kitchen_shotel'
) DEFAULT 'staff';
