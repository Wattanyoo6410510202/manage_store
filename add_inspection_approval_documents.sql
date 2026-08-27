ALTER TABLE inspection_approvals
    ADD COLUMN selected_documents_json TEXT NULL AFTER reason;
