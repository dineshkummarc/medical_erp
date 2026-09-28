-- Optms Rx — customer type and business name
-- Widens customers.type so Hospital, Clinic and Others can be stored.
-- Adds optional business_name.
-- MySQL 5.7 / MariaDB. Safe to re-run.

SET @db = DATABASE();

-- Widen type so Hospital, Clinic and Others fit beside retail and wholesale.
SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'customers' AND COLUMN_NAME = 'type'
        AND DATA_TYPE = 'enum'
    ),
    'ALTER TABLE customers MODIFY COLUMN type VARCHAR(20) NOT NULL DEFAULT ''retail'''
    ,
    'SELECT 1'
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
