-- ==========================================================
-- 1. TABEL: travel_documents (Header Surat Jalan/Dokumen)
-- ==========================================================

CREATE TABLE `travel_documents` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `document_no` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nomor Surat Jalan (e.g., SJ/ART/2025/XI/001)',
    `customer_id` INT(11) NOT NULL COMMENT 'FK ke customers.id',
    `date_doc` DATE NOT NULL COMMENT 'Tanggal Dokumen Diterbitkan',
    `po_number` VARCHAR(100) DEFAULT NULL COMMENT 'Nomor PO yang direferensikan (dari input/select)',
    `note` TEXT DEFAULT NULL COMMENT 'Catatan tambahan di dokumen',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `fk_td_customer_id` (`customer_id`),
    KEY `idx_po_number` (`po_number`),
    -- Asumsi tabel customers sudah ada. Jika belum, hapus baris FOREIGN KEY
    -- CONSTRAINT `fk_td_customer_id` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ==========================================================
-- 2. TABEL: travel_document_items (Detail Item Dokumen)
-- ==========================================================

CREATE TABLE `travel_document_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `document_id` INT(11) NOT NULL COMMENT 'FK ke travel_documents.id',
    `item_desc` TEXT NOT NULL COMMENT 'Deskripsi barang yang dikirim',
    `qty` INT(11) NOT NULL DEFAULT 1 COMMENT 'Jumlah barang yang dikirim',
    `unit` VARCHAR(20) NOT NULL COMMENT 'Satuan (Pcs, Unit, Box, dll.)',
    `remarks` VARCHAR(255) DEFAULT NULL COMMENT 'Keterangan per item (misal: kondisi barang)',
    PRIMARY KEY (`id`),
    KEY `fk_tdi_document_id` (`document_id`),
    -- Asumsi tabel travel_documents sudah ada
    CONSTRAINT `fk_tdi_document_id` FOREIGN KEY (`document_id`) REFERENCES `travel_documents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;