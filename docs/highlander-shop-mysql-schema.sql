-- Highlander net shop database schema for Xserver MySQL
-- Apply this to the MySQL database configured by SHOP_DB_* env vars.

CREATE TABLE IF NOT EXISTS shop_customers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  google_uid VARCHAR(128) NULL,
  email VARCHAR(255) NOT NULL,
  name VARCHAR(255) NOT NULL DEFAULT '',
  phone VARCHAR(64) NOT NULL DEFAULT '',
  shipping_address TEXT NULL,
  last_order_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shop_customers_google_uid (google_uid),
  KEY idx_shop_customers_email (email),
  KEY idx_shop_customers_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id VARCHAR(80) NOT NULL,
  customer_id BIGINT UNSIGNED NULL,
  google_uid VARCHAR(128) NULL,
  customer_email VARCHAR(255) NOT NULL DEFAULT '',
  customer_name VARCHAR(255) NOT NULL DEFAULT '',
  customer_phone VARCHAR(64) NOT NULL DEFAULT '',
  shipping_address TEXT NULL,
  amount_total INT UNSIGNED NOT NULL DEFAULT 0,
  currency VARCHAR(12) NOT NULL DEFAULT 'jpy',
  payment_method VARCHAR(40) NOT NULL DEFAULT '',
  payment_status VARCHAR(80) NOT NULL DEFAULT '',
  status VARCHAR(80) NOT NULL DEFAULT '',
  stripe_session_id VARCHAR(255) NULL,
  stripe_payment_status VARCHAR(80) NULL,
  stripe_amount_total INT UNSIGNED NULL,
  hublink_result LONGTEXT NULL,
  raw_order LONGTEXT NOT NULL,
  paid_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shop_orders_order_id (order_id),
  KEY idx_shop_orders_customer_id (customer_id),
  KEY idx_shop_orders_google_uid (google_uid),
  KEY idx_shop_orders_customer_email (customer_email),
  KEY idx_shop_orders_payment_status (payment_status),
  KEY idx_shop_orders_created_at (created_at),
  CONSTRAINT fk_shop_orders_customer
    FOREIGN KEY (customer_id) REFERENCES shop_customers(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id VARCHAR(80) NOT NULL,
  product_id VARCHAR(128) NOT NULL DEFAULT '',
  product_name VARCHAR(255) NOT NULL DEFAULT '',
  unit_price INT UNSIGNED NOT NULL DEFAULT 0,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  subtotal INT UNSIGNED NOT NULL DEFAULT 0,
  raw_item LONGTEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_shop_order_items_order_id (order_id),
  KEY idx_shop_order_items_product_id (product_id),
  CONSTRAINT fk_shop_order_items_order
    FOREIGN KEY (order_id) REFERENCES shop_orders(order_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
