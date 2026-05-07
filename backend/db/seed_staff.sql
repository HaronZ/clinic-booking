-- seed_staff.sql — dev staff accounts.
-- Passwords (all bcrypt, cost 12):
--   admin       → admin123
--   ana.reyes   → doctor123
--   luis.mendoza→ doctor123
--   reception   → reception123
--
-- Run: mysql -u haronzie -p clinic_booking < db/seed_staff.sql

INSERT INTO staff_users (id, username, name, password, provider_id, role) VALUES
  (
    '10000000-0000-0000-0000-000000000001',
    'admin',
    'Admin User',
    '$2y$12$x8/M.LSoSOONSE88rDVe9.OGRVqyGkIYCxy1nAuxi.TZ8V9Pc6XD6',
    NULL,
    'admin'
  ),
  (
    '10000000-0000-0000-0000-000000000002',
    'reception',
    'Front Desk',
    '$2y$12$HjAuDdopQ2wGuiC1eTHGKOIdSmV3pzScT3TTTuNimycBqIDVz8vGa',
    NULL,
    'receptionist'
  ),
  (
    '10000000-0000-0000-0000-000000000003',
    'ana.reyes',
    'Dr. Ana Reyes',
    '$2y$12$1SHGnkONSDfSGUIiUt/3muOcOMr02lND./nKUNll4xV4QDk.7Vy8.',
    '11111111-1111-1111-1111-111111111111',
    'doctor'
  ),
  (
    '10000000-0000-0000-0000-000000000004',
    'luis.mendoza',
    'Dr. Luis Mendoza',
    '$2y$12$1SHGnkONSDfSGUIiUt/3muOcOMr02lND./nKUNll4xV4QDk.7Vy8.',
    '22222222-2222-2222-2222-222222222222',
    'doctor'
  );
