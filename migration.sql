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

-- 2. Pastikan kolom email & cc_email ada di tabel customers
-- (Biasanya sudah ada, tapi jaga-jaga)

ALTER TABLE customers 
  ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS cc_email TEXT NULL;

-- ============================================
-- SELESAI! Cek dengan: DESCRIBE quotations; DESCRIBE customers;
-- ============================================
