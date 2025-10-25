-- Migration: Create password_resets table for password reset functionality

CREATE TABLE IF NOT EXISTS `password_resets` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `email` varchar(255) NOT NULL,
    `token` varchar(255) NOT NULL,
    `expires_at` datetime NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_email` (`email`),
    KEY `idx_token` (`token`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
