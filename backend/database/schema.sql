CREATE DATABASE IF NOT EXISTS chickintel_inventory
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE chickintel_inventory;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  category VARCHAR(80) NOT NULL,
  quantity DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 0,
  unit VARCHAR(30) NOT NULL,
  unit_price DECIMAL(12,2) UNSIGNED NULL,
  expiration_date DATE NULL,
  notes VARCHAR(1000) NULL,
  reference_quantity DECIMAL(12,3) UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_inventory_user_name (user_id, name),
  KEY idx_inventory_user_category (user_id, category),
  CONSTRAINT fk_inventory_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NULL,
  item_name VARCHAR(120) NOT NULL,
  action ENUM('Item added', 'Item restocked', 'Item updated', 'Item deleted') NOT NULL,
  quantity_change DECIMAL(12,3) NULL,
  notes VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_history_user_created (user_id, created_at),
  CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_history_item FOREIGN KEY (item_id) REFERENCES inventory_items (id) ON DELETE SET NULL
) ENGINE=InnoDB;
