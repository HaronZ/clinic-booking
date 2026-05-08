import {
  ChangeDetectionStrategy, Component, EventEmitter,
  HostListener, OnInit, Output, inject, signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { AdminApiService, AdminAppointmentType, ApiError } from '../../services/admin-api.service';
import { AutofocusDirective } from '../../shared/autofocus.directive';
import { ConfirmService } from '../../services/confirm.service';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-types-section',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, AutofocusDirective],
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
    .muted { color:#9ca3af; font-size:.8rem; }
    .slug-hint { font-size:.72rem; color:#9ca3af; margin-top:.1rem; font-family:monospace; letter-spacing:.01em; }
    .btn { padding:.28rem .65rem; font:inherit; font-size:.82rem; border-radius:4px; cursor:pointer; border:1px solid transparent; margin-right:.25rem; font-weight:600; }
    .btn:disabled { opacity:.5; cursor:not-allowed; }
    .btn-blue { background:#2563eb; color:#fff; border-color:#2563eb; }
    .btn-blue:hover:not(:disabled) { background:#1d4ed8; }
    .btn-gray { background:#f3f4f6; color:#374151; border-color:#d1d5db; }
    .btn-gray:hover:not(:disabled) { background:#e5e7eb; }
    .btn-red  { background:#fee2e2; color:#991b1b; border-color:#fca5a5; }
    .btn-red:hover:not(:disabled)  { background:#fecaca; }
    .modal-back { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; display:flex; align-items:center; justify-content:center; }
    .modal { background:#fff; border-radius:8px; padding:1.5rem; width:420px; max-width:95vw; box-shadow:0 20px 60px rgba(0,0,0,.3); }
    .modal h3 { margin:0 0 1.2rem; font-size:1.05rem; }
    .field { margin-bottom:.9rem; }
    .field label { display:block; font-size:.83rem; font-weight:600; margin-bottom:.3rem; color:#374151; }
    .field input { width:100%; padding:.42rem .6rem; border:1px solid #d1d5db; border-radius:4px; font:inherit; font-size:.9rem; box-sizing:border-box; }
    .field input:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 2px rgba(37,99,235,.15); }
    .field small { color:#6b7280; font-size:.78rem; }
    .modal-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:1.2rem; }
    .alert-err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:.6rem .9rem; border-radius:6px; margin-bottom:.9rem; font-size:.875rem; }
    .empty-msg { text-align:center; padding:3rem; color:#9ca3af; }
    .empty-card { text-align:center; padding:3rem 1.5rem; background:#fff; border:1px dashed #d1d5db; border-radius:8px; color:#6b7280; }
    .empty-card .empty-icon { font-size:2.5rem; margin-bottom:.5rem; }
    .empty-card h3 { margin:0 0 .25rem; font-size:1.05rem; color:#374151; }
    .empty-card p { margin:0 0 1rem; font-size:.9rem; }
    .check-row { display:flex; align-items:center; gap:.5rem; font-size:.85rem; color:#374151; margin-bottom:1rem; cursor:pointer; }
  `],
  template: `
    <div class="sh">
      <h2>Appointment Types</h2>
      <div style="display:flex;align-items:center;gap:1rem">
        <label class="check-row">
          <input type="checkbox" [checked]="showInactive()" (change)="toggleInactive()">
          Show inactive
        </label>
        <button class="btn btn-blue" (click)="openCreate()">+ Add Type</button>
      </div>
    </div>

    @if (loading()) { <div class="empty-msg">Loading…</div> }
    @if (listErr()) { <div class="alert-err">{{ listErr() }}</div> }

    @if (!loading() && types().length === 0 && !listErr()) {
      @if (showInactive()) {
        <div class="empty-card">
          <div class="empty-icon">📋</div>
          <h3>No appointment types yet</h3>
          <p>Define visit types like "General Consultation" or "Follow-up" so patients can book them.</p>
          <button class="btn btn-blue" (click)="openCreate()">+ Add your first type</button>
        </div>
      } @else {
        <div class="empty-card">
          <div class="empty-icon">🚫</div>
          <h3>No active appointment types</h3>
          <p>All types are deactivated. Toggle <strong>Show inactive</strong> above to view or restore them.</p>
        </div>
      }
    }

    @if (!loading() && types().length > 0) {
      <div class="tw">
        <table>
          <thead><tr>
            <th>Name</th><th>Duration</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
            @for (t of types(); track t.id) {
              <tr>
                <td>
                  <strong>{{ t.name }}</strong>
                  <div class="slug-hint">{{ t.slug }}</div>
                </td>
                <td>{{ t.duration_minutes }} min</td>
                <td>
                  @if (+t.is_active) { <span class="badge-on">Active</span> }
                  @else              { <span class="badge-off">Inactive</span> }
                </td>
                <td>
                  <button class="btn btn-gray" (click)="openEdit(t)">Edit</button>
                  @if (+t.is_active) {
                    <button class="btn btn-red"
                      title="Hides from new bookings. Existing appointments using this type are unaffected."
                      (click)="deactivate(t)">Deactivate</button>
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
          <h3>{{ editing() ? 'Edit Appointment Type' : 'Add Appointment Type' }}</h3>
          @if (formErr()) { <div class="alert-err" role="alert">{{ formErr() }}</div> }
          <form [formGroup]="form" (ngSubmit)="save()">
            <fieldset [disabled]="saving()" style="border:none;padding:0;margin:0">
              <div class="field">
                <label for="type-name">Name *</label>
                <input id="type-name" formControlName="name" placeholder="General Consultation" appAutofocus />
              </div>
              <div class="field">
                <label for="type-dur">Duration (minutes) * <small>1–480</small></label>
                <input id="type-dur" type="number" formControlName="duration_minutes" min="1" max="480" placeholder="30" />
              </div>
              <div class="modal-actions">
                <button type="button" class="btn btn-gray" (click)="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-blue" [disabled]="saving() || form.invalid">
                  {{ saving() ? 'Saving…' : 'Save' }}
                </button>
              </div>
            </fieldset>
          </form>
        </div>
      </div>
    }
  `,
})
export class TypesSectionComponent implements OnInit {
  private readonly api     = inject(AdminApiService);
  private readonly fb      = inject(FormBuilder);
  private readonly toast   = inject(ToastService);
  private readonly confirm = inject(ConfirmService);

  @Output() unauthorized = new EventEmitter<void>();

  readonly types        = signal<AdminAppointmentType[]>([]);
  readonly loading      = signal(true);
  readonly saving       = signal(false);
  readonly listErr      = signal<string | null>(null);
  readonly formErr      = signal<string | null>(null);
  readonly showModal    = signal(false);
  readonly editing      = signal<AdminAppointmentType | null>(null);
  readonly showInactive = signal(false);

  // duration_minutes is bound as number | null because <input type="number">
  // emits null when the field is empty. Validators clamp to 1–480.
  readonly form = this.fb.nonNullable.group({
    name:             ['', [Validators.required]],
    duration_minutes: [null as number | null, [Validators.required, Validators.min(1), Validators.max(480)]],
  });

  @HostListener('document:keydown.escape')
  onEsc(): void { if (this.showModal() && !this.saving()) this.closeModal(); }

  ngOnInit(): void { this.load(); }

  load(): void {
    this.loading.set(true);
    this.listErr.set(null);
    this.api.getTypes(this.showInactive()).subscribe({
      next:  (list) => { this.types.set(list); this.loading.set(false); },
      error: (e: ApiError) => { this.handleErr(e, true); this.loading.set(false); },
    });
  }

  toggleInactive(): void { this.showInactive.update((v) => !v); this.load(); }

  openCreate(): void {
    this.editing.set(null);
    this.resetForm();
    this.showModal.set(true);
  }

  openEdit(t: AdminAppointmentType): void {
    this.editing.set(t);
    this.form.reset({ name: t.name, duration_minutes: t.duration_minutes });
    this.formErr.set(null);
    this.showModal.set(true);
  }

  closeModal(): void {
    this.showModal.set(false);
    this.resetForm();
    this.editing.set(null);
  }

  save(): void {
    if (this.saving()) return;

    if (this.form.controls.name.invalid) {
      this.formErr.set('Name is required.');
      return;
    }
    if (this.form.controls.duration_minutes.invalid) {
      this.formErr.set('Duration must be between 1 and 480 minutes.');
      return;
    }
    if (this.form.invalid) return;

    const { name, duration_minutes } = this.form.getRawValue();
    this.saving.set(true);
    this.formErr.set(null);

    const body = {
      name: name.trim(),
      duration_minutes: Number(duration_minutes),
    };
    const t     = this.editing();
    const isNew = !t;
    const req   = t ? this.api.updateType(t.id, body) : this.api.createType(body);

    req.subscribe({
      next:  () => {
        this.saving.set(false);
        this.closeModal();
        this.toast.success(isNew ? 'Appointment type created.' : 'Appointment type updated.');
        this.load();
      },
      error: (e: ApiError) => { this.saving.set(false); this.handleErr(e, false); },
    });
  }

  async deactivate(t: AdminAppointmentType): Promise<void> {
    const ok = await this.confirm.ask(
      `Deactivate "${t.name}"? Existing appointments keep using it; new bookings won't see it.`,
      { title: 'Deactivate appointment type?', confirmLabel: 'Deactivate', danger: true },
    );
    if (!ok) return;
    this.api.deleteType(t.id).subscribe({
      next:  () => { this.toast.success(`${t.name} deactivated.`); this.load(); },
      error: (e: ApiError) => this.handleErr(e, false),
    });
  }

  private resetForm(): void {
    this.form.reset({ name: '', duration_minutes: null });
    this.formErr.set(null);
  }

  private handleErr(e: ApiError, toListErr: boolean): void {
    if (e.status === 401) { this.unauthorized.emit(); return; }
    if (toListErr) { this.listErr.set(e.message); return; }
    if (this.showModal()) { this.formErr.set(e.message); return; }
    this.toast.error(e.message);
  }
}
