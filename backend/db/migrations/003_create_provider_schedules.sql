-- 003_create_provider_schedules.sql
-- Weekly recurring schedule. Source of truth for provider availability.
-- One row per (provider_id, day_of_week). day_of_week: 0=Sun ... 6=Sat (matches PHP date('w')).

CREATE TABLE provider_schedules (
  id           CHAR(36)  NOT NULL,
  provider_id  CHAR(36)  NOT NULL,
  day_of_week  TINYINT   NOT NULL COMMENT '0=Sunday 1=Monday ... 6=Saturday',
  start_time   TIME      NOT NULL COMMENT 'Clinic-local time, e.g. 09:00:00',
  end_time     TIME      NOT NULL COMMENT 'Clinic-local time, e.g. 17:00:00',
  CONSTRAINT pk_provider_schedules    PRIMARY KEY (id),
  CONSTRAINT fk_ps_provider           FOREIGN KEY (provider_id)
    REFERENCES providers(id) ON DELETE CASCADE,
  CONSTRAINT uq_ps_provider_day       UNIQUE (provider_id, day_of_week),
  CONSTRAINT chk_ps_times             CHECK (end_time > start_time),
  CONSTRAINT chk_ps_day               CHECK (day_of_week BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_ps_provider_day ON provider_schedules (provider_id, day_of_week);
