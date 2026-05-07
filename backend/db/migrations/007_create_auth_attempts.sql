CREATE TABLE IF NOT EXISTS auth_attempts (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash     CHAR(64)        NOT NULL COMMENT 'SHA-256 of client IP — never stored in plaintext',
  attempt_at  DATETIME        NOT NULL,
  CONSTRAINT pk_auth_attempts PRIMARY KEY (id),
  INDEX idx_auth_attempts_ip_attempt (ip_hash, attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
