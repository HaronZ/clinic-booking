-- 005_create_staff_users.sql
-- Staff accounts (receptionists, doctors, admin).
-- Passwords stored as password_hash() with PASSWORD_DEFAULT.

CREATE TABLE staff_users (
  id          CHAR(36)     NOT NULL,
  username    VARCHAR(80)  NOT NULL,
  name        VARCHAR(120) NOT NULL,
  password    VARCHAR(255) NOT NULL  COMMENT 'password_hash() — never plaintext',
  provider_id CHAR(36)     NULL      COMMENT 'linked doctor account; null = receptionist/admin',
  role        ENUM('admin','receptionist','doctor') NOT NULL DEFAULT 'receptionist',
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT pk_staff_users      PRIMARY KEY (id),
  CONSTRAINT uq_staff_username   UNIQUE (username),
  CONSTRAINT fk_staff_provider   FOREIGN KEY (provider_id)
    REFERENCES providers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
