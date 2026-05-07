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
  private readonly api  = inject(ApiService);
  private readonly auth = inject(AuthService);

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

  cancelBooking(): void {
    if (!this.canCancel || this.cancelling()) return;
    if (!confirm('Cancel this appointment? This cannot be undone.')) return;

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
