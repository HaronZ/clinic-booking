-- schema.sql — table structure only. No demo data.
-- Used by: backend/scripts/migrate.php
-- Safe to re-run: all statements use CREATE TABLE IF NOT EXISTS.
-- For demo data run: php backend/scripts/migrate.php --demo

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. providers ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS providers (
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

-- ── 2. appointment_types ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS appointment_types (
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

-- ── 3. provider_schedules ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS provider_schedules (
  id           CHAR(36)  NOT NULL,
  provider_id  CHAR(36)  NOT NULL,
  day_of_week  TINYINT   NOT NULL COMMENT '0=Sunday 1=Monday ... 6=Saturday',
  start_time   TIME      NOT NULL,
  end_time     TIME      NOT NULL,
  CONSTRAINT pk_provider_schedules PRIMARY KEY (id),
  CONSTRAINT fk_ps_provider        FOREIGN KEY (provider_id)
    REFERENCES providers(id) ON DELETE CASCADE,
  CONSTRAINT uq_ps_provider_day    UNIQUE (provider_id, day_of_week),
  CONSTRAINT chk_ps_times          CHECK (end_time > start_time),
  CONSTRAINT chk_ps_day            CHECK (day_of_week BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_ps_provider_day ON provider_schedules (provider_id, day_of_week);

-- ── 4. appointments ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS appointments (
  id                   CHAR(36)     NOT NULL,
  provider_id          CHAR(36)     NOT NULL,
  appointment_type_id  CHAR(36)     NOT NULL,
  patient_name         VARCHAR(160) NOT NULL  COMMENT 'PRIVACY: never log',
  patient_email        VARCHAR(254) NULL      COMMENT 'PRIVACY: never log',
  patient_phone        VARCHAR(30)  NOT NULL,
  patient_notes        TEXT         NULL,
  start_time           DATETIME     NOT NULL,
  end_time             DATETIME     NOT NULL,
  status               ENUM('pending','confirmed','completed','cancelled')
                                    NOT NULL DEFAULT 'pending',
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_appointments   PRIMARY KEY (id),
  CONSTRAINT fk_appt_provider  FOREIGN KEY (provider_id)
    REFERENCES providers(id),
  CONSTRAINT fk_appt_type      FOREIGN KEY (appointment_type_id)
    REFERENCES appointment_types(id),
  CONSTRAINT chk_appt_times    CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_appt_provider_times ON appointments (provider_id, start_time, end_time);
CREATE INDEX idx_appt_status         ON appointments (status);

-- ── 5. staff_users ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS staff_users (
  id          CHAR(36)     NOT NULL,
  username    VARCHAR(80)  NOT NULL,
  name        VARCHAR(120) NOT NULL,
  password    VARCHAR(255) NOT NULL  COMMENT 'password_hash() — never plaintext',
  provider_id CHAR(36)     NULL      COMMENT 'linked doctor; null = receptionist/admin',
  role        ENUM('admin','receptionist','doctor') NOT NULL DEFAULT 'receptionist',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_staff_users    PRIMARY KEY (id),
  CONSTRAINT uq_staff_username UNIQUE (username),
  CONSTRAINT fk_staff_provider FOREIGN KEY (provider_id)
    REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
