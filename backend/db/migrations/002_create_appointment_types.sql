-- 002_create_appointment_types.sql
-- Canonical durations live here. A booking's end_time is derived from this row.

CREATE TABLE appointment_types (
  id               CHAR(36)     NOT NULL,
  name             VARCHAR(120) NOT NULL,
  slug             VARCHAR(80)  NOT NULL,
  duration_minutes SMALLINT     NOT NULL,
  is_active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                         ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_appointment_types      PRIMARY KEY (id),
  CONSTRAINT uq_appointment_types_slug UNIQUE (slug),
  CONSTRAINT chk_duration              CHECK (duration_minutes > 0
                                         AND duration_minutes <= 480)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
