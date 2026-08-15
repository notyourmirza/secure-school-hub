USE school_saas;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_recipient (school_id,user_id,read_at,created_at)
) ENGINE=InnoDB;

ALTER TABLE subscription_plans ADD COLUMN IF NOT EXISTS max_classes INT UNSIGNED NOT NULL DEFAULT 100;
ALTER TABLE subscription_plans ADD COLUMN IF NOT EXISTS max_storage_mb INT UNSIGNED NOT NULL DEFAULT 1024;
-- The existing attendance migration already defines uq_attendance_client on (school_id, client_uuid).
ALTER TABLE marks ADD COLUMN IF NOT EXISTS submitted_at DATETIME NULL;
ALTER TABLE marks ADD COLUMN IF NOT EXISTS submitted_by BIGINT UNSIGNED NULL;
