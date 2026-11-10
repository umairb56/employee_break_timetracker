-- Break Tracker database (MySQL 5.7+ / MariaDB 10.3+)
CREATE DATABASE IF NOT EXISTS break_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE break_tracker;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','employee') NOT NULL DEFAULT 'employee',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_utc DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE break_policies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  daily_allowance_minutes SMALLINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE employees (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL UNIQUE,
  employee_code VARCHAR(30) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  policy_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (policy_id) REFERENCES break_policies(id)
) ENGINE=InnoDB;

-- All timestamps are UTC. open_employee_id is filled only while a break is open;
-- its UNIQUE index makes it impossible for the database to hold two open breaks per employee.
CREATE TABLE break_records (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  employee_id INT UNSIGNED NOT NULL,
  start_utc DATETIME NOT NULL,
  end_utc DATETIME NULL,
  was_corrected TINYINT(1) NOT NULL DEFAULT 0,
  open_employee_id INT UNSIGNED GENERATED ALWAYS AS (IF(end_utc IS NULL, employee_id, NULL)) STORED,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_one_open_break (open_employee_id),
  KEY idx_emp_start (employee_id, start_utc),
  KEY idx_start (start_utc),
  FOREIGN KEY (employee_id) REFERENCES employees(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  entity_type VARCHAR(30) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  details TEXT NULL,
  reason VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at)
) ENGINE=InnoDB;

-- Small key/value table for the admin-configurable settings
CREATE TABLE settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

INSERT INTO settings VALUES
  ('company_timezone','UTC'),
  ('warn_percent','80'),
  ('forgotten_minutes','180');

INSERT INTO break_policies (name, daily_allowance_minutes) VALUES ('Standard (60 min)', 60);
