-- Optms Rx — recorded prescriptions
-- The prescriptions page can save a prescription that is not a sale.
-- Existing tables are not altered. doctors, customers and medicines are optional links.
-- MySQL 5.7 / MariaDB. Safe to re-run.

SET @db = DATABASE();

CREATE TABLE IF NOT EXISTS prescriptions (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  rx_no varchar(30) NOT NULL,
  rx_date date NOT NULL,
  customer_id int(10) UNSIGNED DEFAULT NULL,
  patient_name varchar(150) NOT NULL,
  doctor_id int(10) UNSIGNED DEFAULT NULL,
  diagnosis varchar(255) DEFAULT NULL,
  status varchar(20) NOT NULL DEFAULT 'Recorded',
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_prescriptions_rx_no (rx_no),
  KEY idx_prescriptions_rx_date (rx_date),
  KEY idx_prescriptions_customer (customer_id),
  KEY idx_prescriptions_doctor (doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS prescription_items (
  id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  prescription_id int(10) UNSIGNED NOT NULL,
  medicine_id int(10) UNSIGNED DEFAULT NULL,
  medicine_name varchar(200) NOT NULL,
  dosage varchar(80) DEFAULT NULL,
  frequency varchar(80) DEFAULT NULL,
  duration varchar(80) DEFAULT NULL,
  qty int(11) NOT NULL DEFAULT 1,
  instructions varchar(255) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_prescription_items_rx (prescription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'prescriptions' AND CONSTRAINT_NAME = 'prescriptions_customer_fk'
    ),
    'SELECT 1',
    'ALTER TABLE prescriptions ADD CONSTRAINT prescriptions_customer_fk FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'prescriptions' AND CONSTRAINT_NAME = 'prescriptions_doctor_fk'
    ),
    'SELECT 1',
    'ALTER TABLE prescriptions ADD CONSTRAINT prescriptions_doctor_fk FOREIGN KEY (doctor_id) REFERENCES doctors (id) ON DELETE SET NULL'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'prescription_items' AND CONSTRAINT_NAME = 'prescription_items_rx_fk'
    ),
    'SELECT 1',
    'ALTER TABLE prescription_items ADD CONSTRAINT prescription_items_rx_fk FOREIGN KEY (prescription_id) REFERENCES prescriptions (id) ON DELETE CASCADE'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE OR REPLACE VIEW v_prescriptions AS
SELECT
  p.id AS id,
  p.rx_no AS rx_no,
  p.rx_date AS rx_date,
  p.customer_id AS customer_id,
  p.patient_name AS patient_name,
  p.doctor_id AS doctor_id,
  COALESCE(d.name, '') AS doctor_name,
  COALESCE(d.specialty, '') AS specialty,
  COALESCE(p.diagnosis, '') AS diagnosis,
  p.status AS status,
  p.created_at AS created_at,
  COALESCE(it.item_count, 0) AS item_count
FROM prescriptions p
LEFT JOIN doctors d ON d.id = p.doctor_id
LEFT JOIN (
  SELECT prescription_id, COUNT(*) AS item_count
  FROM prescription_items
  GROUP BY prescription_id
) it ON it.prescription_id = p.id;
