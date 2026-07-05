-- =============================================
-- SQL Migration: Cashflow Operational Module
-- =============================================

CREATE TABLE IF NOT EXISTS `cashflow_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transaction_date` DATE NOT NULL,
    `technician_name` VARCHAR(100) NOT NULL,
    `category` ENUM('BBM', 'Tol', 'Sparepart', 'Lainnya') NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `description` TEXT,
    `photo_path` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date` (`transaction_date`),
    INDEX `idx_category` (`category`),
    INDEX `idx_technician` (`technician_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;