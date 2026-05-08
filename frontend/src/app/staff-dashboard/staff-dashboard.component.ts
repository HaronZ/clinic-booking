import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  HostListener,
  OnDestroy,
  OnInit,
  Output,
  computed,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormControl, ReactiveFormsModule } from '@angular/forms';

import { ApiService, ApiError } from '../services/api.service';
import { AuthService } from '../services/auth.service';
import { ToastService } from '../services/toast.service';
import { ScheduleAppointment, ScheduleResponse } from '../models/staff.model';

type StatusFilter = 'all' | 'pending' | 'confirmed' | 'completed' | 'cancelled';

@Component({
  selector: 'app-staff-dashboard',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './staff-dashboard.component.html',
  styleUrls: ['./staff-dashboard.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StaffDashboardComponent implements OnInit, OnDestroy {
  private readonly api   = inject(ApiService);
  private readonly auth  = inject(AuthService);
  private readonly toast = inject(ToastService);

  @Output() loggedOut  = new EventEmitter<void>();
  @Output() goAdmin    = new EventEmitter<void>();

  readonly staff     = this.auth.staff;
  readonly schedule  = signal<ScheduleResponse | null>(null);
  readonly loading   = signal(true);
  readonly error     = signal<string | null>(null);
  // Local date, not UTC — `toISOString()` shifts east-of-GMT users to yesterday.
  readonly today     = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  })();
  readonly date      = signal(this.today);

  /** Tracks which appointment is currently being actioned (spinner) */
  readonly actioning = signal<string | null>(null);

  /** Filter + search state */
  readonly statusFilter = signal<StatusFilter>('all');
  readonly searchCtrl   = new FormControl<string>('', { nonNullable: true });
  readonly searchQuery  = signal<string>('');

  /** "Auto-refreshing in 30s" UX — purely visual hint */
  readonly lastRefresh = signal<Date>(new Date());

  /** Stats derived from the full appointment list (not filtered) */
  readonly stats = computed(() => {
    const list = this.schedule()?.appointments ?? [];
    return {
      total:     list.length,
      pending:   list.filter((a) => a.status === 'pending').length,
      confirmed: list.filter((a) => a.status === 'confirmed').length,
      completed: list.filter((a) => a.status === 'completed').length,
      cancelled: list.filter((a) => a.status === 'cancelled').length,
    };
  });

  /** Filter + search applied to the schedule */
  readonly filteredAppointments = computed<ScheduleAppointment[]>(() => {
    const list   = this.schedule()?.appointments ?? [];
    const status = this.statusFilter();
    const q      = this.searchQuery().trim().toLowerCase();

    return list.filter((a) => {
      if (status !== 'all' && a.status !== status) return false;
      if (q) {
        const hay = `${a.patient_name} ${a.patient_phone} ${a.type_name} ${a.provider_name ?? ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });
  });

  /** Auto-refresh handle — cleared on destroy. */
  private autoRefreshTimer: ReturnType<typeof setInterval> | null = null;

  ngOnInit(): void {
    this.loadSchedule();
    this.startAutoRefresh();

    // Mirror the search field into a signal so `filteredAppointments`
    // recomputes reactively as the user types.
    this.searchCtrl.valueChanges.subscribe((v) => this.searchQuery.set(v ?? ''));
  }

  ngOnDestroy(): void {
    this.stopAutoRefresh();
  }

  /** Refresh the schedule every 30s while the user is on this view. */
  private startAutoRefresh(): void {
    this.stopAutoRefresh();
    this.autoRefreshTimer = setInterval(() => {
      // Skip if a manual load is already in flight or an action is pending.
      if (this.loading() || this.actioning()) return;
      this.loadSchedule({ silent: true });
    }, 30_000);
  }

  private stopAutoRefresh(): void {
    if (this.autoRefreshTimer) {
      clearInterval(this.autoRefreshTimer);
      this.autoRefreshTimer = null;
    }
  }

  loadSchedule(opts: { silent?: boolean } = {}): void {
    if (!opts.silent) this.loading.set(true);
    this.error.set(null);

    this.api.getSchedule(this.date()).subscribe({
      next: (s) => {
        this.schedule.set(s);
        this.loading.set(false);
        this.lastRefresh.set(new Date());
      },
      error: (err: ApiError) => {
        this.loading.set(false);
        if (err.status === 401 || err.status === 422) {
          this.auth.logout();
          this.loggedOut.emit();
        } else if (!opts.silent) {
          // Don't surface silent-refresh failures — likely transient.
          this.error.set(err.message ?? 'Failed to load schedule.');
        }
      },
    });
  }

  onDateChange(value: string): void {
    this.date.set(value);
    this.loadSchedule();
  }

  setStatusFilter(s: StatusFilter): void {
    this.statusFilter.set(s);
  }

  clearSearch(): void {
    this.searchCtrl.setValue('');
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
        this.toast.success(`Marked as ${res.status}.`);
      },
      error: (err: ApiError) => {
        this.actioning.set(null);
        if (err.status === 401) {
          this.auth.logout();
          this.loggedOut.emit();
          return;
        }
        this.toast.error(`Could not update status: ${err.message}`);
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

  /**
   * Keyboard shortcuts for power users:
   *   /  → focus search
   *   r  → manual refresh
   *   1  → All  · 2 → Pending · 3 → Confirmed · 4 → Completed
   * Skipped while a text input is focused so users can still type slashes.
   */
  @HostListener('document:keydown', ['$event'])
  onKey(event: KeyboardEvent): void {
    const target = event.target as HTMLElement | null;
    const tag = target?.tagName?.toLowerCase();
    const inField = tag === 'input' || tag === 'textarea' || tag === 'select';
    if (event.ctrlKey || event.metaKey || event.altKey) return;

    if (event.key === '/' && !inField) {
      event.preventDefault();
      const el = document.getElementById('searchInput') as HTMLInputElement | null;
      el?.focus();
      return;
    }
    if (inField) return;

    switch (event.key) {
      case 'r': case 'R': this.loadSchedule(); break;
      case '1': this.setStatusFilter('all'); break;
      case '2': this.setStatusFilter('pending'); break;
      case '3': this.setStatusFilter('confirmed'); break;
      case '4': this.setStatusFilter('completed'); break;
    }
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
