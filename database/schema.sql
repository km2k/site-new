-- ============================================================
--  Храм „Света Троица" – Church Services Database Schema
--  Таблица: services
--
--  База данни: svetnsdc_BQz (зададена от хостинга)
--  Изберете базата в phpMyAdmin преди да импортирате.
-- ============================================================


-- ------------------------------------------------------------
--  services – one row per individual service occurrence
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title           VARCHAR(255)    NOT NULL COMMENT 'Наименование на службата',
  description     TEXT            NULL     COMMENT 'Допълнително описание',
  service_date    DATE            NOT NULL COMMENT 'Дата на службата',
  start_time      TIME            NOT NULL COMMENT 'Начален час на сутрешна служба',
  end_time        TIME            NULL     COMMENT 'Начален час на вечерна служба',
  morning_service VARCHAR(255)    NULL     COMMENT 'Наименование на сутрешната служба',
  evening_service VARCHAR(255)    NULL     COMMENT 'Наименование на вечерната служба',
  day_of_week     TINYINT UNSIGNED NOT NULL COMMENT '1=Понеделник … 7=Неделя (ISO)',
  priest          VARCHAR(255)    NULL     COMMENT 'Отговорен свещенослужител',
  feast           VARCHAR(255)    DEFAULT '' COMMENT 'Честване на деня',
  created_at      DATETIME        DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_date    (service_date),
  INDEX idx_day     (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

