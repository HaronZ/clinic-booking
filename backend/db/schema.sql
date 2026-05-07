-- schema.sql — full database setup in one file (safe to re-run).
-- Used by: backend/scripts/migrate.php (Railway first-deploy)
-- All tables use CREATE TABLE IF NOT EXISTS.
-- All seed rows use INSERT IGNORE (duplicate keys are silently skipped).

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

-- ── Seed: providers ───────────────────────────────────────────────────────────
INSERT IGNORE INTO providers (id, name, specialty, slug, is_active) VALUES
  ('11111111-1111-1111-1111-111111111111', 'Dr. Ana Reyes',    'General Medicine',  'dr-ana-reyes',    1),
  ('22222222-2222-2222-2222-222222222222', 'Dr. Luis Mendoza', 'Internal Medicine', 'dr-luis-mendoza', 1);

-- ── Seed: appointment types ───────────────────────────────────────────────────
INSERT IGNORE INTO appointment_types (id, name, slug, duration_minutes, is_active) VALUES
  ('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'General Consultation', 'general-consultation', 30, 1),
  ('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'Follow-up Visit',      'follow-up-visit',      15, 1),
  ('cccccccc-cccc-cccc-cccc-cccccccccccc', 'Annual Physical',      'annual-physical',      60, 1);

-- ── Seed: provider schedules ──────────────────────────────────────────────────
-- Dr. Ana Reyes — Mon–Thu 09:00–17:00, Fri 09:00–12:00
INSERT IGNORE INTO provider_schedules (id, provider_id, day_of_week, start_time, end_time) VALUES
  ('a1000001-0000-0000-0000-000000000001', '11111111-1111-1111-1111-111111111111', 1, '09:00:00', '17:00:00'),
  ('a1000001-0000-0000-0000-000000000002', '11111111-1111-1111-1111-111111111111', 2, '09:00:00', '17:00:00'),
  ('a1000001-0000-0000-0000-000000000003', '11111111-1111-1111-1111-111111111111', 3, '09:00:00', '17:00:00'),
  ('a1000001-0000-0000-0000-000000000004', '11111111-1111-1111-1111-111111111111', 4, '09:00:00', '17:00:00'),
  ('a1000001-0000-0000-0000-000000000005', '11111111-1111-1111-1111-111111111111', 5, '09:00:00', '12:00:00');

-- Dr. Luis Mendoza — Mon/Wed/Fri 13:00–18:00, Sat 09:00–12:00
INSERT IGNORE INTO provider_schedules (id, provider_id, day_of_week, start_time, end_time) VALUES
  ('a2000002-0000-0000-0000-000000000001', '22222222-2222-2222-2222-222222222222', 1, '13:00:00', '18:00:00'),
  ('a2000002-0000-0000-0000-000000000002', '22222222-2222-2222-2222-222222222222', 3, '13:00:00', '18:00:00'),
  ('a2000002-0000-0000-0000-000000000003', '22222222-2222-2222-2222-222222222222', 5, '13:00:00', '18:00:00'),
  ('a2000002-0000-0000-0000-000000000004', '22222222-2222-2222-2222-222222222222', 6, '09:00:00', '12:00:00');

-- ── Seed: staff users ─────────────────────────────────────────────────────────
-- Passwords (bcrypt cost 12):
--   admin       → admin123
--   ana.reyes   → doctor123
--   luis.mendoza→ doctor123
--   reception   → reception123
INSERT IGNORE INTO staff_users (id, username, name, password, provider_id, role) VALUES
  ('10000000-0000-0000-0000-000000000001', 'admin',       'Admin User',       '$2y$12$x8/M.LSoSOONSE88rDVe9.OGRVqyGkIYCxy1nAuxi.TZ8V9Pc6XD6', NULL,                                   'admin'),
  ('10000000-0000-0000-0000-000000000002', 'reception',   'Front Desk',       '$2y$12$HjAuDdopQ2wGuiC1eTHGKOIdSmV3pzScT3TTTuNimycBqIDVz8vGa', NULL,                                   'receptionist'),
  ('10000000-0000-0000-0000-000000000003', 'ana.reyes',   'Dr. Ana Reyes',    '$2y$12$1SHGnkONSDfSGUIiUt/3muOcOMr02lND./nKUNll4xV4QDk.7Vy8.', '11111111-1111-1111-1111-111111111111', 'doctor'),
  ('10000000-0000-0000-0000-000000000004', 'luis.mendoza','Dr. Luis Mendoza', '$2y$12$1SHGnkONSDfSGUIiUt/3muOcOMr02lND./nKUNll4xV4QDk.7Vy8.', '22222222-2222-2222-2222-222222222222', 'doctor');
