# Screenshots

Drop PNG screenshots here when you have time. The README is set up to render the architecture diagram already, so screenshots are pure polish — but a recruiter scrolling on their phone is more likely to remember a picture than a Mermaid diagram.

## What to capture (4 shots)

### `booking-wizard.png`
Live demo → public booking page → step 3 (date/slot picker, after picking a provider).
- Shows the visual stepper at the top and a populated slot grid.
- Why this one: proves the patient-facing wizard works and looks polished.

### `admin-schedules.png`
Live demo → log in as admin → Admin Panel → **Schedules** tab → pick a doctor.
- Shows the 7-day grid with checkboxes + time inputs.
- Why this one: the schedule editor is the most visually distinctive admin feature.

### `staff-dashboard.png`
Live demo → log in as `ana.reyes` / `<your demo password>` → Staff Dashboard.
- Shows today's schedule with at least one Confirm/Complete/Cancel action button.
- If the dashboard is empty, book a fake appointment first via the public booking flow.

### `change-password.png`
Reset the admin's `must_change_password` flag in the DB (or wipe and re-seed), then log in as admin → forced password change screen.
- Shows the yellow "Required" notice and the three password fields.
- Why this one: the forced-password-change UX is one of the project's strongest security signals; an interviewer who sees this immediately understands you think about real-world ops.

## Dimensions

- 1440 × 900 logical (so retina caps it at 2880 × 1800) is the sweet spot — sharp on GitHub, not absurd in size.
- Crop the browser chrome out (Cmd-Shift-4 on macOS, Snipping Tool on Windows). The README cards are narrow, so a screenshot wider than ~1400 px just gets scaled down.

## After dropping the files in

Add the table back to the project's main `README.md`, right after the live demo section:

```markdown
## Screenshots

| Patient booking wizard | Admin panel — schedule editor |
|---|---|
| ![Booking wizard](docs/screenshots/booking-wizard.png) | ![Schedule editor](docs/screenshots/admin-schedules.png) |

| Staff dashboard | Forced first-login password change |
|---|---|
| ![Staff dashboard](docs/screenshots/staff-dashboard.png) | ![Change password](docs/screenshots/change-password.png) |
```
