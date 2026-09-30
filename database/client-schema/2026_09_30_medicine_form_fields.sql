-- Optms Rx — save every Add Medicine input
-- The shared edrppymy_sumati medicines table has no column for dosage form,
-- rack / shelf, default discount, discount type, or the generic-group chip text.
-- Batch number, opening quantity and expiry lived only on batches, and
-- batches.expiry_date was NOT NULL, so a save without a date dropped that stock.
-- Blank min stock, reorder level, wholesale rate, GST, purchase rate and box unit
-- were forced to 0 or "Box". This makes those blanks stay blank.
-- MySQL 5.7 / MariaDB. Safe to re-run.
--
-- api/v1/medicines.php writes these columns directly. If a Medicine model
-- whitelist is added later, allow:
--   dosage_form, rack, default_discount, discount_type, generic_group,
--   batch_no, opening_qty, expiry_date, min_stock, reorder_level,
--   wholesale_rate, purchase_rate, gst_rate, box_unit, box_qty,
--   expiry_alert_days, barcode, brand_ref, schedule_class, schedule_id,
--   pack_qty, sub_unit, allow_loose_sale, retail_rate, generic_group_id

SET @db = DATABASE();

-- dosage form (Tablet, Capsule, Syrup, ...)
SET @sql = (
  SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'dosage_form'),
    'ALTER TABLE medicines ADD COLUMN dosage_form VARCHAR(40) NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rack / shelf, e.g. A-3
SET @sql = (
  SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'rack'),
    'ALTER TABLE medicines ADD COLUMN rack VARCHAR(60) NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- default discount amount and percent/rupee selector
SET @sql = (
  SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'default_discount'),
    'ALTER TABLE medicines ADD COLUMN default_discount DECIMAL(10,2) NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'discount_type'),
    'ALTER TABLE medicines ADD COLUMN discount_type VARCHAR(10) NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- full generic/composition group chip text. generic_group_id still points at the first salt.
SET @sql = (
  SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'generic_group'),
    'ALTER TABLE medicines ADD COLUMN generic_group VARCHAR(500) NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- opening batch details also live on the medicine so they are not lost if the batch row cannot be inserted
SET @sql = (
  SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'batch_no'),
    'ALTER TABLE medicines ADD COLUMN batch_no VARCHAR(50) NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'opening_qty'),
    'ALTER TABLE medicines ADD COLUMN opening_qty INT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'expiry_date'),
    'ALTER TABLE medicines ADD COLUMN expiry_date DATE NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- blanks must not become 0 or "Box"
SET @sql = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'min_stock' AND IS_NULLABLE = 'NO'),
    'ALTER TABLE medicines MODIFY COLUMN min_stock INT NULL DEFAULT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'reorder_level' AND IS_NULLABLE = 'NO'),
    'ALTER TABLE medicines MODIFY COLUMN reorder_level INT NULL DEFAULT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'wholesale_rate' AND IS_NULLABLE = 'NO'),
    'ALTER TABLE medicines MODIFY COLUMN wholesale_rate DECIMAL(10,2) NULL DEFAULT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'purchase_rate' AND IS_NULLABLE = 'NO'),
    'ALTER TABLE medicines MODIFY COLUMN purchase_rate DECIMAL(10,2) NULL DEFAULT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'gst_rate' AND IS_NULLABLE = 'NO'),
    'ALTER TABLE medicines MODIFY COLUMN gst_rate DECIMAL(5,2) NULL DEFAULT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'box_unit'
        AND (IS_NULLABLE = 'NO' OR COLUMN_DEFAULT = 'Box')
    ),
    'ALTER TABLE medicines MODIFY COLUMN box_unit VARCHAR(30) NULL DEFAULT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- a batch can be saved before an expiry month is known
SET @sql = (
  SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'batches' AND COLUMN_NAME = 'expiry_date' AND IS_NULLABLE = 'NO'),
    'ALTER TABLE batches MODIFY COLUMN expiry_date DATE NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
