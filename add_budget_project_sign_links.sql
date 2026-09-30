-- ลิงก์เซ็นเอกสารโดยไม่ต้อง login (ต้องรัน add_budget_project_signers.sql ก่อน)
-- เก็บเฉพาะ hash ของ token (sha256) ใช้ได้ครั้งเดียว และหมดอายุตาม sign_token_expires
ALTER TABLE `budget_project_signers`
  ADD COLUMN `sign_token_hash` char(64) DEFAULT NULL COMMENT 'sha256 ของ token ในลิงก์เซ็น' AFTER `comment`,
  ADD COLUMN `sign_token_expires` datetime DEFAULT NULL COMMENT 'วันหมดอายุของลิงก์' AFTER `sign_token_hash`,
  ADD COLUMN `sign_token_created_by` int(11) DEFAULT NULL COMMENT 'ผู้สร้างลิงก์' AFTER `sign_token_expires`,
  ADD COLUMN `signed_via` enum('login','link') DEFAULT NULL COMMENT 'เซ็นผ่านระบบ หรือผ่านลิงก์' AFTER `sign_token_created_by`,
  ADD COLUMN `signed_ip` varchar(45) DEFAULT NULL AFTER `signed_via`,
  ADD UNIQUE KEY `uq_bps_sign_token` (`sign_token_hash`);
