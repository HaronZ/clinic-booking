# Clinic Appointment Booking System

> Vanilla PHP 8.2 · MySQL 8 · Angular 17 · Deployed on Railway · MIT License

A production-ready clinic appointment booking system with a patient booking wizard, staff dashboard, JWT authentication, and email notifications.

[![Live Demo](https://img.shields.io/badge/Live%20Demo-railway.app-blueviolet)](https://clinic-booking-production.up.railway.app)
[![PHP 8.2](https://img.shields.io/badge/PHP-8.2-blue)](https://www.php.net/)
[![Angular 17](https://img.shields.io/badge/Angular-17-red)](https://angular.io/)
[![Tests](https://img.shields.io/badge/tests-21%20passing-brightgreen)]()
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

---

## 🚀 Live Demo

**[https://clinic-booking-production.up.railway.app](https://clinic-booking-production.up.railway.app)**

| Role | Username | Password |
|------|----------|----------|
| Patient | *(no login needed)* | — just open the link |
| Doctor | `ana.reyes` | `doctor123` |
| Receptionist | `reception` | `reception123` |
| Admin | `admin` | `admin123` |

> Staff login is at the bottom of the booking page.

---

## Features

| Feature | Status |
|---|---|
| Patient booking wizard (5-step) | ✅ |
| Double-booking prevention (`SELECT … FOR UPDATE`) | ✅ |
| Real-time slot availability | ✅ |
| Booking confirmation page | ✅ |
| Patient cancellation | ✅ |
| Staff login (JWT, 12h TTL) | ✅ |
| Staff schedule dashboard | ✅ |
| Confirm / Complete / Cancel from dashboard | ✅ |
| Email confirmations (PHPMailer, SMTP) | ✅ |
| Mobile-responsive CSS | ✅ |
| 21 PHPUnit tests | ✅ |

---

## Project layout

```
backend/
  db/
    migrations/       001–005 SQL migrations
    seed.sql          Provider + appointment type data
    seed_staff.sql    Dev staff accounts (4 users)
  src/
    Database/         PDO singleton
    Exception/        ValidationException, ConflictException, InvalidTransitionException
    Http/             Router, Request, Response
    Repository/       AppointmentRepository, AvailabilityRepository, ProviderRepository, StaffRepository
    Service/          AuthService (JWT), AvailabilityService, BookingService, EmailService
  public/index.php    Front controller (all routes)
  tests/              PHPUnit — BookingServiceTest, AvailabilityServiceTest
  .env                Local config (never commit with real secrets)

frontend/
  src/app/
    booking/          5-step patient booking wizard
    confirmation/     Booking confirmation + cancel button
    staff-login/      Staff authentication form
    staff-dashboard/  Schedule table with action buttons
    models/           TypeScript interfaces
    services/         ApiService, AuthService

SPEC.md               Clinic configuration (providers, types, schedules)
CLAUDE.md             Architecture rules — read before modifying
```

---

## Prerequisites

- **PHP 8.2+** with extensions: `pdo_mysql`, `mbstring`, `openssl`
- **Composer 2**
- **MySQL 8.0+**
- **Node 20+ / npm**

---

## Quick start (3 steps)

### 1 — Clone and install

```bash
git clone https://github.com/HaronZ/clinic-booking.git
cd clinic-booking

# Install PHP dependencies
cd backend && composer install && cd ..

# Install Angular dependencies
cd frontend && npm install && cd ..
```

### 2 — Configure environment

```bash
cd backend
cp .env.example .env
```

Edit `.env` with your local MySQL credentials:

```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=clinic_booking
DB_USER=root          # your MySQL username
DB_PASS=              # your MySQL password
JWT_SECRET=any-random-string-here
```

### 3 — Create database + run migrations

```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS clinic_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Then choose **one** of the following:

**Option A — Real clinic (empty, your own data)**
```bash
php backend/scripts/migrate.php
```
Creates all tables with no data. Add your own doctors, appointment types,
and staff directly in MySQL.

**Option B — Try the demo / portfolio (pre-filled sample data)**
```bash
php backend/scripts/migrate.php --demo
```
Creates tables AND loads sample data identical to the live demo:
- 2 doctors (Dr. Ana Reyes, Dr. Luis Mendoza)
- 3 appointment types (General Consultation, Follow-up Visit, Annual Physical)
- Doctor schedules for the week
- 4 staff accounts (see login table below)

---

## Running locally

Open **two terminals**:

```bash
# Terminal 1 — PHP API (port 8080)
cd backend
php -S localhost:8080 -t public

# Terminal 2 — Angular dev server (port 4200)
cd frontend
npm start
```

Open **http://localhost:4200**

---

## Default dev accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | Admin |
| `reception` | `reception123` | Receptionist |
| `ana.reyes` | `doctor123` | Doctor (Dr. Ana Reyes) |
| `luis.mendoza` | `doctor123` | Doctor (Dr. Luis Mendoza) |

Click **"Staff login"** at the bottom of the booking page to access the staff portal.

---

## Running tests

```bash
cd backend
./vendor/bin/phpunit --testdox
```

Expected output: **21 tests, 54 assertions** — all green.

---

## Email configuration

By default, `MAIL_ENABLED=0` in `.env` — emails are logged instead of sent (check `php -S` console output). To enable real email:

```ini
MAIL_ENABLED=1
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=your-account@gmail.com
MAIL_PASS=your-16-char-app-password   # Google Account → App passwords
MAIL_FROM=noreply@yourclinic.com
MAIL_FROM_NAME="Clinic Booking"
```

---

## API reference

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/api/auth/login` | — | Login, returns JWT |
| `GET` | `/api/providers` | — | List active providers |
| `GET` | `/api/appointment-types` | — | List active types |
| `GET` | `/api/availability` | — | Available slots for date |
| `POST` | `/api/bookings` | — | Create booking |
| `GET` | `/api/bookings/{id}` | — | Get booking (no PII) |
| `PATCH` | `/api/bookings/{id}/status` | JWT | Update status |
| `GET` | `/api/staff/schedule` | JWT | Staff schedule |

All responses: `{ "data": ..., "meta": {} }` or `{ "error": { "code": "SCREAMING_SNAKE", "message": "..." }, "meta": {} }`

---

## Architecture decisions

| Decision | Rationale |
|---|---|
| UUID PKs | No enumeration attacks, safe for external APIs |
| `SELECT … FOR UPDATE` | Only correct double-booking guard under concurrent writes in MySQL |
| Server-derived `end_time` | Client can't sneak in arbitrary durations |
| Explicit status allow-list | Prevents invalid transitions (e.g. `completed → pending`) |
| No ORM | 800 lines of PHP total — an ORM would triple it for no benefit |
| PII never logged | patient_name/email only in DB; never in logs, never in non-staff API responses |

See `CLAUDE.md` for the full rule set.

---

## Production checklist

- [ ] Change `JWT_SECRET` to a 64-char random string
- [ ] Set `APP_ENV=production`
- [ ] Enable HTTPS (nginx/Caddy), set HSTS
- [ ] Set `MAIL_ENABLED=1` and configure real SMTP credentials
- [ ] Restrict MySQL user: `GRANT SELECT, INSERT, UPDATE ON clinic_booking.* TO ...`
- [ ] Remove `seed_staff.sql` default accounts; create real user accounts
- [ ] Set PHP `display_errors=Off`, `log_errors=On`
- [ ] Add rate limiting on `/api/bookings` (nginx `limit_req`)

---

## License

[MIT](LICENSE) — free to use, modify, and distribute.
