import {
  ChangeDetectionStrategy, Component, OnInit,
  inject, signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { AdminApiService, AdminProvider, AdminStaff, ApiError } from '../../services/admin-api.service';

const ROLES = ['admin', 'receptionist', 'doctor'] as const;
type Role = typeof ROLES[number];

@Component({
  selector: 'app-staff-section',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styles: [`
    :host { display:block; padding:1.5rem; }
    .sh { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
    .sh h2 { margin:0; font-size:1.15rem; }
    .tw { overflow-x:auto; background:#fff; border:1px solid #e5e7eb; border-radius:8px; }
    table { width:100%; border-collapse:collapse; font-size:.9rem; }
    th { background:#f9fafb; padding:.6rem .9rem; text-align:left; border-bottom:1px solid #e5e7eb; font-weight:600; white-space:nowrap; }
    td { padding:.6rem .9rem; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    .badge-on  { background:#d1fae5; color:#065f46; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:700; }
    .badge-off { background:#fee2e2; color:#991b1b; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:700; }
    .badge-role { background:#dbeafe; color:#1e40af; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:700; }
    .muted { color:#9ca3af; font-size:.8rem; }
    .btn { padding:.28rem .65rem; font:inherit; font-size:.82rem; border-radius:4px; cursor:pointer; border:1px solid transparent; margin-right:.25rem; font-weight:600; }
    .btn:disabled { opacity:.5; cursor:not-allowed; }
    .btn-blue { background:#2563eb; color:#fff; border-color:#2563eb; }
    .btn-blue:hover:not(:disabled) { background:#1d4ed8; }
    .btn-gray { background:#f3f4f6; color:#374151; border-color:#d1d5db; }
    .btn-gray:hover:not(:disabled) { background:#e5e7eb; }
    .btn-red  { background:#fee2e2; color:#991b1b; border-color:#fca5a5; }
    .btn-red:hover:not(:disabled)  { background:#fecaca; }
    .modal-back { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; display:flex; align-items:center; justify-content:center; }
    .modal { background:#fff; border-radius:8px; padding:1.5rem; width:440px; max-width:95vw; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    .modal h3 { margin:0 0 1.2rem; font-size:1.05rem; }
    .field { margin-bottom:.9rem; }
    .field label { display:block; font-size:.83rem; font-weight:600; margin-bottom:.3rem; color:#374151; }
    .field input, .field select { width:100%; padding:.42rem .6rem; border:1px solid #d1d5db; border-radius:4px; font:inherit; font-size:.9rem; box-sizing:border-box; }
    .field input:focus, .field select:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 2px rgba(37,99,235,.15); }
    .field small { color:#6b7280; font-size:.78rem; }
    .modal-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.2rem; }
    .alert-err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:.6rem .9rem; border-radius:6px; margin-bottom:.9rem; font-size:.875rem; }
    .empty-msg { text-align:center; padding:3rem; color:#9ca3af; }
    .check-row { display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:#374151; cursor:pointer; }
    .provider-name { font-size:.8rem; color:#6b7280; }
  `],
  template: `
    <div class="sh">
      <h2>Staff Accounts</h2>
      <div style="display:flex;align-items:center;gap:1rem">
        <label class="check-row">
          <input type="checkbox" [checked]="showInactive()" (change)="toggleInactive()">
          Show inactive
        </label>
        <button class="btn btn-blue" (click)="openCreate()">+ Add Staff</button>
      </div>
    </div>

    @if (loading()) { <div class="empty-msg">Loading…</div> }
    @if (listErr()) { <div class="alert-err">{{ listErr() }}</div> }

    @if (!loading() && staff().length === 0 && !listErr()) {
      <div class="empty-msg">No staff accounts found.</div>
    }

    @if (!loading() && staff().length > 0) {
      <div class="tw">
        <table>
          <thead><tr>
            <th>Username</th><th>Name</th><th>Role</th><th>Provider</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
            @for (s of staff(); track s.id) {
              <tr>
                <td><strong>{{ s.username }}</strong></td>
                <td>{{ s.name }}</td>
                <td><span class="badge-role">{{ s.role }}</span></td>
                <td>
                  @if (s.provider_id) {
                    <span class="provider-name">{{ providerName(s.provider_id) }}</span>
                  } @else { <span class="muted">—</span> }
                </td>
                <td>
                  @if (+s.is_active) { <span class="badge-on">Active</span> }
                  @else              { <span class="badge-off">Inactive</span> }
                </td>
                <td>
                  <button class="btn btn-gray" (click)="openEdit(s)">Edit</button>
                  @if (+s.is_active) {
                    <button class="btn btn-red" (click)="deactivate(s)">Deactivate</button>
                  }
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }

    @if (showModal()) {
      <div class="modal-back" (click)="closeModal()">
        <div class="modal" (click)="$event.stopPropagation()">
          <h3>{{ editing() ? 'Edit Staff Account' : 'Add Staff Account' }}</h3>
          @if (formErr()) { <div class="alert-err" role="alert">{{ formErr() }}</div> }
          <form [formGroup]="form" (ngSubmit)="save()">
            <div class="field">
              <label for="staff-user">Username *</label>
              <input id="staff-user" formControlName="username" placeholder="ana.reyes" autocomplete="off" />
            </div>
            <div class="field">
              <label for="staff-name">Full Name *</label>
              <input id="staff-name" formControlName="name" placeholder="Dr. Ana Reyes" />
            </div>
            <div class="field">
              <label for="staff-pw">
                {{ editing() ? 'New Password' : 'Password *' }}
                @if (editing()) { <small>(leave blank to keep current)</small> }
              </label>
              <input id="staff-pw" type="password" formControlName="password"
                     [placeholder]="editing() ? '(unchanged)' : 'min. 8 characters'"
                     autocomplete="new-password" />
            </div>
            <div class="field">
              <label for="staff-role">Role *</label>
              <select id="staff-role" formControlName="role">
                @for (r of roles; track r) { <option [value]="r">{{ r }}</option> }
              </select>
            </div>
            @if (form.controls.role.value === 'doctor') {
              <div class="field">
                <label for="staff-provider">Linked Provider *</label>
                <select id="staff-provider" formControlName="provider_id">
                  <option value="">— Select provider —</option>
                  @for (p of providers(); track p.id) {
                    <option [value]="p.id">{{ p.name }}</option>
                  }
                </select>
              </div>
            }
            <div class="modal-actions">
              <button type="button" class="btn btn-gray" (click)="closeModal()">Cancel</button>
              <button type="submit" class="btn btn-blue" [disabled]="saving()">
                {{ saving() ? 'Saving…' : 'Save' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    }
  `,
})
export class StaffSectionComponent implements OnInit {
  private readonly api = inject(AdminApiService);
  private readonly fb  = inject(FormBuilder);

  readonly staff        = signal<AdminStaff[]>([]);
  readonly providers    = signal<AdminProvider[]>([]);
  readonly loading      = signal(true);
  readonly saving       = signal(false);
  readonly listErr      = signal<string | null>(null);
  readonly formErr      = signal<string | null>(null);
  readonly showModal    = signal(false);
  readonly editing      = signal<AdminStaff | null>(null);
  readonly showInactive = signal(false);

  readonly roles = ROLES;

  // Password is required on Create, optional on Edit. We swap validators
  // dynamically in openCreate / openEdit rather than using a giant cross-field
  // validator — easier to reason about.
  readonly form = this.fb.nonNullable.group({
    username:    ['',                     [Validators.required]],
    name:        ['',                     [Validators.required]],
    password:    [''],
    role:        ['receptionist' as Role, [Validators.required]],
    provider_id: [''],
  });

  ngOnInit(): void {
    this.load();
    this.api.getProviders(false).subscribe({ next: (list) => this.providers.set(list) });

    // Clear provider_id whenever the role changes away from 'doctor', and
    // require it whenever it lands on 'doctor'.
    this.form.controls.role.valueChanges.subscribe((role) => {
      const pid = this.form.controls.provider_id;
      if (role === 'doctor') {
        pid.addValidators(Validators.required);
      } else {
        pid.clearValidators();
        pid.setValue('');
      }
      pid.updateValueAndValidity({ emitEvent: false });
    });
  }

  load(): void {
    this.loading.set(true);
    this.listErr.set(null);
    this.api.getStaff(this.showInactive()).subscribe({
      next:  (list) => { this.staff.set(list); this.loading.set(false); },
      error: (e: ApiError) => { this.listErr.set(e.message); this.loading.set(false); },
    });
  }

  toggleInactive(): void { this.showInactive.update((v) => !v); this.load(); }

  providerName(id: string): string {
    return this.providers().find((p) => p.id === id)?.name ?? id;
  }

  openCreate(): void {
    this.editing.set(null);
    this.form.reset({ username: '', name: '', password: '', role: 'receptionist', provider_id: '' });
    // Password required on create.
    this.form.controls.password.setValidators([Validators.required, Validators.minLength(8)]);
    this.form.controls.password.updateValueAndValidity({ emitEvent: false });
    this.formErr.set(null);
    this.showModal.set(true);
  }

  openEdit(s: AdminStaff): void {
    this.editing.set(s);
    this.form.reset({
      username:    s.username,
      name:        s.name,
      password:    '',
      role:        s.role as Role,
      provider_id: s.provider_id ?? '',
    });
    // Password optional on edit, but if provided must still meet the min-length rule.
    this.form.controls.password.setValidators([Validators.minLength(8)]);
    this.form.controls.password.updateValueAndValidity({ emitEvent: false });
    this.formErr.set(null);
    this.showModal.set(true);
  }

  closeModal(): void { this.showModal.set(false); }

  save(): void {
    if (this.saving()) return;
    this.formErr.set(null);

    const { username, name, password, role, provider_id } = this.form.getRawValue();
    const isEdit = !!this.editing();

    if (this.form.controls.username.invalid)            { this.formErr.set('Username is required.'); return; }
    if (this.form.controls.name.invalid)                { this.formErr.set('Name is required.'); return; }
    if (this.form.controls.password.hasError('required'))  { this.formErr.set('Password is required.'); return; }
    if (this.form.controls.password.hasError('minlength')) { this.formErr.set('Password must be at least 8 characters.'); return; }
    if (role === 'doctor' && !provider_id)              { this.formErr.set('Doctor accounts must be linked to a provider.'); return; }
    if (this.form.invalid) return;

    this.saving.set(true);
    const s = this.editing();

    if (isEdit && s) {
      const patch: Record<string, unknown> = { username: username.trim(), name: name.trim(), role };
      if (role === 'doctor') patch['provider_id'] = provider_id;
      if (password)          patch['password']    = password;

      this.api.updateStaff(s.id, patch).subscribe({
        next:  () => { this.saving.set(false); this.closeModal(); this.load(); },
        error: (e: ApiError) => { this.saving.set(false); this.formErr.set(e.message); },
      });
    } else {
      const body: Record<string, unknown> = {
        username: username.trim(), name: name.trim(), password, role,
      };
      if (role === 'doctor') body['provider_id'] = provider_id;

      this.api.createStaff(body as Parameters<typeof this.api.createStaff>[0]).subscribe({
        next:  () => { this.saving.set(false); this.closeModal(); this.load(); },
        error: (e: ApiError) => { this.saving.set(false); this.formErr.set(e.message); },
      });
    }
  }

  deactivate(s: AdminStaff): void {
    if (!confirm(`Deactivate "${s.username}"?`)) return;
    this.api.deleteStaff(s.id).subscribe({ next: () => this.load(), error: (e: ApiError) => alert(e.message) });
  }
}
