import {
  ChangeDetectionStrategy, Component, EventEmitter,
  Output, inject, signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  AbstractControl, FormBuilder, ReactiveFormsModule,
  ValidationErrors, ValidatorFn, Validators,
} from '@angular/forms';

import { AuthService } from '../services/auth.service';

/** Cross-field validator: confirm field must match newPw. */
const matchNewPasswordValidator: ValidatorFn = (group: AbstractControl): ValidationErrors | null => {
  const newPw   = group.get('newPw')?.value as string;
  const confirm = group.get('confirm')?.value as string;
  if (!newPw || !confirm) return null;
  return newPw === confirm ? null : { passwordMismatch: true };
};

@Component({
  selector: 'app-change-password',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styles: [`
    :host { display:flex; min-height:100vh; align-items:center; justify-content:center; background:#f3f4f6; font-family:system-ui,-apple-system,sans-serif; }
    .card { background:#fff; border-radius:10px; padding:2rem 2.5rem; width:400px; max-width:95vw; box-shadow:0 4px 24px rgba(0,0,0,.1); }
    .brand { display:flex; align-items:center; gap:.5rem; margin-bottom:1.5rem; }
    .brand-logo { font-size:1.8rem; }
    .brand-title { font-size:1.1rem; font-weight:700; color:#1f2937; }
    h2 { margin:0 0 .3rem; font-size:1.2rem; color:#111827; }
    p.sub  { margin:0 0 1.5rem; font-size:.87rem; color:#6b7280; }
    .field { margin-bottom:1rem; }
    .field label { display:block; font-size:.83rem; font-weight:600; margin-bottom:.35rem; color:#374151; }
    .field input { width:100%; padding:.45rem .7rem; border:1px solid #d1d5db; border-radius:5px; font:inherit; font-size:.9rem; box-sizing:border-box; }
    .field input:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 2px rgba(37,99,235,.15); }
    .btn-submit { width:100%; padding:.6rem; background:#2563eb; color:#fff; border:none; border-radius:5px; font:inherit; font-size:.95rem; font-weight:700; cursor:pointer; margin-top:.25rem; }
    .btn-submit:hover:not(:disabled) { background:#1d4ed8; }
    .btn-submit:disabled { opacity:.55; cursor:not-allowed; }
    .alert-err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:.6rem .9rem; border-radius:6px; margin-bottom:1rem; font-size:.875rem; }
    .notice { background:#fef3c7; border:1px solid #fcd34d; color:#92400e; padding:.65rem .9rem; border-radius:6px; margin-bottom:1.2rem; font-size:.87rem; }
    .notice strong { display:block; margin-bottom:.2rem; }
  `],
  template: `
    <div class="card">
      <div class="brand">
        <span class="brand-logo">🏥</span>
        <span class="brand-title">Clinic Admin</span>
      </div>

      <h2>Set Your Password</h2>
      <p class="sub">You're using the default credentials. Choose a new password to continue.</p>

      <div class="notice">
        <strong>⚠ Required before you can continue</strong>
        You must change the default password to access the admin panel.
      </div>

      @if (error()) { <div class="alert-err" role="alert">{{ error() }}</div> }

      <form [formGroup]="form" (ngSubmit)="submit()">
        <div class="field">
          <label for="cp-current">Current Password</label>
          <input id="cp-current" type="password" formControlName="current" autocomplete="current-password" />
        </div>
        <div class="field">
          <label for="cp-new">New Password <span style="color:#9ca3af;font-weight:400;font-size:.78rem">(min. 8 characters)</span></label>
          <input id="cp-new" type="password" formControlName="newPw" autocomplete="new-password" />
        </div>
        <div class="field">
          <label for="cp-confirm">Confirm New Password</label>
          <input id="cp-confirm" type="password" formControlName="confirm" autocomplete="new-password" />
        </div>

        <button type="submit" class="btn-submit" [disabled]="saving()">
          {{ saving() ? 'Saving…' : 'Set Password & Continue' }}
        </button>
      </form>
    </div>
  `,
})
export class ChangePasswordComponent {
  private readonly auth = inject(AuthService);
  private readonly fb   = inject(FormBuilder);

  /** Emitted when the password has been changed successfully. */
  @Output() changed = new EventEmitter<void>();

  readonly form = this.fb.nonNullable.group(
    {
      current: ['', [Validators.required]],
      newPw:   ['', [Validators.required, Validators.minLength(8)]],
      confirm: ['', [Validators.required]],
    },
    { validators: matchNewPasswordValidator },
  );
  readonly saving = signal(false);
  readonly error  = signal<string | null>(null);

  submit(): void {
    this.error.set(null);

    if (this.form.controls.current.invalid) {
      this.error.set('Current password is required.');
      return;
    }
    if (this.form.controls.newPw.hasError('required')) {
      this.error.set('New password is required.');
      return;
    }
    if (this.form.controls.newPw.hasError('minlength')) {
      this.error.set('New password must be at least 8 characters.');
      return;
    }
    if (this.form.hasError('passwordMismatch')) {
      this.error.set('New passwords do not match.');
      return;
    }
    if (this.form.invalid) return;

    const { current, newPw } = this.form.getRawValue();
    this.saving.set(true);
    this.auth.changePassword(current, newPw).subscribe({
      next: () => {
        this.saving.set(false);
        this.changed.emit();
      },
      error: (e: { message?: string }) => {
        this.saving.set(false);
        this.error.set(e?.message ?? 'Password change failed. Please try again.');
      },
    });
  }
}
