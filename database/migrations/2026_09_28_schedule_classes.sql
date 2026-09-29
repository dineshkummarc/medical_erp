-- Optms Rx — Schedule / Class master
-- Drug schedules (OTC, G, H, H1, X, NDPS) and therapeutic classes live in one ledger.
-- Medicines keep their existing schedule text. schedule_id / class_id are added only
-- when the medicines table is present, then schedule_id is filled from medicines.schedule.
-- MySQL 5.7 / MariaDB. Safe to re-run.
--
-- If a model whitelist is added later, allow:
--   kind, code, name, description, rx_required, register_required, status, sort_order

SET @db = DATABASE();

CREATE TABLE IF NOT EXISTS schedule_classes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind VARCHAR(16) NOT NULL,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(80) NOT NULL,
  description VARCHAR(255) NULL,
  rx_required TINYINT(1) NOT NULL DEFAULT 0,
  register_required TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(16) NOT NULL DEFAULT 'Active',
  sort_order INT NOT NULL DEFAULT 100,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_schedule_classes_kind_code (kind, code)
);

SET @sql = (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines'
    ) OR EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'schedule_id'
    ),
    'SELECT 1',
    'ALTER TABLE medicines ADD COLUMN schedule_id INT UNSIGNED NULL'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    NOT EXISTS (
      SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines'
    ) OR EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'class_id'
    ),
    'SELECT 1',
    'ALTER TABLE medicines ADD COLUMN class_id INT UNSIGNED NULL'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND INDEX_NAME = 'idx_medicines_schedule_id'
    ) OR NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'schedule_id'
    ),
    'SELECT 1',
    'ALTER TABLE medicines ADD INDEX idx_medicines_schedule_id (schedule_id)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND INDEX_NAME = 'idx_medicines_class_id'
    ) OR NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'class_id'
    ),
    'SELECT 1',
    'ALTER TABLE medicines ADD INDEX idx_medicines_class_id (class_id)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'schedule', 'OTC', 'Over the counter', 'No prescription. Sold against a bill only.', 0, 0, 'Active', 10
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'schedule' AND code = 'OTC');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'schedule', 'G', 'Schedule G', 'Caution label. Keep the prescription with the bill.', 1, 0, 'Active', 20
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'schedule' AND code = 'G');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'schedule', 'H', 'Schedule H', 'Prescription medicine under the Drugs and Cosmetics Rules.', 1, 0, 'Active', 30
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'schedule' AND code = 'H');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'schedule', 'H1', 'Schedule H1', 'Prescription plus the H1 register. Do not sell without a valid Rx.', 1, 1, 'Active', 40
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'schedule' AND code = 'H1');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'schedule', 'X', 'Schedule X', 'Restricted prescription medicine. Record the Rx in the Schedule X register.', 1, 1, 'Active', 50
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'schedule' AND code = 'X');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'schedule', 'NDPS', 'Narcotic / psychotropic', 'NDPS medicine. Prescription and the narcotic register are both required.', 1, 1, 'Active', 60
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'schedule' AND code = 'NDPS');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'class', 'Analgesic', 'Analgesic & antipyretic', 'Pain and fever medicines.', 0, 0, 'Active', 10
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'class' AND code = 'Analgesic');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'class', 'Antibiotic', 'Antibiotic', 'Antibacterial medicines.', 0, 0, 'Active', 20
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'class' AND code = 'Antibiotic');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'class', 'Antacid', 'Antacid & PPI', 'Acidity and ulcer medicines.', 0, 0, 'Active', 30
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'class' AND code = 'Antacid');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'class', 'Cardiac', 'Cardiovascular', 'Blood pressure, heart and lipid medicines.', 0, 0, 'Active', 40
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'class' AND code = 'Cardiac');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'class', 'Diabetes', 'Antidiabetic', 'Diabetes and insulin medicines.', 0, 0, 'Active', 50
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'class' AND code = 'Diabetes');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'class', 'Respiratory', 'Respiratory', 'Cough, cold, asthma and inhalers.', 0, 0, 'Active', 60
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'class' AND code = 'Respiratory');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'class', 'Vitamin', 'Vitamin & supplement', 'Vitamins, minerals and nutritional supplements.', 0, 0, 'Active', 70
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'class' AND code = 'Vitamin');

INSERT INTO schedule_classes (kind, code, name, description, rx_required, register_required, status, sort_order)
SELECT 'class', 'Derma', 'Dermatological', 'Skin, cream and ointment medicines.', 0, 0, 'Active', 80
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM schedule_classes WHERE kind = 'class' AND code = 'Derma');

-- Link existing medicines to the schedule master when the text column is already filled.
SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'schedule'
    ) AND EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'medicines' AND COLUMN_NAME = 'schedule_id'
    ),
    'UPDATE medicines m
       JOIN schedule_classes sc
         ON sc.kind = ''schedule'' AND sc.code = m.schedule
       SET m.schedule_id = sc.id
       WHERE m.schedule_id IS NULL AND m.schedule IS NOT NULL AND m.schedule <> ''''',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
