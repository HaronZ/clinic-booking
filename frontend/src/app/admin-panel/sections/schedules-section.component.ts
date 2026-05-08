import {
  ChangeDetectionStrategy, Component, EventEmitter,
  OnInit, Output, inject, signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormArray, FormBuilder, FormControl, FormGroup,
  ReactiveFormsModule, Validators,
} from '@angular/forms';

import { AdminApiService, AdminProvider, ApiError } from '../../services/admin-api.service';
import { ToastService } from '../../services/toast.service';

const DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

interface DayRowControls {
  working:    FormControl<boolean>;
  start_time: FormControl<string>;
  end_time:   FormControl<string>;
}
type DayRowGroup = FormGroup<DayRowControls>;

@Component({
  selector: 'app-schedules-section',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styles: [`
    :host { display:block; padding:1.5rem; }
    .sh { display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; flex-wrap:wrap; }
    .sh h2 { margin:0; font-size:1.15rem; }
    .sh select { padding:.42rem .65rem; border:1px solid #d1d5db; border-radius:4px; font:inherit; font-size:.9rem; }
    .sh select:focus { outline:none; border-color:#2563eb; }
    .schedule-card { background:#fff; border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; max-width:680px; }
    table { width:100%; border-collapse:collapse; font-size:.9rem; }
    th { background:#f9fafb; padding:.6rem .9rem; text-align:left; border-bottom:1px solid #e5e7eb; font-weight:600; }
    td { padding:.55rem .9rem; border-bottom:1px solid #f3f4f6; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    .day-off { color:#9ca3af; background:#fafafa; }
    .day-off td:first-child strong { color:#9ca3af; }
    .off-badge { display:inline-block; margin-left:.5rem; font-size:.7rem; font-weight:600; padding:.1rem .4rem; background:#f3f4f6; color:#9ca3af; border-radius:3px; }
    input[type="time"] { padding:.38rem .5rem; border:1px solid #d1d5db; border-radius:4px; font:inherit; font-size:.88rem; }
    input[type="time"]:focus { outline:none; border-color:#2563eb; }
    input[type="time"]:disabled { background:#f9fafb; color:#9ca3af; cursor:not-allowed; }
    .actions-row { padding:1rem 1.5rem; border-top:1px solid #f3f4f6; display:flex; gap:.75rem; align-items:center; }
    .btn { padding:.35rem .8rem; font:inherit; font-size:.88rem; border-radius:4px; cursor:pointer; border:1px solid transparent; font-weight:600; }
    .btn:disabled { opacity:.5; cursor:not-allowed; }
    .btn-blue { background:#2563eb; color:#fff; border-color:#2563eb; }
    .btn-blue:hover:not(:disabled) { background:#1d4ed8; }
    .alert-err { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:.6rem .9rem; border-radius:6px; margin-bottom:.9rem; font-size:.875rem; }
    .alert-ok  { background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; padding:.6rem .9rem; border-radius:6px; font-size:.875rem; }
    .empty-msg { text-align:center; padding:3rem; color:#9ca3af; }
    .check-cell input[type="checkbox"] { width:1rem; height:1rem; cursor:pointer; }
  `],
  template: `
    <div class="sh">
      <h2>Schedules</h2>
      @if (providersLoading()) { <span style="color:#9ca3af;font-size:.9rem">Loading providers…</span> }
      @if (!providersLoading() && providers().length > 0) {
        <select [formControl]="providerCtrl">
          <option value="">— Select provider —</option>
          @for (p of providers(); track p.id) {
            <option [value]="p.id">{{ p.name }}</option>
          }
        </select>
      }
    </div>

    @if (listErr()) { <div class="alert-err" role="alert">{{ listErr() }}</div> }
    @if (savedOk())  { <div class="alert-ok"  role="status">Schedule saved successfully.</div> }

    @if (providerCtrl.value && !scheduleLoading()) {
      <div class="schedule-card">
        <form [formGroup]="weekForm" (ngSubmit)="save()">
          <table>
            <thead><tr>
              <th>Day</th><th>Working</th><th>Start</th><th>End</th>
            </tr></thead>
            <tbody formArrayName="days">
              @for (row of days.controls; track row; let i = $index) {
                <tr [formGroupName]="i" [class.day-off]="!row.controls.working.value">
                  <td>
                    <strong>{{ dayLabels[i] }}</strong>
                    @if (!row.controls.working.value) { <span class="off-badge">OFF</span> }
                  </td>
                  <td class="check-cell">
                    <input type="checkbox" formControlName="working" [attr.aria-label]="'Working on ' + dayLabels[i]" />
                  </td>
                  <td>
                    <input type="time" formControlName="start_time" [attr.disabled]="row.controls.working.value ? null : ''" />
                  </td>
                  <td>
                    <input type="time" formControlName="end_time" [attr.disabled]="row.controls.working.value ? null : ''" />
                  </td>
                </tr>
              }
            </tbody>
          </table>
          <div class="actions-row">
            <button type="submit" class="btn btn-blue" [disabled]="saving()">
              {{ saving() ? 'Saving…' : 'Save Schedule' }}
            </button>
            @if (saveErr()) { <span style="color:#991b1b;font-size:.85rem">{{ saveErr() }}</span> }
          </div>
        </form>
      </div>
    }

    @if (!providerCtrl.value && !providersLoading()) {
      <div class="empty-msg">Select a provider above to view or edit their schedule.</div>
    }
    @if (providerCtrl.value && scheduleLoading()) {
      <div class="empty-msg">Loading schedule…</div>
    }
  `,
})
export class SchedulesSectionComponent implements OnInit {
  private readonly api   = inject(AdminApiService);
  private readonly fb    = inject(FormBuilder);
  private readonly toast = inject(ToastService);

