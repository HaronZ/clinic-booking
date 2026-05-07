-- seed_demo.sql — sample data for development, testing, and portfolio demos.
-- Run with: php backend/scripts/migrate.php --demo
--
-- A real clinic should NOT run this file.
-- Instead, add your own doctors, schedules, and appointment types
-- directly in MySQL or through an admin interface.
--
-- All rows use INSERT IGNORE so re-running is safe.

SET NAMES utf8mb4;

-- ── Demo providers ────────────────────────────────────────────────────────────
INSERT IGNORE INTO providers (id, name, specialty, slug, is_active) VALUES
  ('11111111-1111-1111-1111-111111111111', 'Dr. Ana Reyes',    'General Medicine',  'dr-ana-reyes',    1),
  ('22222222-2222-2222-2222-222222222222', 'Dr. Luis Mendoza', 'Internal Medicine', 'dr-luis-mendoza', 1);

-- ── Demo appointment types ────────────────────────────────────────────────────
INSERT IGNORE INTO appointment_types (id, name, slug, duration_minutes, is_active) VALUES
  ('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'General Consultation', 'general-consultation', 30, 1),
  ('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'Follow-up Visit',      'follow-up-visit',      15, 1),
  ('cccccccc-cccc-cccc-cccc-cccccccccccc', 'Annual Physical',      'annual-physical',      60, 1);

-- ── Demo provider schedules ───────────────────────────────────────────────────
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

-- ── Demo staff accounts ───────────────────────────────────────────────────────
-- Passwords (bcrypt cost 12):
--   admin        → admin123
--   reception    → reception123
--   ana.reyes    → doctor123
--   luis.mendoza → doctor123
--
-- IMPORTANT: Change all passwords before using in a real clinic.
-- The admin row sets must_change_password = 1 so the very first login forces
-- a password change, ensuring the public default never lingers in production.
INSERT IGNORE INTO staff_users (id, username, name, password, provider_id, role, must_change_password) VALUES
  ('10000000-0000-0000-0000-000000000001', 'admin',        'Admin User',       '$2y$12$x8/M.LSoSOONSE88rDVe9.OGRVqyGkIYCxy1nAuxi.TZ8V9Pc6XD6', NULL,                                   'admin',        1),
  ('10000000-0000-0000-0000-000000000002', 'reception',    'Front Desk',       '$2y$12$HjAuDdopQ2wGuiC1eTHGKOIdSmV3pzScT3TTTuNimycBqIDVz8vGa', NULL,                                   'receptionist', 0),
  ('10000000-0000-0000-0000-000000000003', 'ana.reyes',    'Dr. Ana Reyes',    '$2y$12$1SHGnkONSDfSGUIiUt/3muOcOMr02lND./nKUNll4xV4QDk.7Vy8.', '11111111-1111-1111-1111-111111111111', 'doctor',       0),
  ('10000000-0000-0000-0000-000000000004', 'luis.mendoza', 'Dr. Luis Mendoza', '$2y$12$1SHGnkONSDfSGUIiUt/3muOcOMr02lND./nKUNll4xV4QDk.7Vy8.', '22222222-2222-2222-2222-222222222222', 'doctor',       0);
