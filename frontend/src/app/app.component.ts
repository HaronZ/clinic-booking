import {
  ChangeDetectionStrategy,
  Component,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';

import { BookingComponent }        from './booking/booking.component';
import { ConfirmationComponent }   from './confirmation/confirmation.component';
import { StaffLoginComponent }     from './staff-login/staff-login.component';
import { StaffDashboardComponent } from './staff-dashboard/staff-dashboard.component';
import { AuthService }             from './services/auth.service';

type View = 'booking' | 'confirmation' | 'staff-login' | 'staff-dashboard';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    CommonModule,
    BookingComponent,
    ConfirmationComponent,
    StaffLoginComponent,
    StaffDashboardComponent,
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
      <app-staff-dashboard (loggedOut)="onLoggedOut()" />
    }

    <!-- Tiny nav link for staff -->
    @if (view() === 'booking' || view() === 'confirmation') {
      <nav class="staff-nav">
        <a href="#" (click)="goToStaff($event)">Staff login</a>
      </nav>
    }
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

  readonly view      = signal<View>(
    this.auth.isLoggedIn() ? 'staff-dashboard' : 'booking',
  );
  readonly bookingId = signal<string | null>(null);

  onBookingCreated(id: string): void {
    this.bookingId.set(id);
    this.view.set('confirmation');
  }

  goToStaff(e: Event): void {
    e.preventDefault();
    this.view.set(this.auth.isLoggedIn() ? 'staff-dashboard' : 'staff-login');
  }

  onLoggedIn(): void {
    this.view.set('staff-dashboard');
  }

  onLoggedOut(): void {
    this.view.set('staff-login');
  }
}
