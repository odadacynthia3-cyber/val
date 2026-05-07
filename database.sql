-- database.sql
-- Run this SQL in phpMyAdmin (000webhost) to create required table.

CREATE TABLE IF NOT EXISTS messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    receiver_name VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    sender_type ENUM('boy', 'girl') NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
