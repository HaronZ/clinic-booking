import {
  ChangeDetectionStrategy,
  Component,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';

import { BookingComponent }          from './booking/booking.component';
import { ConfirmationComponent }     from './confirmation/confirmation.component';
import { StaffLoginComponent }       from './staff-login/staff-login.component';
import { StaffDashboardComponent }   from './staff-dashboard/staff-dashboard.component';
import { AdminPanelComponent }       from './admin-panel/admin-panel.component';
import { ChangePasswordComponent }   from './change-password/change-password.component';
import { ToastComponent }            from './shared/toast.component';
import { ConfirmDialogComponent }    from './shared/confirm-dialog.component';
import { AuthService }               from './services/auth.service';

type View = 'booking' | 'confirmation' | 'staff-login' | 'staff-dashboard' | 'admin-panel' | 'change-password';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule,
    BookingComponent,
    ConfirmationComponent,
    StaffLoginComponent,
    StaffDashboardComponent,
    AdminPanelComponent,
    ChangePasswordComponent,
    ToastComponent,
    ConfirmDialogComponent,
  ],
  template: `
    <!-- Patient-facing views -->
    @if (view() === 'booking') {
      <app-booking (bookingCreated)="onBookingCreated($event)" />
    } @else if (view() === 'confirmation' && bookingId()) {
      <app-confirmation [bookingId]="bookingId()!" />
    }

    <!-- Staff views -->
    @if (view() === 'staff-login') {
      <app-staff-login (loggedIn)="onLoggedIn()" />
    } @else if (view() === 'staff-dashboard') {
      <app-staff-dashboard (loggedOut)="onLoggedOut()" (goAdmin)="view.set('admin-panel')" />
    } @else if (view() === 'admin-panel') {
      <app-admin-panel (loggedOut)="onLoggedOut()" (goDashboard_)="view.set('staff-dashboard')" />
    } @else if (view() === 'change-password') {
      <app-change-password (changed)="onPasswordChanged()" />
    }

    <!-- Tiny nav link for staff -->
    @if (view() === 'booking' || view() === 'confirmation') {
      <nav class="staff-nav no-print">
        <a href="#" (click)="goToStaff($event)">Staff login</a>
      </nav>
    }

    <!-- Global UX hosts: mounted once, used by every screen via DI -->
    <app-toast />
    <app-confirm-dialog />
  `,
  styles: [`
    .staff-nav {
      text-align: center;
      padding: 1.5rem;
      font-family: system-ui, sans-serif;
    }
    .staff-nav a {
      color: #9ca3af;
      font-size: 0.8rem;
      text-decoration: none;
    }
    .staff-nav a:hover { color: #4b5563; }
  `],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AppComponent {
  private readonly auth = inject(AuthService);

  readonly bookingId = signal<string | null>(null);
  readonly view      = signal<View>(this.initialView());

  onBookingCreated(id: string): void {
    this.bookingId.set(id);
    this.view.set('confirmation');
  }

  goToStaff(e: Event): void {
    e.preventDefault();
    this.view.set(this.auth.isLoggedIn() ? this.staffHome() : 'staff-login');
  }

  onLoggedIn(): void {
    this.view.set(this.staffHome());
  }

  onLoggedOut(): void {
    this.view.set('staff-login');
  }

  onPasswordChanged(): void {
    // Password changed — token is already refreshed in auth.service.
    // Send admin to the admin panel; others to the staff dashboard.
    const staff = this.auth.staff();
    this.view.set(staff?.role === 'admin' ? 'admin-panel' : 'staff-dashboard');
  }

  // ── helpers ───────────────────────────────────────────────────────────────

  /**
   * Where to land immediately after login (or on page reload when still logged in).
   * Force-change check: if must_change_password, send to change-password first.
   */
  private staffHome(): View {
    const staff = this.auth.staff();
    if (!staff) return 'staff-login';
    if (staff.must_change_password) return 'change-password';
    return staff.role === 'admin' ? 'admin-panel' : 'staff-dashboard';
  }

  private initialView(): View {
    if (!this.auth.isLoggedIn()) return 'booking';
    return this.staffHome();
  }
}
