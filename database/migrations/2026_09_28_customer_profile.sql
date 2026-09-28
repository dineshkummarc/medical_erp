-- Optms Rx — customer category and business name
-- Adds optional org_type (Hospital, Clinic, Others) and business_name.
-- Does not change the existing retail/wholesale type column.
-- MySQL 5.7 / MariaDB. Safe to re-run.

SET @db = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'org_type'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD COLUMN org_type VARCHAR(20) NULL AFTER type'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'business_name'
    ),
    'SELECT 1',
    'ALTER TABLE customers ADD COLUMN business_name VARCHAR(150) NULL AFTER name'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
