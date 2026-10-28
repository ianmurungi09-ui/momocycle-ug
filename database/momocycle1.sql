-- ============================================================
-- MoMoCycle UG : complete database (MySQL 8.0.16+ / MariaDB 10.2+)
-- Demo password for all sample users: Pass1234
-- Safe to re-run: it drops and rebuilds everything.
-- ============================================================
DROP DATABASE IF EXISTS momocycle1;
CREATE DATABASE momocycle1 CHARACTER SET utf8mb4;
USE momocycle1;

-- ------------------------------------------------------------
-- 1. TABLES AND CONSTRAINTS
-- ------------------------------------------------------------
CREATE TABLE users (
  user_id        INT AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(100) NOT NULL,
  phone_number   VARCHAR(20)  NOT NULL UNIQUE,
  email          VARCHAR(100) UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('ADMIN','AGENT','COLLECTOR') NOT NULL DEFAULT 'COLLECTOR',
  wallet_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_wallet_nonneg CHECK (wallet_balance >= 0)
);

CREATE TABLE collection_hubs (
  hub_id               INT AUTO_INCREMENT PRIMARY KEY,
  hub_name             VARCHAR(100) NOT NULL UNIQUE,
  location_description VARCHAR(255) NOT NULL,
  agent_id             INT NULL,
  FOREIGN KEY (agent_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE material_rates (
  material_type ENUM('PET_PLASTIC','HDPE','ALUMINIUM') PRIMARY KEY,
  rate_per_kg   DECIMAL(10,2) NOT NULL,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_rate_pos CHECK (rate_per_kg > 0)
);

CREATE TABLE hub_stock (
  hub_id        INT NOT NULL,
  material_type ENUM('PET_PLASTIC','HDPE','ALUMINIUM') NOT NULL,
  stock_kg      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (hub_id, material_type),
  FOREIGN KEY (hub_id) REFERENCES collection_hubs(hub_id),
  FOREIGN KEY (material_type) REFERENCES material_rates(material_type),
  CONSTRAINT chk_stock_nonneg CHECK (stock_kg >= 0)
);

CREATE TABLE waste_deposits (
  deposit_id    INT AUTO_INCREMENT PRIMARY KEY,
  collector_id  INT NOT NULL,
  hub_id        INT NOT NULL,
  agent_id      INT NOT NULL,
  material_type ENUM('PET_PLASTIC','HDPE','ALUMINIUM') NOT NULL,
  weight_kg     DECIMAL(8,2)  NOT NULL,
  payout_amount DECIMAL(12,2) NOT NULL,
  status        ENUM('HUB_VERIFIED','PAID','REJECTED') NOT NULL DEFAULT 'HUB_VERIFIED',
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (collector_id)  REFERENCES users(user_id),
  FOREIGN KEY (agent_id)      REFERENCES users(user_id),
  FOREIGN KEY (hub_id)        REFERENCES collection_hubs(hub_id),
  FOREIGN KEY (material_type) REFERENCES material_rates(material_type),
  CONSTRAINT chk_weight_pos    CHECK (weight_kg > 0),
  CONSTRAINT chk_payout_nonneg CHECK (payout_amount >= 0),
  INDEX idx_dep_collector (collector_id),
  INDEX idx_dep_status (status)
);

CREATE TABLE transactions_ledger (
  ledger_id             INT AUTO_INCREMENT PRIMARY KEY,
  deposit_id            INT NOT NULL UNIQUE,          -- one payout per deposit
  recipient_phone       VARCHAR(20)  NOT NULL,
  amount                DECIMAL(12,2) NOT NULL,
  transaction_reference VARCHAR(100) NOT NULL UNIQUE,
  status                VARCHAR(30)  NOT NULL DEFAULT 'SIMULATED_SUCCESS',
  logged_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (deposit_id) REFERENCES waste_deposits(deposit_id),
  CONSTRAINT chk_ledger_amount CHECK (amount > 0)
);

CREATE TABLE audit_log (
  audit_id   INT AUTO_INCREMENT PRIMARY KEY,
  table_name VARCHAR(50) NOT NULL,
  record_id  INT NOT NULL,
  action     VARCHAR(30) NOT NULL,
  old_value  VARCHAR(100),
  new_value  VARCHAR(100),
  changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- 2. STORED PROCEDURES (each owns its own transaction)
-- ------------------------------------------------------------
DELIMITER //

CREATE PROCEDURE sp_log_deposit(
  IN  p_collector_id INT,
  IN  p_hub_id       INT,
  IN  p_agent_id     INT,
  IN  p_material     VARCHAR(20),
  IN  p_weight       DECIMAL(8,2),
  OUT p_deposit_id   INT)
BEGIN
  DECLARE v_rate DECIMAL(10,2);
  DECLARE v_role VARCHAR(20);
  DECLARE v_agent_role VARCHAR(20);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; RESIGNAL; END;

  IF p_weight IS NULL OR p_weight <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Weight must be greater than zero';
  END IF;

  START TRANSACTION;
    SELECT role INTO v_role FROM users WHERE user_id = p_collector_id FOR UPDATE;
    IF v_role IS NULL OR v_role <> 'COLLECTOR' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid collector';
    END IF;

    SELECT role INTO v_agent_role FROM users WHERE user_id = p_agent_id;
    IF v_agent_role IS NULL OR v_agent_role NOT IN ('AGENT','ADMIN') THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid agent';
    END IF;

    SELECT rate_per_kg INTO v_rate FROM material_rates WHERE material_type = p_material;
    IF v_rate IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Unknown material type';
    END IF;

    INSERT INTO waste_deposits
      (collector_id, hub_id, agent_id, material_type, weight_kg, payout_amount, status)
    VALUES
      (p_collector_id, p_hub_id, p_agent_id, p_material, p_weight,
       ROUND(p_weight * v_rate, 2), 'HUB_VERIFIED');
    SET p_deposit_id = LAST_INSERT_ID();
  COMMIT;
END //

CREATE PROCEDURE sp_process_payout(IN p_deposit_id INT)
BEGIN
  DECLARE v_status    VARCHAR(20);
  DECLARE v_collector INT;
  DECLARE v_amount    DECIMAL(12,2);
  DECLARE v_phone     VARCHAR(20);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
    -- Row lock: a concurrent payout of the same deposit waits here
    SELECT status, collector_id, payout_amount
      INTO v_status, v_collector, v_amount
      FROM waste_deposits WHERE deposit_id = p_deposit_id FOR UPDATE;

    IF v_status IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deposit not found';
    END IF;
    IF v_status <> 'HUB_VERIFIED' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deposit is not payable (already paid or rejected)';
    END IF;

    SELECT phone_number INTO v_phone FROM users WHERE user_id = v_collector FOR UPDATE;

    UPDATE users SET wallet_balance = wallet_balance + v_amount WHERE user_id = v_collector;
    UPDATE waste_deposits SET status = 'PAID' WHERE deposit_id = p_deposit_id;
    INSERT INTO transactions_ledger (deposit_id, recipient_phone, amount, transaction_reference)
      VALUES (p_deposit_id, v_phone, v_amount, CONCAT('MOMO-', UUID()));
  COMMIT;
END //

CREATE PROCEDURE sp_reject_deposit(IN p_deposit_id INT)
BEGIN
  DECLARE v_status VARCHAR(20);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN ROLLBACK; RESIGNAL; END;

  START TRANSACTION;
    SELECT status INTO v_status FROM waste_deposits WHERE deposit_id = p_deposit_id FOR UPDATE;
    IF v_status IS NULL OR v_status <> 'HUB_VERIFIED' THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Only verified deposits can be rejected';
    END IF;
    UPDATE waste_deposits SET status = 'REJECTED' WHERE deposit_id = p_deposit_id;
  COMMIT;
END //

-- ------------------------------------------------------------
-- 3. TRIGGERS
-- ------------------------------------------------------------
CREATE TRIGGER trg_deposit_after_insert
AFTER INSERT ON waste_deposits
FOR EACH ROW
BEGIN
  INSERT INTO hub_stock (hub_id, material_type, stock_kg)
  VALUES (NEW.hub_id, NEW.material_type, NEW.weight_kg)
  ON DUPLICATE KEY UPDATE stock_kg = stock_kg + NEW.weight_kg;
END //

CREATE TRIGGER trg_deposit_after_update
AFTER UPDATE ON waste_deposits
FOR EACH ROW
BEGIN
  IF OLD.status <> NEW.status THEN
    INSERT INTO audit_log (table_name, record_id, action, old_value, new_value)
    VALUES ('waste_deposits', NEW.deposit_id, 'STATUS_CHANGE', OLD.status, NEW.status);
  END IF;
  IF OLD.status = 'HUB_VERIFIED' AND NEW.status = 'REJECTED' THEN
    UPDATE hub_stock SET stock_kg = stock_kg - NEW.weight_kg
    WHERE hub_id = NEW.hub_id AND material_type = NEW.material_type;
  END IF;
END //

CREATE TRIGGER trg_deposit_before_delete
BEFORE DELETE ON waste_deposits
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deposits cannot be deleted';
END //

DELIMITER ;

-- ------------------------------------------------------------
-- 4. VIEWS
-- ------------------------------------------------------------
CREATE VIEW vw_daily_collection_summary AS
SELECT DATE(d.created_at) AS collection_date, h.hub_name, d.material_type,
       SUM(d.weight_kg)      AS total_weight_kg,
       SUM(d.payout_amount)  AS total_payout_ugx,
       COUNT(*)              AS total_dropoffs
FROM waste_deposits d JOIN collection_hubs h ON h.hub_id = d.hub_id
WHERE d.status <> 'REJECTED'
GROUP BY DATE(d.created_at), h.hub_name, d.material_type;

CREATE VIEW vw_hub_inventory AS
SELECT h.hub_name, s.material_type, s.stock_kg
FROM hub_stock s JOIN collection_hubs h ON h.hub_id = s.hub_id;

CREATE VIEW vw_pending_payouts AS
SELECT d.deposit_id, u.full_name, u.phone_number, d.material_type,
       d.weight_kg, d.payout_amount, d.created_at
FROM waste_deposits d JOIN users u ON u.user_id = d.collector_id
WHERE d.status = 'HUB_VERIFIED';

CREATE VIEW vw_collector_earnings AS
SELECT u.user_id, u.full_name, u.wallet_balance,
       COUNT(d.deposit_id)          AS total_deposits,
       COALESCE(SUM(d.weight_kg),0) AS total_kg
FROM users u LEFT JOIN waste_deposits d
  ON d.collector_id = u.user_id AND d.status <> 'REJECTED'
WHERE u.role = 'COLLECTOR'
GROUP BY u.user_id, u.full_name, u.wallet_balance;

-- ------------------------------------------------------------
-- 5. SAMPLE DATA  (password for every user: Pass1234)
-- ------------------------------------------------------------
INSERT INTO material_rates (material_type, rate_per_kg) VALUES
 ('PET_PLASTIC', 500.00), ('HDPE', 400.00), ('ALUMINIUM', 2000.00);

INSERT INTO users (full_name, phone_number, email, password_hash, role) VALUES
 ('System Admin',  '0700000001', 'admin@momocycle.ug', '$2y$10$KN6ngKwF.KbRGzTleZmswuUKvbdEJ.M.9fhRf4piRYE7H891eq.2e', 'ADMIN'),
 ('Hub Agent One', '0700000002', 'agent@momocycle.ug', '$2y$10$KN6ngKwF.KbRGzTleZmswuUKvbdEJ.M.9fhRf4piRYE7H891eq.2e', 'AGENT'),
 ('Collector One', '0700000003', NULL, '$2y$10$KN6ngKwF.KbRGzTleZmswuUKvbdEJ.M.9fhRf4piRYE7H891eq.2e', 'COLLECTOR'),
 ('Collector Two', '0700000004', NULL, '$2y$10$KN6ngKwF.KbRGzTleZmswuUKvbdEJ.M.9fhRf4piRYE7H891eq.2e', 'COLLECTOR');

INSERT INTO collection_hubs (hub_name, location_description, agent_id)
VALUES ('Main Hostel Hub', 'Near the campus main gate', 2);

-- Sample deposits made through the procedures (this also fires the triggers)
CALL sp_log_deposit(3, 1, 2, 'PET_PLASTIC', 4.50, @d1);
CALL sp_log_deposit(3, 1, 2, 'HDPE',        3.00, @d2);
CALL sp_log_deposit(4, 1, 2, 'ALUMINIUM',   1.25, @d3);
CALL sp_process_payout(@d1);      -- deposit 1 paid, deposits 2 and 3 left pending
