import {
  ChangeDetectionStrategy, Component, EventEmitter,
  HostListener, OnInit, Output, inject, signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { AdminApiService, AdminProvider, ApiError } from '../../services/admin-api.service';
import { AutofocusDirective } from '../../shared/autofocus.directive';
import { ConfirmService } from '../../services/confirm.service';
import { ToastService } from '../../services/toast.service';

@Component({
  selector: 'app-providers-section',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, AutofocusDirective],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styles: [`
    :host { display: block; padding: 1.5rem; }
    .sh { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
    .sh h2 { margin:0; font-size:1.15rem; }
    .tw { overflow-x:auto; background:#fff; border:1px solid #e5e7eb; border-radius:8px; }
    table { width:100%; border-collapse:collapse; font-size:0.9rem; }
    th { background:#f9fafb; padding:.6rem .9rem; text-align:left; border-bottom:1px solid #e5e7eb; font-weight:600; white-space:nowrap; }
    td { padding:.6rem .9rem; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    .badge-on  { background:#d1fae5; color:#065f46; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:700; }
    .badge-off { background:#fee2e2; color:#991b1b; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:700; }
    .muted { color:#9ca3af; font-size:.8rem; }
    .btn { padding:.28rem .65rem; font:inherit; font-size:.82rem; border-radius:4px; cursor:pointer; border:1px solid transparent; margin-right:.25rem; font-weight:600; }
    .btn:disabled { opacity:.5; cursor:not-allowed; }
    .btn-blue  { background:#2563eb; color:#fff; border-color:#2563eb; }
    .btn-blue:hover:not(:disabled)  { background:#1d4ed8; }
    .btn-gray  { background:#f3f4f6; color:#374151; border-color:#d1d5db; }
    .btn-gray:hover:not(:disabled)  { background:#e5e7eb; }
    .btn-red   { background:#fee2e2; color:#991b1b; border-color:#fca5a5; }
    .btn-red:hover:not(:disabled)   { background:#fecaca; }
    .btn-green { background:#d1fae5; color:#065f46; border-color:#6ee7b7; }
    .btn-green:hover:not(:disabled) { background:#bbf7d0; }
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
      <h2>Providers</h2>
      <div style="display:flex;align-items:center;gap:1rem">
        <label class="check-row">
          <input type="checkbox" [checked]="showInactive()" (change)="toggleInactive()">
          Show inactive
        </label>
        <button class="btn btn-blue" (click)="openCreate()">+ Add Provider</button>
      </div>
    </div>

    @if (loading()) { <div class="empty-msg">Loading…</div> }
    @if (listErr()) { <div class="alert-err">{{ listErr() }}</div> }

    @if (!loading() && providers().length === 0 && !listErr()) {
      @if (showInactive()) {
        <div class="empty-card">
          <div class="empty-icon">👨‍⚕️</div>
          <h3>No providers yet</h3>
          <p>Add your first doctor or practitioner to start accepting bookings.</p>
          <button class="btn btn-blue" (click)="openCreate()">+ Add your first provider</button>
        </div>
      } @else {
        <div class="empty-card">
          <div class="empty-icon">🚫</div>
          <h3>No active providers</h3>
          <p>All providers are deactivated. Toggle <strong>Show inactive</strong> above to view or restore them.</p>
        </div>
      }
    }

    @if (!loading() && providers().length > 0) {
      <div class="tw">
        <table>
          <thead><tr>
            <th>Name</th><th>Specialty</th><th>Slug</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
            @for (p of providers(); track p.id) {
              <tr>
                <td><strong>{{ p.name }}</strong></td>
                <td>{{ p.specialty }}</td>
                <td><span class="muted">{{ p.slug }}</span></td>
                <td>
                  @if (+p.is_active) { <span class="badge-on">Active</span> }
                  @else              { <span class="badge-off">Inactive</span> }
                </td>
                <td>
                  <button class="btn btn-gray" (click)="openEdit(p)">Edit</button>
                  @if (+p.is_active) {
                    <button class="btn btn-red" (click)="deactivate(p)">Deactivate</button>
                  } @else {
                    <button class="btn btn-green" (click)="restore(p)">Restore</button>
                  }
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }

    <!-- Create / Edit modal -->
    @if (showModal()) {
      <div class="modal-back" (click)="closeModal()">
        <div class="modal" (click)="$event.stopPropagation()">
          <h3>{{ editing() ? 'Edit Provider' : 'Add Provider' }}</h3>
          @if (formErr()) { <div class="alert-err" role="alert">{{ formErr() }}</div> }
          <form [formGroup]="form" (ngSubmit)="save()">
            <fieldset [disabled]="saving()" style="border:none;padding:0;margin:0">
              <div class="field">
                <label for="prov-name">Name *</label>
                <input id="prov-name" formControlName="name" placeholder="Dr. Maria Santos" appAutofocus />
              </div>
              <div class="field">
                <label for="prov-spec">Specialty *</label>
                <input id="prov-spec" formControlName="specialty" placeholder="General Medicine" />
              </div>
              <div class="field">
                <label for="prov-slug">Slug <small>(optional — auto-generated if blank)</small></label>
                <input id="prov-slug" formControlName="slug" placeholder="dr-maria-santos" />
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
export class ProvidersSectionComponent implements OnInit {
  private readonly api     = inject(AdminApiService);
  private readonly fb      = inject(FormBuilder);
  private readonly toast   = inject(ToastService);
  private readonly confirm = inject(ConfirmService);

  /** Bubbles a 401 up to the admin-panel shell so it can log the user out cleanly. */
  @Output() unauthorized = new EventEmitter<void>();

  readonly providers    = signal<AdminProvider[]>([]);
  readonly loading      = signal(true);
  readonly saving       = signal(false);
  readonly listErr      = signal<string | null>(null);
  readonly formErr      = signal<string | null>(null);
  readonly showModal    = signal(false);
  readonly editing      = signal<AdminProvider | null>(null);
  readonly showInactive = signal(false);

  readonly form = this.fb.nonNullable.group({
    name:      ['', [Validators.required]],
    specialty: ['', [Validators.required]],
    slug:      [''],
  });

  @HostListener('document:keydown.escape')
  onEsc(): void { if (this.showModal() && !this.saving()) this.closeModal(); }

  ngOnInit(): void { this.load(); }

  load(): void {
    this.loading.set(true);
    this.listErr.set(null);
    this.api.getProviders(this.showInactive()).subscribe({
      next:  (list) => { this.providers.set(list); this.loading.set(false); },
      error: (e: ApiError) => { this.handleErr(e, /*toListErr*/ true); this.loading.set(false); },
    });
  }

  toggleInactive(): void {
    this.showInactive.update((v) => !v);
    this.load();
  }

  openCreate(): void {
    this.editing.set(null);
    this.resetForm();
    this.showModal.set(true);
  }

  openEdit(p: AdminProvider): void {
    this.editing.set(p);
    this.form.reset({ name: p.name, specialty: p.specialty, slug: p.slug });
    this.formErr.set(null);
    this.showModal.set(true);
  }

  closeModal(): void {
    this.showModal.set(false);
    // Reset so the next openCreate / openEdit starts from a clean slate even
    // if the user dismissed the previous attempt mid-edit.
    this.resetForm();
    this.editing.set(null);
  }

  save(): void {
    if (this.saving()) return;

    if (this.form.invalid) {
      this.formErr.set('Name and Specialty are required.');
      return;
    }

    const { name, specialty, slug } = this.form.getRawValue();
    this.saving.set(true);
    this.formErr.set(null);

    const body = {
      name:      name.trim(),
      specialty: specialty.trim(),
      ...(slug.trim() ? { slug: slug.trim() } : {}),
    };
    const p     = this.editing();
    const isNew = !p;
    const req   = p ? this.api.updateProvider(p.id, body) : this.api.createProvider(body);

    req.subscribe({
      next: () => {
        this.saving.set(false);
        this.closeModal();
        this.toast.success(isNew ? 'Provider created.' : 'Provider updated.');
        this.load();
      },
      error: (e: ApiError) => {
        this.saving.set(false);
        this.handleErr(e, /*toListErr*/ false);
      },
    });
  }

  async deactivate(p: AdminProvider): Promise<void> {
    const ok = await this.confirm.ask(
      `Deactivate "${p.name}"? Existing appointments are preserved; the provider just won't appear on the booking page.`,
      { title: 'Deactivate provider?', confirmLabel: 'Deactivate', danger: true },
    );
    if (!ok) return;
    this.api.deleteProvider(p.id).subscribe({
      next:  () => { this.toast.success(`${p.name} deactivated.`); this.load(); },
      error: (e: ApiError) => this.handleErr(e, false),
    });
  }

  restore(p: AdminProvider): void {
    this.api.restoreProvider(p.id).subscribe({
      next:  () => { this.toast.success(`${p.name} reactivated.`); this.load(); },
      error: (e: ApiError) => this.handleErr(e, false),
    });
  }

  private resetForm(): void {
    this.form.reset({ name: '', specialty: '', slug: '' });
    this.formErr.set(null);
  }

  /** Centralised error handling: 401 logs out, others toast or surface in form. */
  private handleErr(e: ApiError, toListErr: boolean): void {
    if (e.status === 401) { this.unauthorized.emit(); return; }
    if (toListErr) {
      this.listErr.set(e.message);
    } else if (this.showModal()) {
      this.formErr.set(e.message);
    } else {
      this.toast.error(e.message);
    }
  }
}
