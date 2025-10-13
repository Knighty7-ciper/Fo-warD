-- Backup System Migration
-- Creates table for tracking backups

CREATE TABLE IF NOT EXISTS backups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL,
    backup_type ENUM('manual', 'automatic', 'scheduled') DEFAULT 'manual',
    status ENUM('in_progress', 'completed', 'failed') DEFAULT 'in_progress',
    tables_included TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    error_message TEXT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created_at (created_at),
    INDEX idx_status (status)
);

CREATE TABLE IF NOT EXISTS backup_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    auto_backup_enabled BOOLEAN DEFAULT TRUE,
    backup_frequency ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily',
    backup_time TIME DEFAULT '02:00:00',
    retention_days INT DEFAULT 30,
    max_backups INT DEFAULT 10,
    include_uploads BOOLEAN DEFAULT FALSE,
    notification_email VARCHAR(255),
    last_backup_at TIMESTAMP NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default backup settings
INSERT INTO backup_settings (auto_backup_enabled, backup_frequency, backup_time, retention_days, max_backups)
VALUES (TRUE, 'daily', '02:00:00', 30, 10);
