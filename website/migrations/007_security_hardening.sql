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

-- Access tokens are stored as SHA-256 digests, never in plaintext.
ALTER TABLE api_tokens
  ADD COLUMN IF NOT EXISTS revoked_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS last_used_at DATETIME NULL DEFAULT NULL;

CREATE INDEX IF NOT EXISTS idx_api_tokens_expiry ON api_tokens (expires_at);
CREATE INDEX IF NOT EXISTS idx_audit_logs_school_created ON audit_logs (school_id, created_at);
