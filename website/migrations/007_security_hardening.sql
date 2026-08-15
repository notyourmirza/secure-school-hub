-- 007_security_hardening.sql
-- Persistent security state: request rate limiting, login throttling and
-- token hygiene. Idempotent so it can be re-run safely.

CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket VARCHAR(64) NOT NULL,
  identifier VARCHAR(190) NOT NULL,
  window_started_at DATETIME NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rate_limit (bucket, identifier),
  KEY idx_rate_limit_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  successful TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_attempt_email (email, attempted_at),
  KEY idx_login_attempt_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index guards written as dynamic SQL so the migration is safe on both
-- MySQL 8 and MariaDB (neither supports CREATE INDEX IF NOT EXISTS portably).
SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'audit_logs'
               AND index_name = 'idx_audit_logs_school_created');
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_audit_logs_school_created ON audit_logs (school_id, created_at)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'access_tokens'
               AND index_name = 'idx_access_tokens_user');
SET @sql := IF(@idx = 0,
  'CREATE INDEX idx_access_tokens_user ON access_tokens (user_id, revoked_at)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
