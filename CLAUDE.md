# Clinic Booking System — Project Context

## Stack
- Backend: PHP 8.2+ vanilla (no framework), PDO + MySQL 8
- Frontend: Angular 17+ standalone components, Angular CLI
- Tests: PHPUnit 11 (backend), Karma/Jasmine (frontend)
- Autoloader: Composer PSR-4, namespace root `Clinic\`

## Project layout
```
PHP Project/
  backend/
    public/index.php          # front controller — ALL /api/* requests route here
    src/
      Database/Connection.php
      Http/Router.php
      Http/Request.php
      Http/Response.php
      Repository/AppointmentRepository.php
      Repository/ProviderRepository.php
      Repository/AvailabilityRepository.php
      Service/BookingService.php
      Service/AvailabilityService.php
      Exception/ValidationException.php
      Exception/ConflictException.php
      Exception/InvalidTransitionException.php
    db/migrations/
      001_create_providers.sql
      002_create_appointment_types.sql
      003_create_provider_schedules.sql
      004_create_appointments.sql
    tests/
      Unit/BookingServiceTest.php
      Unit/AvailabilityServiceTest.php
    composer.json
    phpunit.xml
    .env
  frontend/                   # output of: ng new frontend --standalone --routing=false --style=css
    proxy.conf.json
    src/app/
      booking/booking.component.ts
      booking/booking.component.html
      confirmation/confirmation.component.ts
      services/api.service.ts
      models/
```

## Dev commands
```powershell
# Backend dev server
php -S localhost:8080 -t backend/public

# PHPUnit
cd backend; .\vendor\bin\phpunit --testdox

# Angular dev server (proxies /api to localhost:8080)
cd frontend; ng serve --proxy-config proxy.conf.json
```

## Non-negotiable rules
- All times stored in clinic-local time as `DATETIME` (single-clinic MVP, no timezone juggling).
- SQL: prepared statements ONLY. No string interpolation, ever.
- Double-booking prevented via `SELECT ... FOR UPDATE` inside an explicit transaction. App-level checks alone do NOT count.
- No framework, no Eloquent. Vanilla PDO.
- All money/sensitive ops handled server-side. Never trust the client.

## CLINIC-SPECIFIC RULES — never violate

### Patient privacy
- NEVER log `patient_name` or `patient_email` in ANY application log, `error_log()` call, or debug output.
- These fields may appear ONLY in: the database row itself, and the HTTP response body sent directly to the requesting client.
- In all log statements, use `patient_id` (the opaque UUID) only.
- Tests that need to assert on patient data may inspect the DB row directly — not via log output.

### Appointment type / duration invariant
- `appointment_types.duration_minutes` is the canonical slot length.
- `end_time` MUST always be derived server-side: `end_time = start_time + INTERVAL duration_minutes MINUTE`
- The client MUST NOT send `end_time` or `duration_minutes`. If either is present in the request body, ignore it silently.
- Tests MUST assert: if a client sends `"duration_minutes": 999`, the stored `end_time` still matches canonical type duration.

### Provider schedule is source of truth for availability
- To determine whether a provider is working on a given day/time: query `provider_schedules` for `day_of_week` and compare `start_time`/`end_time`.
- Do NOT infer availability by absence of rows in `appointments`.
- The `appointments` table is queried ONLY to find existing bookings that fall within a confirmed working window.

### Status transition machine — EXPLICIT allow-list
Valid transitions ONLY:
- `pending`   → `confirmed`
- `pending`   → `cancelled`
- `confirmed` → `completed`
- `confirmed` → `cancelled`

All other transitions (e.g. `completed` → `pending`, `cancelled` → `confirmed`, `pending` → `completed`) MUST throw `InvalidTransitionException` and return HTTP 422 with code `INVALID_TRANSITION`.
Implement as an explicit allow-list array in `BookingService::transition()`, not a chain of if-statements.

### Double-booking guard
Use `SELECT ... FOR UPDATE` inside a `BEGIN/COMMIT` transaction.
Pattern lives in `BookingService::create()`.
Do NOT rely on application-level mutexes.

## Response envelope (all endpoints)
- Success: `{ "data": <payload>, "meta": {} }`
- Error:   `{ "error": { "code": "SCREAMING_SNAKE", "message": "human readable" }, "meta": {} }`

## Coding conventions
- `declare(strict_types=1)` at top of every PHP file.
- Typed properties on all classes.
- Named arguments for `PDO->execute()` calls: `['param' => $value]` not positional.
- No raw `$_GET` / `$_POST` / `$_REQUEST` — parse exclusively through `Request` class.
- All user-controlled values in SQL go through prepared statements. Zero string interpolation in queries.
- Angular: standalone components only. `HttpClient` via `inject()`. Reactive forms with typed `FormGroup`.

## Workflow
- Migrations in `backend/db/migrations`, numbered `NNN_description.sql`.
- Run `composer test` (or `.\vendor\bin\phpunit`) before committing PHP.
- Run `ng build` and `ng lint` before committing Angular.
- Commits: `type(scope): subject` — e.g. `feat(booking): prevent overlap on insert`.

## Out of scope for MVP (do not implement)
- Authentication / JWT / sessions
- Email or SMS notifications
- Admin panel or provider dashboard
- Payment integration
- Recurring appointments
- Multi-timezone support (store all times in clinic-local time for MVP)
