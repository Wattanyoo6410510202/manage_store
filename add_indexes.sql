-- =============================================================
-- SQL Script: เพิ่ม Indexes เพื่อเพิ่มประสิทธิภาพฐานข้อมูล
-- =============================================================
-- รันครั้งเดียวเท่านั้น ไม่ต้องกลัวข้อมูลเสียหาย (IF NOT EXISTS)
-- =============================================================

-- =============================================================
-- 1. pr (ใบขอซื้อ) — ตารางหลักที่ใช้มากที่สุด
-- =============================================================
ALTER TABLE pr ADD INDEX idx_pr_supplier (supplier_id);
ALTER TABLE pr ADD INDEX idx_pr_status (status);
ALTER TABLE pr ADD INDEX idx_pr_created_by (created_by);
ALTER TABLE pr ADD INDEX idx_pr_is_internal (is_internal);
ALTER TABLE pr ADD INDEX idx_pr_created_at (created_at);
ALTER TABLE pr ADD INDEX idx_pr_deleted_by (deleted_by);
-- Composite index: การกรองที่พบบ่อยมาก (deleted + status + supplier)
ALTER TABLE pr ADD INDEX idx_pr_list_filter (deleted_at, status, supplier_id);
-- Composite index: pending_approval.php (is_internal + deleted + status)
ALTER TABLE pr ADD INDEX idx_pr_pending_approval (is_internal, deleted_at, status, created_at DESC);

-- =============================================================
-- 2. po (ใบสั่งซื้อ)
-- =============================================================
ALTER TABLE po ADD INDEX idx_po_supplier (supplier_id);
ALTER TABLE po ADD INDEX idx_po_status (status);
ALTER TABLE po ADD INDEX idx_po_created_by (created_by);
ALTER TABLE po ADD INDEX idx_po_created_at (created_at);
ALTER TABLE po ADD INDEX idx_po_deleted_by (deleted_by);
-- Composite: รายการ PO + กรองสถานะ
ALTER TABLE po ADD INDEX idx_po_list_filter (deleted_at, status, created_at DESC);

-- =============================================================
-- 3. quotations (ใบเสนอราคา)
-- =============================================================
ALTER TABLE quotations ADD INDEX idx_quote_supplier (supplier_id);
ALTER TABLE quotations ADD INDEX idx_quote_status (status);
ALTER TABLE quotations ADD INDEX idx_quote_created_by (created_by);
ALTER TABLE quotations ADD INDEX idx_quote_created_at (created_at);
ALTER TABLE quotations ADD INDEX idx_quote_deleted_by (deleted_by);
ALTER TABLE quotations ADD INDEX idx_quote_list_filter (deleted_at, status);

-- =============================================================
-- 4. invoices (ใบแจ้งหนี้)
-- =============================================================
ALTER TABLE invoices ADD INDEX idx_inv_supplier (supplier_id);
ALTER TABLE invoices ADD INDEX idx_inv_customer (customer_id);
ALTER TABLE invoices ADD INDEX idx_inv_status (status);
ALTER TABLE invoices ADD INDEX idx_inv_created_by (created_by);
ALTER TABLE invoices ADD INDEX idx_inv_created_at (created_at);
ALTER TABLE invoices ADD INDEX idx_inv_deleted_by (deleted_by);
ALTER TABLE invoices ADD INDEX idx_inv_list_filter (deleted_at, status, created_at DESC);

-- =============================================================
-- 5. quotation_items — IMPORTANT! ไม่มี index บน quotation_id
-- =============================================================
ALTER TABLE quotation_items ADD INDEX idx_qi_quotation (quotation_id);

-- =============================================================
-- 6. users
-- =============================================================
ALTER TABLE users ADD INDEX idx_users_username (username);
ALTER TABLE users ADD INDEX idx_users_role (role);

-- =============================================================
-- 7. suppliers
-- =============================================================
ALTER TABLE suppliers ADD INDEX idx_suppliers_company (company_name);

-- =============================================================
-- 8. customers
-- =============================================================
ALTER TABLE customers ADD INDEX idx_customers_name (customer_name);

-- =============================================================
-- 9. budget_types, expense_categories, pr_objectives
-- =============================================================
ALTER TABLE budget_types ADD INDEX idx_bt_sup (sup_id);
ALTER TABLE expense_categories ADD INDEX idx_ec_sup (sup_id);
ALTER TABLE pr_objectives ADD INDEX idx_po_sup (sup_id);