  @Output() unauthorized = new EventEmitter<void>();

  readonly providers        = signal<AdminProvider[]>([]);
  readonly providersLoading = signal(true);
  readonly scheduleLoading  = signal(false);
  readonly saving           = signal(false);
  readonly listErr          = signal<string | null>(null);
  readonly saveErr          = signal<string | null>(null);
  readonly savedOk          = signal(false);

  readonly dayLabels = DAY_LABELS;

  /** Provider dropdown — held as a typed FormControl so we can react to changes via valueChanges. */
  readonly providerCtrl = new FormControl<string>('', { nonNullable: true });

  /** 7-row FormArray. Each row mirrors the API shape minus day_of_week (positional). */
  readonly weekForm = this.fb.nonNullable.group({
    days: this.fb.array<DayRowGroup>(this.buildBlankWeek()),
  });

  get days(): FormArray<DayRowGroup> {
    return this.weekForm.controls.days;
  }

  ngOnInit(): void {
    this.api.getProviders(false).subscribe({
      next:  (list) => { this.providers.set(list); this.providersLoading.set(false); },
      error: (e: ApiError) => {
        this.providersLoading.set(false);
        if (e.status === 401) { this.unauthorized.emit(); return; }
        this.listErr.set(e.message);
      },
    });

    this.providerCtrl.valueChanges.subscribe((id) => this.onProviderChange(id));
  }

  private buildBlankWeek(): DayRowGroup[] {
    return DAY_LABELS.map((): DayRowGroup =>
      this.fb.nonNullable.group({
        working:    [false],
        start_time: ['09:00', [Validators.required]],
        end_time:   ['17:00', [Validators.required]],
      }) as DayRowGroup,
    );
  }

  private resetWeek(): void {
    for (let i = 0; i < this.days.length; i++) {
      this.days.at(i).reset({ working: false, start_time: '09:00', end_time: '17:00' });
    }
  }

  private onProviderChange(id: string): void {
    this.savedOk.set(false);
    this.saveErr.set(null);
    if (!id) { this.resetWeek(); return; }

    this.scheduleLoading.set(true);
    this.api.getSchedule(id).subscribe({
      next: (res) => {
        this.resetWeek();
        for (const row of res.schedule) {
          this.days.at(row.day_of_week).reset({
            working:    true,
            start_time: row.start_time.slice(0, 5), // HH:MM:SS → HH:MM
            end_time:   row.end_time.slice(0, 5),
          });
        }
        this.scheduleLoading.set(false);
      },
      error: (e: ApiError) => {
        this.scheduleLoading.set(false);
        if (e.status === 401) { this.unauthorized.emit(); return; }
        this.listErr.set(e.message);
      },
    });
  }

  save(): void {
    if (this.saving()) return;
    this.saveErr.set(null);
    this.savedOk.set(false);

    const working = this.days.controls
      .map((g, i) => ({ day_of_week: i, ...g.getRawValue() }))
      .filter((r) => r.working);

    // Pre-flight: each working row must have end_time strictly > start_time.
    // The backend enforces this too via CHECK (end_time > start_time), but
    // failing client-side gives a much friendlier message than a 422.
    for (const r of working) {
      if (r.end_time <= r.start_time) {
        this.saveErr.set(`${DAY_LABELS[r.day_of_week]}: end time must be after start time.`);
        return;
      }
    }

    const rows = working.map(({ day_of_week, start_time, end_time }) => ({
      day_of_week, start_time, end_time,
    }));

    this.saving.set(true);
    this.api.saveSchedule(this.providerCtrl.value, rows).subscribe({
      next: (res) => {
        this.resetWeek();
        for (const row of res.schedule) {
          this.days.at(row.day_of_week).reset({
            working:    true,
            start_time: row.start_time.slice(0, 5),
            end_time:   row.end_time.slice(0, 5),
          });
        }
        this.saving.set(false);
        this.savedOk.set(true);
        this.toast.success('Schedule saved.');
      },
      error: (e: ApiError) => {
        this.saving.set(false);
        if (e.status === 401) { this.unauthorized.emit(); return; }
        this.saveErr.set(e.message);
      },
    });
  }
}
