-- Optms Rx — doctor profile columns for the Add Doctor modal
-- Existing doctors rows are kept. name, specialty, phone and reg_no are not changed.
-- Adds email, clinic, address and status so Save Doctor can store the full form.
-- MySQL 5.7 / MariaDB. Safe to re-run.
--
-- If models/Doctor.php whitelists columns, also allow:
--   email, clinic, address, status

SET @db = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'email'
    ),
    'SELECT 1',
    'ALTER TABLE doctors ADD COLUMN email VARCHAR(160) NULL AFTER reg_no'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'clinic'
    ),
    'SELECT 1',
    'ALTER TABLE doctors ADD COLUMN clinic VARCHAR(150) NULL AFTER email'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'address'
    ),
    'SELECT 1',
    'ALTER TABLE doctors ADD COLUMN address VARCHAR(255) NULL AFTER clinic'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'status'
    ),
    'SELECT 1',
    'ALTER TABLE doctors ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT ''Active'' AFTER address'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE doctors SET status = 'Active' WHERE status IS NULL OR status = '';

-- Mobile on the modal can include a +91 prefix and spaces. The original phone column is only 20 characters.
SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'doctors' AND COLUMN_NAME = 'phone'
        AND CHARACTER_MAXIMUM_LENGTH IS NOT NULL AND CHARACTER_MAXIMUM_LENGTH < 32
    ),
    'ALTER TABLE doctors MODIFY COLUMN phone VARCHAR(32) NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
