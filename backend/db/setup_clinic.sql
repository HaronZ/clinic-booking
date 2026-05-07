-- ─────────────────────────────────────────────────────────────────────────────
-- setup_clinic.sql  —  Fill in YOUR clinic's data, then run this file.
--
-- HOW TO USE:
--   1. Edit every value marked with  <-- CHANGE THIS
--   2. Run:  mysql -u root -p clinic_booking < backend/db/setup_clinic.sql
--
-- AFTER running this file, staff can log in at the bottom of the booking page.
-- ─────────────────────────────────────────────────────────────────────────────

SET NAMES utf8mb4;

-- ── STEP 1: Add your doctors ──────────────────────────────────────────────────
-- Copy and paste this block once for EACH doctor in your clinic.
-- UUID() generates a unique ID automatically — do not change that part.

INSERT INTO providers (id, name, specialty, slug, is_active) VALUES
  (UUID(), 'Dr. Juan Santos',   'General Medicine',  'dr-juan-santos',   1),  -- <-- CHANGE THIS
  (UUID(), 'Dr. Maria Cruz',    'Pediatrics',         'dr-maria-cruz',    1),  -- <-- CHANGE THIS
  (UUID(), 'Dr. Roberto Lim',   'Internal Medicine',  'dr-roberto-lim',   1);  -- <-- CHANGE THIS (add/remove rows as needed)

-- slug rules: lowercase, words separated by hyphens, must be unique
-- Examples: 'dr-juan-santos'  'dr-maria-cruz'  'dr-roberto-lim'


-- ── STEP 2: Add your appointment types ───────────────────────────────────────
-- duration_minutes = how long each appointment lasts

INSERT INTO appointment_types (id, name, slug, duration_minutes, is_active) VALUES
  (UUID(), 'General Consultation', 'general-consultation', 30, 1),  -- <-- CHANGE THIS
  (UUID(), 'Follow-up Visit',      'follow-up-visit',      15, 1),  -- <-- CHANGE THIS
  (UUID(), 'Annual Physical',      'annual-physical',      60, 1);  -- <-- CHANGE THIS (add/remove rows as needed)


-- ── STEP 3: Set each doctor's working schedule ────────────────────────────────
-- day_of_week: 0=Sunday  1=Monday  2=Tuesday  3=Wednesday  4=Thursday  5=Friday  6=Saturday
--
-- You must use the EXACT id from your providers table.
-- Run this first to see your doctor IDs:
--   SELECT id, name FROM providers;
-- Then replace 'PASTE-DOCTOR-ID-HERE' with the actual UUID.

INSERT INTO provider_schedules (id, provider_id, day_of_week, start_time, end_time) VALUES
  -- Dr. Juan Santos — Monday to Friday, 8am–5pm  (<-- CHANGE name in comment)
  (UUID(), 'PASTE-DOCTOR-ID-HERE', 1, '08:00:00', '17:00:00'),  -- <-- CHANGE ID and times
  (UUID(), 'PASTE-DOCTOR-ID-HERE', 2, '08:00:00', '17:00:00'),  -- <-- CHANGE ID and times
  (UUID(), 'PASTE-DOCTOR-ID-HERE', 3, '08:00:00', '17:00:00'),  -- <-- CHANGE ID and times
  (UUID(), 'PASTE-DOCTOR-ID-HERE', 4, '08:00:00', '17:00:00'),  -- <-- CHANGE ID and times
  (UUID(), 'PASTE-DOCTOR-ID-HERE', 5, '08:00:00', '17:00:00'),  -- <-- CHANGE ID and times

  -- Dr. Maria Cruz — Tuesday and Thursday only, 9am–3pm  (<-- CHANGE name in comment)
  (UUID(), 'PASTE-DOCTOR-ID-HERE', 2, '09:00:00', '15:00:00'),  -- <-- CHANGE ID and times
  (UUID(), 'PASTE-DOCTOR-ID-HERE', 4, '09:00:00', '15:00:00');  -- <-- CHANGE ID and times


-- ── STEP 4: Create staff accounts ─────────────────────────────────────────────
-- Passwords below are already hashed with bcrypt.
-- DEFAULT PASSWORDS — change them after first login (or generate new hashes).
--
-- To generate a new password hash, run:
--   php -r "echo password_hash('your-password-here', PASSWORD_BCRYPT, ['cost'=>12]);"
--
-- provider_id: link a doctor account to their provider row (use the doctor's UUID from Step 3)
--              set to NULL for admin and receptionist accounts
--
-- role options: 'admin'  |  'receptionist'  |  'doctor'

INSERT INTO staff_users (id, username, name, password, provider_id, role) VALUES
  -- Admin account (NULL provider_id = not linked to any doctor)
  (UUID(), 'admin',       'Admin User',      '$2y$12$x8/M.LSoSOONSE88rDVe9.OGRVqyGkIYCxy1nAuxi.TZ8V9Pc6XD6', NULL,                    'admin'),
  -- default password: admin123 — CHANGE THIS

  -- Receptionist account
  (UUID(), 'reception',   'Front Desk',      '$2y$12$HjAuDdopQ2wGuiC1eTHGKOIdSmV3pzScT3TTTuNimycBqIDVz8vGa', NULL,                    'receptionist'),
  -- default password: reception123 — CHANGE THIS

  -- Doctor accounts — paste each doctor's UUID from Step 3 in provider_id
  (UUID(), 'juan.santos',  'Dr. Juan Santos', '$2y$12$1SHGnkONSDfSGUIiUt/3muOcOMr02lND./nKUNll4xV4QDk.7Vy8.', 'PASTE-DOCTOR-ID-HERE', 'doctor'),
  -- default password: doctor123 — CHANGE THIS

  (UUID(), 'maria.cruz',   'Dr. Maria Cruz',  '$2y$12$1SHGnkONSDfSGUIiUt/3muOcOMr02lND./nKUNll4xV4QDk.7Vy8.', 'PASTE-DOCTOR-ID-HERE', 'doctor');
  -- default password: doctor123 — CHANGE THIS


-- ─────────────────────────────────────────────────────────────────────────────
-- Done! Visit your app, click "Staff login" at the bottom, and sign in.
-- ─────────────────────────────────────────────────────────────────────────────
