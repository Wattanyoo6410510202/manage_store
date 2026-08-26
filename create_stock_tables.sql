CREATE TABLE IF NOT EXISTS stock_categories (
  id INT NOT NULL AUTO_INCREMENT,
  sup_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_category_company_name (sup_id, name),
  KEY idx_stock_category_company_active (sup_id, is_active),
  CONSTRAINT fk_stock_category_company FOREIGN KEY (sup_id) REFERENCES suppliers(id),
  CONSTRAINT fk_stock_category_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_products (
  id INT NOT NULL AUTO_INCREMENT,
  sup_id INT NOT NULL,
  sku VARCHAR(80) NOT NULL,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(120) DEFAULT NULL,
  category_id INT DEFAULT NULL,
  unit VARCHAR(50) NOT NULL,
  min_quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_product_company_sku (sup_id, sku),
  KEY idx_stock_product_company_active (sup_id, is_active),
  KEY idx_stock_product_category (category_id),
  CONSTRAINT fk_stock_product_company FOREIGN KEY (sup_id) REFERENCES suppliers(id),
  CONSTRAINT fk_stock_product_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_product_category FOREIGN KEY (category_id) REFERENCES stock_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_balances (
  product_id INT NOT NULL,
  quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (product_id),
  CONSTRAINT fk_stock_balance_product FOREIGN KEY (product_id) REFERENCES stock_products(id) ON DELETE CASCADE,
  CONSTRAINT chk_stock_balance_nonnegative CHECK (quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_product_unit_conversions (
  id INT NOT NULL AUTO_INCREMENT,
  product_id INT NOT NULL,
  purchase_unit VARCHAR(50) NOT NULL,
  units_per_purchase_unit DECIMAL(15,4) NOT NULL,
  updated_by INT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_product_purchase_unit (product_id, purchase_unit),
  CONSTRAINT fk_stock_conversion_product FOREIGN KEY (product_id) REFERENCES stock_products(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_conversion_actor FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_stock_conversion_positive CHECK (units_per_purchase_unit > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
  id BIGINT NOT NULL AUTO_INCREMENT,
  sup_id INT NOT NULL,
  product_id INT NOT NULL,
  movement_type ENUM('in_po','out_withdrawal','adjustment','return') NOT NULL,
  quantity DECIMAL(15,2) NOT NULL,
  balance_before DECIMAL(15,2) NOT NULL,
  balance_after DECIMAL(15,2) NOT NULL,
  reference_type VARCHAR(50) NOT NULL,
  reference_id BIGINT NOT NULL,
  actor_id INT DEFAULT NULL,
  note TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_movement_reference (movement_type, reference_type, reference_id, product_id),
  KEY idx_stock_movement_product_date (product_id, created_at),
  KEY idx_stock_movement_company_date (sup_id, created_at),
  CONSTRAINT fk_stock_movement_company FOREIGN KEY (sup_id) REFERENCES suppliers(id),
  CONSTRAINT fk_stock_movement_product FOREIGN KEY (product_id) REFERENCES stock_products(id),
  CONSTRAINT fk_stock_movement_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_stock_movement_nonzero CHECK (quantity <> 0),
  CONSTRAINT chk_stock_movement_balance_nonnegative CHECK (balance_after >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_po_item_settings (
  po_item_id INT NOT NULL,
  destination ENUM('stock','direct') NOT NULL,
  sup_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  purchase_unit VARCHAR(50) DEFAULT NULL,
  stock_unit VARCHAR(50) DEFAULT NULL,
  units_per_purchase_unit DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
  decided_by INT DEFAULT NULL,
  decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (po_item_id),
  KEY idx_stock_po_setting_company (sup_id),
  CONSTRAINT fk_stock_po_setting_item FOREIGN KEY (po_item_id) REFERENCES po_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_po_setting_company FOREIGN KEY (sup_id) REFERENCES suppliers(id),
  CONSTRAINT fk_stock_po_setting_product FOREIGN KEY (product_id) REFERENCES stock_products(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_po_setting_actor FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_receipts (
  id BIGINT NOT NULL AUTO_INCREMENT,
  doc_no VARCHAR(50) NOT NULL,
  po_id INT NOT NULL,
  sup_id INT NOT NULL,
  received_by INT DEFAULT NULL,
  note TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_receipt_doc_no (doc_no),
  KEY idx_stock_receipt_po (po_id),
  KEY idx_stock_receipt_company_date (sup_id, created_at),
  CONSTRAINT fk_stock_receipt_po FOREIGN KEY (po_id) REFERENCES po(id),
  CONSTRAINT fk_stock_receipt_company FOREIGN KEY (sup_id) REFERENCES suppliers(id),
  CONSTRAINT fk_stock_receipt_actor FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_receipt_items (
  id BIGINT NOT NULL AUTO_INCREMENT,
  receipt_id BIGINT NOT NULL,
  po_item_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  destination ENUM('stock','direct') NOT NULL,
  received_quantity DECIMAL(15,2) NOT NULL,
  units_per_purchase_unit DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
  stock_quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  KEY idx_stock_receipt_item_po_item (po_item_id),
  KEY idx_stock_receipt_item_product (product_id),
  CONSTRAINT fk_stock_receipt_item_receipt FOREIGN KEY (receipt_id) REFERENCES stock_receipts(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_receipt_item_po_item FOREIGN KEY (po_item_id) REFERENCES po_items(id),
  CONSTRAINT fk_stock_receipt_item_product FOREIGN KEY (product_id) REFERENCES stock_products(id) ON DELETE SET NULL,
  CONSTRAINT chk_stock_receipt_item_positive CHECK (received_quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_withdrawals (
  id BIGINT NOT NULL AUTO_INCREMENT,
  doc_no VARCHAR(50) NOT NULL,
  sup_id INT NOT NULL,
  requester_id INT NOT NULL,
  purpose TEXT NOT NULL,
  status ENUM('waiting_issue','waiting_confirmation','discrepancy','partially_fulfilled','completed','cancelled') NOT NULL DEFAULT 'waiting_issue',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_withdrawal_doc_no (doc_no),
  KEY idx_stock_withdrawal_company_status (sup_id, status),
  KEY idx_stock_withdrawal_requester (requester_id, created_at),
  CONSTRAINT fk_stock_withdrawal_company FOREIGN KEY (sup_id) REFERENCES suppliers(id),
  CONSTRAINT fk_stock_withdrawal_requester FOREIGN KEY (requester_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_withdrawal_items (
  id BIGINT NOT NULL AUTO_INCREMENT,
  withdrawal_id BIGINT NOT NULL,
  product_id INT NOT NULL,
  requested_quantity DECIMAL(15,2) NOT NULL,
  issued_quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  received_quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  cancelled_quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_withdrawal_product (withdrawal_id, product_id),
  KEY idx_stock_withdrawal_item_product (product_id),
  CONSTRAINT fk_stock_withdrawal_item_header FOREIGN KEY (withdrawal_id) REFERENCES stock_withdrawals(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_withdrawal_item_product FOREIGN KEY (product_id) REFERENCES stock_products(id),
  CONSTRAINT chk_stock_withdrawal_requested_positive CHECK (requested_quantity > 0),
  CONSTRAINT chk_stock_withdrawal_totals CHECK (
    issued_quantity >= 0 AND received_quantity >= 0 AND cancelled_quantity >= 0
    AND received_quantity <= issued_quantity
    AND issued_quantity + cancelled_quantity <= requested_quantity
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_withdrawal_issues (
  id BIGINT NOT NULL AUTO_INCREMENT,
  withdrawal_item_id BIGINT NOT NULL,
  issued_quantity DECIMAL(15,2) NOT NULL,
  issued_by INT DEFAULT NULL,
  issued_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_quantity DECIMAL(15,2) DEFAULT NULL,
  received_by INT DEFAULT NULL,
  received_at DATETIME DEFAULT NULL,
  receiver_note TEXT DEFAULT NULL,
  status ENUM('waiting_confirmation','confirmed','discrepancy','resolved') NOT NULL DEFAULT 'waiting_confirmation',
  resolution_type ENUM('returned_to_stock','accepted_loss') DEFAULT NULL,
  resolution_quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  resolved_by INT DEFAULT NULL,
  resolved_at DATETIME DEFAULT NULL,
  resolution_note TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_stock_issue_item_status (withdrawal_item_id, status),
  CONSTRAINT fk_stock_issue_item FOREIGN KEY (withdrawal_item_id) REFERENCES stock_withdrawal_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_issue_actor FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_issue_receiver FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_issue_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_stock_issue_positive CHECK (issued_quantity > 0),
  CONSTRAINT chk_stock_issue_received CHECK (received_quantity IS NULL OR (received_quantity >= 0 AND received_quantity <= issued_quantity))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_withdrawal_actions (
  id BIGINT NOT NULL AUTO_INCREMENT,
  withdrawal_id BIGINT NOT NULL,
  withdrawal_item_id BIGINT NOT NULL,
  issue_id BIGINT DEFAULT NULL,
  action_type ENUM('cancel_remaining','discrepancy_return','discrepancy_loss') NOT NULL,
  quantity DECIMAL(15,2) NOT NULL,
  actor_id INT DEFAULT NULL,
  reason TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stock_withdrawal_action_header (withdrawal_id, created_at),
  CONSTRAINT fk_stock_withdrawal_action_header FOREIGN KEY (withdrawal_id) REFERENCES stock_withdrawals(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_withdrawal_action_item FOREIGN KEY (withdrawal_item_id) REFERENCES stock_withdrawal_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_withdrawal_action_issue FOREIGN KEY (issue_id) REFERENCES stock_withdrawal_issues(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_withdrawal_action_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_stock_withdrawal_action_positive CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_reconciliations (
  id BIGINT NOT NULL AUTO_INCREMENT,
  doc_no VARCHAR(50) NOT NULL,
  sup_id INT NOT NULL,
  status ENUM('matched','pending_approval','approved','rejected') NOT NULL,
  reason TEXT DEFAULT NULL,
  counted_by INT DEFAULT NULL,
  counted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  review_note TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_reconciliation_doc_no (doc_no),
  KEY idx_stock_reconciliation_status_company (status, sup_id),
  CONSTRAINT fk_stock_reconciliation_company FOREIGN KEY (sup_id) REFERENCES suppliers(id),
  CONSTRAINT fk_stock_reconciliation_counter FOREIGN KEY (counted_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_stock_reconciliation_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS stock_reconciliation_items (
  id BIGINT NOT NULL AUTO_INCREMENT,
  reconciliation_id BIGINT NOT NULL,
  product_id INT NOT NULL,
  system_quantity DECIMAL(15,2) NOT NULL,
  physical_quantity DECIMAL(15,2) NOT NULL,
  difference_quantity DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stock_reconciliation_product (reconciliation_id, product_id),
  KEY idx_stock_reconciliation_item_product (product_id),
  CONSTRAINT fk_stock_reconciliation_item_header FOREIGN KEY (reconciliation_id) REFERENCES stock_reconciliations(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_reconciliation_item_product FOREIGN KEY (product_id) REFERENCES stock_products(id),
  CONSTRAINT chk_stock_reconciliation_nonnegative CHECK (system_quantity >= 0 AND physical_quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE stock_withdrawals
  MODIFY status ENUM('waiting_issue','waiting_confirmation','discrepancy','partially_fulfilled','completed','cancelled') NOT NULL DEFAULT 'waiting_issue';

ALTER TABLE stock_withdrawal_issues
  MODIFY status ENUM('waiting_confirmation','confirmed','discrepancy','resolved') NOT NULL DEFAULT 'waiting_confirmation',
  ADD COLUMN IF NOT EXISTS resolution_type ENUM('returned_to_stock','accepted_loss') DEFAULT NULL AFTER status,
  ADD COLUMN IF NOT EXISTS resolution_quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER resolution_type,
  ADD COLUMN IF NOT EXISTS resolved_by INT DEFAULT NULL AFTER resolution_quantity,
  ADD COLUMN IF NOT EXISTS resolved_at DATETIME DEFAULT NULL AFTER resolved_by,
  ADD COLUMN IF NOT EXISTS resolution_note TEXT DEFAULT NULL AFTER resolved_at;

SET @stock_resolver_fk_exists = (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_stock_issue_resolver'
);
SET @stock_resolver_fk_sql = IF(
  @stock_resolver_fk_exists = 0,
  'ALTER TABLE stock_withdrawal_issues ADD CONSTRAINT fk_stock_issue_resolver FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stock_resolver_fk_stmt FROM @stock_resolver_fk_sql;
EXECUTE stock_resolver_fk_stmt;
DEALLOCATE PREPARE stock_resolver_fk_stmt;

ALTER TABLE stock_products
  ADD COLUMN IF NOT EXISTS category_id INT DEFAULT NULL AFTER category,
  ADD INDEX IF NOT EXISTS idx_stock_product_category (category_id);

ALTER TABLE stock_po_item_settings
  ADD COLUMN IF NOT EXISTS purchase_unit VARCHAR(50) DEFAULT NULL AFTER product_id,
  ADD COLUMN IF NOT EXISTS stock_unit VARCHAR(50) DEFAULT NULL AFTER purchase_unit,
  ADD COLUMN IF NOT EXISTS units_per_purchase_unit DECIMAL(15,4) NOT NULL DEFAULT 1.0000 AFTER stock_unit;

ALTER TABLE stock_receipt_items
  ADD COLUMN IF NOT EXISTS units_per_purchase_unit DECIMAL(15,4) NOT NULL DEFAULT 1.0000 AFTER received_quantity,
  ADD COLUMN IF NOT EXISTS stock_quantity DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER units_per_purchase_unit;

UPDATE stock_receipt_items
SET stock_quantity = received_quantity * units_per_purchase_unit
WHERE stock_quantity = 0;

INSERT INTO stock_categories (sup_id, name, created_by)
SELECT DISTINCT p.sup_id, TRIM(p.category), p.created_by
FROM stock_products p
WHERE p.category IS NOT NULL AND TRIM(p.category) <> ''
ON DUPLICATE KEY UPDATE name = VALUES(name);

UPDATE stock_products p
INNER JOIN stock_categories c ON c.sup_id = p.sup_id AND c.name = TRIM(p.category)
SET p.category_id = c.id
WHERE p.category_id IS NULL AND p.category IS NOT NULL AND TRIM(p.category) <> '';

SET @stock_product_category_fk_exists = (
  SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_stock_product_category'
);
SET @stock_product_category_fk_sql = IF(
  @stock_product_category_fk_exists = 0,
  'ALTER TABLE stock_products ADD CONSTRAINT fk_stock_product_category FOREIGN KEY (category_id) REFERENCES stock_categories(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stock_product_category_fk_stmt FROM @stock_product_category_fk_sql;
EXECUTE stock_product_category_fk_stmt;
DEALLOCATE PREPARE stock_product_category_fk_stmt;
