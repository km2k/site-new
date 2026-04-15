-- ============================================================
--  Храм „Света Троица" – Users Table Schema
--  Таблица: users
--
--  База данни: svetnsdc_BQz (зададена от хостинга)
--  Изберете базата в phpMyAdmin преди да импортирате.
-- ============================================================


-- ------------------------------------------------------------
--  users – site users with admin flag
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(100)    NOT NULL UNIQUE COMMENT 'Потребителско име',
  email         VARCHAR(255)    NOT NULL UNIQUE COMMENT 'Имейл адрес',
  password_hash VARCHAR(255)    NOT NULL       COMMENT 'bcrypt хеш на паролата',
  display_name  VARCHAR(255)    NULL           COMMENT 'Показвано име',
  is_admin      TINYINT(1)      DEFAULT 0      COMMENT '1 = администратор',
  is_active     TINYINT(1)      DEFAULT 1      COMMENT '0 = деактивиран акаунт',
  created_at    DATETIME        DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_admin   (is_admin),
  INDEX idx_active  (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Default admin user: root / root  — CHANGE PASSWORD IN PRODUCTION!
-- ------------------------------------------------------------
INSERT INTO users (username, email, password_hash, display_name, is_admin, is_active) VALUES
  ('root', 'sveta.troica.sofia@gmail.com',
   '$2y$12$hRGyUdf/cZfpzE6sOThDzOCIncb/rUIAOfz2LJ8URLkDzTX3oiwG2',
   'Root Администратор', 1, 1);

