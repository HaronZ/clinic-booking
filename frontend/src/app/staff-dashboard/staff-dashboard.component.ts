import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  OnInit,
  Output,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';

import { ApiService, ApiError } from '../services/api.service';
import { AuthService } from '../services/auth.service';
import { ScheduleAppointment, ScheduleResponse } from '../models/staff.model';

@Component({
  selector: 'app-staff-dashboard',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './staff-dashboard.component.html',
  styleUrls: ['./staff-dashboard.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StaffDashboardComponent implements OnInit {
  private readonly api  = inject(ApiService);
  private readonly auth = inject(AuthService);

  @Output() loggedOut  = new EventEmitter<void>();
  @Output() goAdmin    = new EventEmitter<void>();

  readonly staff     = this.auth.staff;
  readonly schedule  = signal<ScheduleResponse | null>(null);
  readonly loading   = signal(true);
  readonly error     = signal<string | null>(null);
  readonly today     = new Date().toISOString().slice(0, 10);
  readonly date      = signal(this.today);

  /** Tracks which appointment is currently being actioned (spinner) */
  readonly actioning = signal<string | null>(null);

  ngOnInit(): void {
    this.loadSchedule();
  }

  loadSchedule(): void {
    this.loading.set(true);
    this.error.set(null);

    this.api.getSchedule(this.date()).subscribe({
      next: (s) => {
        this.schedule.set(s);
        this.loading.set(false);
      },
      error: (err: ApiError) => {
        this.loading.set(false);
        if (err.status === 401 || err.status === 422) {
          this.auth.logout();
          this.loggedOut.emit();
        } else {
          this.error.set(err.message ?? 'Failed to load schedule.');
        }
      },
    });
  }

  onDateChange(value: string): void {
    this.date.set(value);
    this.loadSchedule();
  }

  transition(appt: ScheduleAppointment, newStatus: string): void {
    this.actioning.set(appt.id);

    this.api.updateBookingStatus(appt.id, newStatus).subscribe({
      next: (res) => {
        this.actioning.set(null);
        // Mutate the local list so the UI updates instantly
        const s = this.schedule();
        if (s) {
          this.schedule.set({
            ...s,
            appointments: s.appointments.map((a) =>
              a.id === appt.id ? { ...a, status: res.status } : a,
            ),
          });
        }
      },
      error: (err: ApiError) => {
        this.actioning.set(null);
        alert(`Could not update status: ${err.message}`);
      },
    });
  }

  logout(): void {
    this.auth.logout();
    this.loggedOut.emit();
  }

  navigateToAdmin(): void {
    this.goAdmin.emit();
  }

  /** Which action buttons are valid for a given status */
  nextActions(status: string): { label: string; next: string; style: string }[] {
    switch (status) {
      case 'pending':
        return [
          { label: 'Confirm',  next: 'confirmed', style: 'btn-confirm' },
          { label: 'Cancel',   next: 'cancelled', style: 'btn-cancel'  },
        ];
      case 'confirmed':
        return [
          { label: 'Complete', next: 'completed', style: 'btn-complete' },
          { label: 'Cancel',   next: 'cancelled', style: 'btn-cancel'   },
        ];
      default:
        return [];
    }
  }
}
