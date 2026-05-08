import {
  ChangeDetectionStrategy,
  Component,
  Input,
  OnInit,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';

import { ApiService, ApiError } from '../services/api.service';
import { AuthService } from '../services/auth.service';
import { ConfirmService } from '../services/confirm.service';
import { BookingConfirmation, BookingStatus } from '../models/booking.model';

@Component({
  selector: 'app-confirmation',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './confirmation.component.html',
  styleUrls: ['./confirmation.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConfirmationComponent implements OnInit {
  private readonly api     = inject(ApiService);
  private readonly auth    = inject(AuthService);
  private readonly confirm = inject(ConfirmService);

  @Input({ required: true }) bookingId!: string;

  readonly booking     = signal<BookingConfirmation | null>(null);
  readonly loading     = signal(true);
  readonly error       = signal<string | null>(null);
  readonly cancelling  = signal(false);
  readonly cancelError = signal<string | null>(null);

  ngOnInit(): void {
    this.api.getBooking(this.bookingId).subscribe({
      next: (b) => {
        this.booking.set(b);
        this.loading.set(false);
      },
      error: (err: ApiError) => {
        this.loading.set(false);
        this.error.set(err.status === 404 ? 'Booking not found.' : err.message);
      },
    });
  }

  get canCancel(): boolean {
    const s = this.booking()?.status;
    return s === 'pending' || s === 'confirmed';
  }

  /** Opens the OS print dialog. The print stylesheet hides nav and buttons. */
  print(): void {
    window.print();
  }

  /**
   * Build a minimal RFC 5545 calendar file and trigger a browser download.
   * Times are floating (clinic-local) — no Z suffix, no VTIMEZONE block.
   * That's the right choice for a single-clinic, single-timezone deployment.
   */
  downloadIcs(): void {
    const b = this.booking();
    if (!b) return;

    const fmt = (iso: string): string => iso.replace(/[-:]/g, '').slice(0, 15); // 20260508T140000
    const escape = (s: string): string => s.replace(/[\\;,\n]/g, (c) => (c === '\n' ? '\\n' : '\\' + c));

    const summary = `${b.appointment_type.name} with ${b.provider.name}`;
    const description = [
      `Provider: ${b.provider.name} (${b.provider.specialty})`,
      `Type: ${b.appointment_type.name} (${b.appointment_type.duration_minutes} min)`,
      `Reference: ${b.id}`,
      'Please arrive 10 minutes early.',
    ].join('\n');

    const dtstamp = new Date().toISOString().replace(/[-:]/g, '').slice(0, 15) + 'Z';
    const ics = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//Clinic Booking//EN',
      'CALSCALE:GREGORIAN',
      'METHOD:PUBLISH',
      'BEGIN:VEVENT',
      `UID:${b.id}@clinic-booking`,
      `DTSTAMP:${dtstamp}`,
      `DTSTART:${fmt(b.start_time)}`,
      `DTEND:${fmt(b.end_time)}`,
      `SUMMARY:${escape(summary)}`,
      `DESCRIPTION:${escape(description)}`,
      'END:VEVENT',
      'END:VCALENDAR',
      '',
    ].join('\r\n');

    const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `appointment-${b.id.slice(0, 8)}.ics`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1_000);
  }

  async cancelBooking(): Promise<void> {
    if (!this.canCancel || this.cancelling()) return;
    const ok = await this.confirm.ask(
      'Cancel this appointment? This cannot be undone.',
      { title: 'Cancel appointment?', confirmLabel: 'Yes, cancel', danger: true },
    );
    if (!ok) return;

    this.cancelling.set(true);
    this.cancelError.set(null);

    this.api.updateBookingStatus(this.bookingId, 'cancelled').subscribe({
      next: (res) => {
        this.cancelling.set(false);
        const b = this.booking();
        if (b) this.booking.set({ ...b, status: res.status as BookingStatus });
      },
      error: (err: ApiError) => {
        this.cancelling.set(false);
        if (err.status === 401 || err.status === 422) {
          // Not logged in — cancellation from patient side requires auth
          // Show friendly message
          this.cancelError.set(
            'Cancellation requires staff authorisation. Please call the clinic.',
          );
        } else {
          this.cancelError.set(err.message ?? 'Could not cancel. Please try again.');
        }
      },
    });
  }
}
