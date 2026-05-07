import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  Output,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { CommonModule } from '@angular/common';

import { AuthService } from '../services/auth.service';
import { StaffInfo } from '../models/staff.model';

@Component({
  selector: 'app-staff-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './staff-login.component.html',
  styleUrls: ['./staff-login.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StaffLoginComponent {
  private readonly auth = inject(AuthService);
  private readonly fb   = inject(FormBuilder);

  @Output() loggedIn = new EventEmitter<StaffInfo>();

  readonly form = this.fb.nonNullable.group({
    username: ['', [Validators.required]],
    password: ['', [Validators.required]],
  });
  readonly loading = signal(false);
  readonly error   = signal<string | null>(null);

  onSubmit(): void {
    if (this.loading() || this.form.invalid) return;
    this.error.set(null);
    this.loading.set(true);

    const { username, password } = this.form.getRawValue();

    this.auth.login(username, password).subscribe({
      next: (staff) => {
        this.loading.set(false);
        this.loggedIn.emit(staff);
      },
      error: (err) => {
        this.loading.set(false);
        const code = err?.code as string | undefined;
        if (code === 'TOO_MANY_ATTEMPTS') {
          this.error.set(err.message ?? 'Too many attempts. Try again later.');
        } else if (code === 'INVALID_CREDENTIALS') {
          this.error.set('Invalid username or password.');
        } else {
          this.error.set(err?.message ?? 'Login failed. Please try again.');
        }
      },
    });
  }
}
