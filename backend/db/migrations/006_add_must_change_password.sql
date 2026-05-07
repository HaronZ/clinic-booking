-- 006_add_must_change_password.sql
--
-- Adds a flag that lets the app force a first-login password change,
-- so the default admin/admin123 credentials shipped in seed_demo.sql
-- can never survive into production unchanged.
--
-- For fresh installs, schema.sql already contains the column — this
-- migration is for upgrading an existing database that was created
-- before the column existed.

-- Plain ADD COLUMN (no IF NOT EXISTS): not all MySQL 8.0 distributions
-- support that syntax even though the docs say >= 8.0.29 does. migrate.php
-- gracefully ignores error 1060 ("duplicate column name") so a re-run on a
-- DB that already has this column is safe.
ALTER TABLE staff_users
  ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0
  AFTER is_active;
