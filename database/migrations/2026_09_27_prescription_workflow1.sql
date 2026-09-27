-- Optms Rx — prescription packing workflow
-- Adds optional patient age and mobile for the ledger. Status stays a short text column.
-- Pending, Ready, Dispensed and Cancelled are stored in the existing status column.
-- MySQL 5.7 / MariaDB. Safe to re-run.

SET @db = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'prescriptions' AND COLUMN_NAME = 'patient_age'
    ),
    'SELECT 1',
    'ALTER TABLE prescriptions ADD COLUMN patient_age SMALLINT UNSIGNED NULL AFTER patient_name'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE prescriptions SET status = 'Pending' WHERE status IS NULL OR status = '' OR status = 'Recorded';

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'prescriptions' AND COLUMN_NAME = 'patient_phone'
    ),
    'SELECT 1',
    'ALTER TABLE prescriptions ADD COLUMN patient_phone VARCHAR(32) NULL AFTER patient_age'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
