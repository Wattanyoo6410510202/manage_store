CREATE TABLE IF NOT EXISTS repair_bridge_withdrawals (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    operation_key VARCHAR(64) NOT NULL UNIQUE,
    payload_hash CHAR(64) NOT NULL,
    repair_request_id INT NOT NULL,
    repair_number VARCHAR(50) NOT NULL,
    source_actor_id INT NOT NULL,
    store_actor_id INT NOT NULL,
    sup_id INT NOT NULL,
    withdrawal_id BIGINT NULL UNIQUE,
    cancelled TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_repair_bridge_request (repair_request_id, sup_id),
    FOREIGN KEY (withdrawal_id) REFERENCES stock_withdrawals(id),
    FOREIGN KEY (store_actor_id) REFERENCES users(id),
    FOREIGN KEY (sup_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
