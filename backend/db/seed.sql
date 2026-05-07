-- seed.sql — minimal data so the Angular form has something to render.
-- Run after the four migrations:
--   mysql -u root -p clinic_booking < db/seed.sql

INSERT INTO providers (id, name, specialty, slug, is_active) VALUES
  ('11111111-1111-1111-1111-111111111111', 'Dr. Ana Reyes',    'General Medicine',  'dr-ana-reyes',    1),
  ('22222222-2222-2222-2222-222222222222', 'Dr. Luis Mendoza', 'Internal Medicine', 'dr-luis-mendoza', 1);

INSERT INTO appointment_types (id, name, slug, duration_minutes, is_active) VALUES
  ('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'General Consultation', 'general-consultation', 30, 1),
  ('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'Follow-up Visit',      'follow-up-visit',      15, 1),
  ('cccccccc-cccc-cccc-cccc-cccccccccccc', 'Annual Physical',      'annual-physical',      60, 1);

-- Dr. Ana Reyes — Mon–Thu 09:00–17:00, Fri 09:00–12:00
INSERT INTO provider_schedules (id, provider_id, day_of_week, start_time, end_time) VALUES
  (UUID(), '11111111-1111-1111-1111-111111111111', 1, '09:00:00', '17:00:00'),
  (UUID(), '11111111-1111-1111-1111-111111111111', 2, '09:00:00', '17:00:00'),
  (UUID(), '11111111-1111-1111-1111-111111111111', 3, '09:00:00', '17:00:00'),
  (UUID(), '11111111-1111-1111-1111-111111111111', 4, '09:00:00', '17:00:00'),
  (UUID(), '11111111-1111-1111-1111-111111111111', 5, '09:00:00', '12:00:00');

-- Dr. Luis Mendoza — Mon/Wed/Fri 13:00–18:00, Sat 09:00–12:00
INSERT INTO provider_schedules (id, provider_id, day_of_week, start_time, end_time) VALUES
  (UUID(), '22222222-2222-2222-2222-222222222222', 1, '13:00:00', '18:00:00'),
  (UUID(), '22222222-2222-2222-2222-222222222222', 3, '13:00:00', '18:00:00'),
  (UUID(), '22222222-2222-2222-2222-222222222222', 5, '13:00:00', '18:00:00'),
  (UUID(), '22222222-2222-2222-2222-222222222222', 6, '09:00:00', '12:00:00');
