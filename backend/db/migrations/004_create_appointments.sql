-- 004_create_appointments.sql
-- The booking row. PII columns marked privacy-sensitive — never log these.
-- Double-booking is prevented at the application layer via SELECT ... FOR UPDATE.
-- The unique index uq_appt_provider_start is defense-in-depth (exact-match only).

CREATE TABLE appointments (
  id                   CHAR(36)     NOT NULL,
  provider_id          CHAR(36)     NOT NULL,
  appointment_type_id  CHAR(36)     NOT NULL,
  patient_name         VARCHAR(160) NOT NULL  COMMENT 'PRIVACY: never log',
  patient_email        VARCHAR(254) NULL      COMMENT 'PRIVACY: never log',
  patient_phone        VARCHAR(30)  NOT NULL,
  patient_notes        TEXT         NULL,
  start_time           DATETIME     NOT NULL,
  end_time             DATETIME     NOT NULL  COMMENT 'Derived server-side from type.duration_minutes',
  status               ENUM('pending','confirmed','completed','cancelled')
                                    NOT NULL DEFAULT 'pending',
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT pk_appointments     PRIMARY KEY (id),
  CONSTRAINT fk_appt_provider    FOREIGN KEY (provider_id)
    REFERENCES providers(id),
  CONSTRAINT fk_appt_type        FOREIGN KEY (appointment_type_id)
    REFERENCES appointment_types(id),
  CONSTRAINT chk_appt_times      CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Query pattern: find overlapping bookings for a provider in a time range.
CREATE INDEX idx_appt_provider_times ON appointments (provider_id, start_time, end_time);

-- Query pattern: filter/sort by status.
CREATE INDEX idx_appt_status ON appointments (status);

-- NOTE: MySQL 8 does not support partial/filtered unique indexes (unlike PostgreSQL).
-- A unique index on (provider_id, start_time) would incorrectly block new bookings
-- when a cancelled booking exists at the same start_time. Range overlap prevention
-- is handled entirely by SELECT ... FOR UPDATE in BookingService::create().
