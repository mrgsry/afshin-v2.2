-- ============================================
-- SQL Migration - CRM Afshin v2
-- Fitur: Email Quotation PDF
-- Tanggal: 11 Juni 2026
-- ============================================

-- 1. Tambah kolom date_quot dan po_number ke tabel quotations
-- (Jalankan di phpMyAdmin hosting jika kolom belum ada)

ALTER TABLE quotations 
  ADD COLUMN IF NOT EXISTS date_quot DATE NULL AFTER quotation_no,
  ADD COLUMN IF NOT EXISTS po_number VARCHAR(50) NULL AFTER date_quot;

-- Diskon per item quotation. Amount menyimpan nilai setelah diskon item.
ALTER TABLE quotation_items
  ADD COLUMN IF NOT EXISTS discount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER unit_price;

-- 2. Pastikan kolom email & cc_email ada di tabel customers
-- (Biasanya sudah ada, tapi jaga-jaga)

ALTER TABLE customers 
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS cc_email TEXT NULL;

ALTER TABLE users
  MODIFY COLUMN role ENUM('admin','user','staff','guest') NOT NULL DEFAULT 'guest';

UPDATE users SET role = 'staff' WHERE role = 'user';

ALTER TABLE users
  MODIFY COLUMN role ENUM('admin','staff','guest') NOT NULL DEFAULT 'guest';

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS job_position VARCHAR(255) NULL AFTER full_name,
  ADD COLUMN IF NOT EXISTS photo_path VARCHAR(255) NULL AFTER job_position;

CREATE TABLE IF NOT EXISTS user_modules (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  module_name VARCHAR(50) NOT NULL,
  policy ENUM('full','read') NOT NULL DEFAULT 'read',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_modules (user_id, module_name),
  CONSTRAINT fk_user_modules_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE user_modules
  ADD COLUMN IF NOT EXISTS policy ENUM('full','read') NOT NULL DEFAULT 'read' AFTER module_name;

UPDATE user_modules SET policy = 'full' WHERE policy IS NULL OR policy = '';

-- ============================================
-- SELESAI! Cek dengan: DESCRIBE quotations; DESCRIBE customers;
-- ============================================

-- Modul Karyawan dan Slip Gaji.
CREATE TABLE IF NOT EXISTS employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_no VARCHAR(50) NOT NULL,
  name VARCHAR(255) NOT NULL,
  employee_level VARCHAR(255) NOT NULL,
  signature_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employees_employee_no (employee_no),
  KEY idx_employees_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE employees
  ADD COLUMN IF NOT EXISTS signature_path VARCHAR(255) NULL AFTER employee_level;

CREATE TABLE IF NOT EXISTS payslips (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payslip_no VARCHAR(50) NOT NULL,
  employee_id INT UNSIGNED NULL,
  employee_no VARCHAR(50) NOT NULL,
  employee_name VARCHAR(255) NOT NULL,
  employee_level VARCHAR(255) NOT NULL,
  salary_period DATE NOT NULL,
  issued_date DATE NOT NULL,
  net_salary DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  description TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payslips_payslip_no (payslip_no),
  KEY idx_payslips_employee_id (employee_id),
  KEY idx_payslips_salary_period (salary_period),
  CONSTRAINT fk_payslips_employee FOREIGN KEY (employee_id) REFERENCES employees(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE payslips
  ADD COLUMN IF NOT EXISTS invoice_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER issued_date,
  ADD COLUMN IF NOT EXISTS rate_per_invoice DECIMAL(15,2) NOT NULL DEFAULT 350000.00 AFTER invoice_count,
  ADD COLUMN IF NOT EXISTS gross_salary DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER rate_per_invoice,
  ADD COLUMN IF NOT EXISTS pph21_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER gross_salary,
  ADD COLUMN IF NOT EXISTS salary_method ENUM('invoice','custom') NOT NULL DEFAULT 'invoice' AFTER pph21_amount;

CREATE TABLE IF NOT EXISTS payslip_invoices (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payslip_id INT UNSIGNED NOT NULL,
  invoice_id INT NOT NULL,
  invoice_no VARCHAR(100) NOT NULL,
  customer_name VARCHAR(255) NOT NULL,
  po_number VARCHAR(100) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payslip_invoices_invoice_id (invoice_id),
  KEY idx_payslip_invoices_payslip_id (payslip_id),
  CONSTRAINT fk_payslip_invoices_payslip FOREIGN KEY (payslip_id) REFERENCES payslips(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep long PO references intact in invoices and Berita Acara.
ALTER TABLE invoices MODIFY COLUMN po_number VARCHAR(255) NULL;
ALTER TABLE berita_acara MODIFY COLUMN po_number VARCHAR(255) NULL;

-- Catatan tambahan pada Berita Acara.
ALTER TABLE berita_acara
  ADD COLUMN IF NOT EXISTS note TEXT NULL AFTER pelaksana;

-- Pulihkan nomor PO BA lama dari invoice sumber jika sebelumnya tersimpan
-- hanya sebagai prefix (contoh: 9030).
UPDATE berita_acara b
INNER JOIN invoices i ON i.id = b.invoice_id
SET b.po_number = i.po_number
WHERE i.po_number IS NOT NULL
  AND i.po_number <> ''
  AND (b.po_number IS NULL OR b.po_number = '' OR BINARY b.po_number <> BINARY i.po_number);
