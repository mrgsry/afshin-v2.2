-- Jalankan satu kali pada database aplikasi.
CREATE TABLE IF NOT EXISTS ai_settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider VARCHAR(30) NOT NULL,
    base_url VARCHAR(500) NOT NULL DEFAULT 'https://generativelanguage.googleapis.com/v1beta',
    model VARCHAR(150) NOT NULL,
    api_key_encrypted TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ai_settings_provider (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ai_settings ADD COLUMN IF NOT EXISTS base_url VARCHAR(500) NOT NULL DEFAULT 'https://generativelanguage.googleapis.com/v1beta' AFTER provider;