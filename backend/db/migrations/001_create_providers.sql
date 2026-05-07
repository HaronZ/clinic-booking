-- 001_create_providers.sql
-- Providers (doctors / practitioners). UUID PKs, slug for human-readable URLs.

CREATE TABLE providers (
  id          CHAR(36)     NOT NULL,
  name        VARCHAR(120) NOT NULL,
  specialty   VARCHAR(120) NOT NULL,
  slug        VARCHAR(80)  NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_providers       PRIMARY KEY (id),
  CONSTRAINT uq_providers_slug  UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_providers_active ON providers (is_active);
