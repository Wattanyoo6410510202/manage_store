-- =============================================================
-- ตาราง big_projects (โปรเจคใหญ่) — รวม project_id หลายอันไว้ด้วยกัน
-- =============================================================

CREATE TABLE IF NOT EXISTS `big_projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `customer_id` INT DEFAULT NULL,
  `contract_value` DECIMAL(15,2) DEFAULT 0.00,
  `total_vat_amount` DECIMAL(15,2) DEFAULT 0.00,
  `total_wht_amount` DECIMAL(15,2) DEFAULT 0.00,
  `net_contract_value` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `remarks` TEXT,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================
-- ตารางเชื่อมโยง big_projects กับ projects (หลายต่อหลาย)
-- =============================================================

CREATE TABLE IF NOT EXISTS `big_project_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `big_project_id` INT NOT NULL,
  `project_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`big_project_id`) REFERENCES `big_projects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_bp_project` (`big_project_id`, `project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Indexes
CREATE INDEX idx_bpi_big_project ON `big_project_items`(`big_project_id`);
CREATE INDEX idx_bpi_project ON `big_project_items`(`project_id`);
