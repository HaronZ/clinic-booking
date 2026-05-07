# Clinic Booking System — SPEC

> Generated as a sensible default for the MVP. Edit this file to match your clinic. Re-run Phase 1 if you change `appointment_types`/`providers`/`provider_schedules` shape.

## 1. Appointment Types

| Name                  | Slug                  | Duration (minutes) |
| --------------------- | --------------------- | ------------------ |
| General Consultation  | general-consultation  | 30                 |
| Follow-up Visit       | follow-up-visit       | 15                 |
| Annual Physical       | annual-physical       | 60                 |

## 2. Providers

| Slug             | Name              | Specialty         |
| ---------------- | ----------------- | ----------------- |
| dr-ana-reyes     | Dr. Ana Reyes     | General Medicine  |
| dr-luis-mendoza  | Dr. Luis Mendoza  | Internal Medicine |

## 3. Provider Schedules

### Dr. Ana Reyes
| Day       | Start | End   |
| --------- | ----- | ----- |
| Monday    | 09:00 | 17:00 |
| Tuesday   | 09:00 | 17:00 |
| Wednesday | 09:00 | 17:00 |
| Thursday  | 09:00 | 17:00 |
| Friday    | 09:00 | 12:00 |

### Dr. Luis Mendoza
| Day       | Start | End   |
| --------- | ----- | ----- |
| Monday    | 13:00 | 18:00 |
| Wednesday | 13:00 | 18:00 |
| Friday    | 13:00 | 18:00 |
| Saturday  | 09:00 | 12:00 |

## 4. Booking Rules

- **Slot interval:** equal to `appointment_type.duration_minutes` (no fractional slots in MVP).
- **Same-day booking:** allowed.
- **Minimum advance notice:** none enforced in MVP (slots in the past are filtered client-side via `<input type="date" min="today">`).
- **Timezone:** Asia/Manila — clinic-local. All `DATETIME` values stored as clinic-local; no UTC conversion.
- **Cancellation:** allowed any time before `start_time`; status moves to `cancelled`.

## 5. Patient Fields

| Field            | Required | Notes                             |
| ---------------- | -------- | --------------------------------- |
| `patient_name`   | Yes      | First and last together           |
| `patient_phone`  | Yes      | E.164 or local format accepted    |
| `patient_email`  | No       | Validated as email if present     |
| `patient_notes`  | No       | Free text, max 1000 chars         |

## 6. Out of Scope (MVP)

- Authentication
- Email / SMS notifications
- Admin / provider dashboards
- Payments
- Recurring appointments
- Multi-timezone support
