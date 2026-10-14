CREATE TABLE IF NOT EXISTS bad_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_no VARCHAR(80) NOT NULL,
    date_doc DATE NOT NULL,
    invoice_id INT NULL,
    po_number VARCHAR(255) NULL,
    customer_id INT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_address TEXT NULL,
    customer_pic VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bad_document_no (document_no),
    KEY idx_bad_date (date_doc),
    KEY idx_bad_po (po_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bad_photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bad_id INT UNSIGNED NOT NULL,
    photo_type ENUM('before','after') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bad_photos (bad_id, photo_type),
    CONSTRAINT fk_bad_photos_document FOREIGN KEY (bad_id) REFERENCES bad_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;