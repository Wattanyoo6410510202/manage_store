ALTER TABLE project_milestones
    ADD COLUMN IF NOT EXISTS retention_return_due_date DATE NULL AFTER retention_amount,
    ADD COLUMN IF NOT EXISTS retention_returned_at DATETIME NULL AFTER retention_return_due_date;
