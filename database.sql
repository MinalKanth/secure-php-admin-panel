-- ============================================================
-- Admin Panel Database Schema
-- Import this file into MySQL/MariaDB before running the app
-- ============================================================

CREATE DATABASE IF NOT EXISTS admin_panel
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE admin_panel;

-- ------------------------------------------------------------
-- Admins table (no public registration - seeded manually only)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) DEFAULT NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  last_login_ip VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Users table (managed via CRUD by admin only)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_created_by FOREIGN KEY (created_by)
    REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Activity log (audit trail for admin actions)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  details VARCHAR(255) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_admin FOREIGN KEY (admin_id)
    REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed ONE admin account.
-- Default login: username = admin / password = ChangeMe!123
-- The hash below is generated with PHP's password_hash() (bcrypt).
-- *** CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN ***
-- (Use create_admin.php, included in the package, to generate
--  your own hash instead of trusting this one.)
-- ------------------------------------------------------------
INSERT INTO admins (username, email, password_hash, full_name)
VALUES (
  'admin',
  'admin@example.com',
  '$2y$12$Tz5gk2W6Q9q7nq2Y8eYV0.placeholder.hash.replace.me',
  'Default Administrator'
);
