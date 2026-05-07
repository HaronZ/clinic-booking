import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  Output,
  inject,
  signal,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';

import { AuthService } from '../services/auth.service';
import { StaffInfo } from '../models/staff.model';

@Component({
  selector: 'app-staff-login',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './staff-login.component.html',
  styleUrls: ['./staff-login.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StaffLoginComponent {
  private readonly auth = inject(AuthService);

  @Output() loggedIn = new EventEmitter<StaffInfo>();

  readonly username = signal('');
  readonly password = signal('');
  readonly loading  = signal(false);
  readonly error    = signal<string | null>(null);

  onSubmit(): void {
    if (this.loading()) return;
    this.error.set(null);
    this.loading.set(true);

    this.auth.login(this.username(), this.password()).subscribe({
      next: (staff) => {
        this.loading.set(false);
        this.loggedIn.emit(staff);
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(
          err.code === 'INVALID_CREDENTIALS'
            ? 'Invalid username or password.'
            : (err.message ?? 'Login failed. Please try again.'),
        );
      },
    });
  }
}
