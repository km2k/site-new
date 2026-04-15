-- ============================================================
--  Migration: Add morning_service & evening_service columns
--  Run this on the existing services table.
-- ============================================================

-- Add the two new columns
ALTER TABLE services
  ADD COLUMN morning_service VARCHAR(255) NULL COMMENT 'Наименование на сутрешната служба' AFTER end_time,
  ADD COLUMN evening_service VARCHAR(255) NULL COMMENT 'Наименование на вечерната служба' AFTER morning_service;

-- Populate morning_service from the existing title column
UPDATE services SET morning_service = title WHERE morning_service IS NULL;

-- Set a default evening service name
UPDATE services SET evening_service = 'Вечерня' WHERE evening_service IS NULL;

