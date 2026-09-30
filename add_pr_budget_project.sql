-- ผูกใบขอซื้อ (PR) กับโครงการ (budget_projects) — ต้องรัน add_budget_projects.sql ก่อน
-- PR ที่เลือกโครงการ จะใช้เงินจากยอดที่โครงการกันไว้; PR ที่ไม่เลือกโครงการ ใช้ได้เฉพาะยอดงบที่ยังไม่ถูกกันให้โครงการ
ALTER TABLE `pr`
  ADD COLUMN `budget_project_id` int(11) DEFAULT NULL COMMENT 'โครงการที่ใช้เงิน (budget_projects.id)' AFTER `budget_type_id`,
  ADD KEY `idx_pr_budget_project` (`budget_project_id`),
  ADD KEY `idx_pr_budget_type` (`budget_type_id`);
