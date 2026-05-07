import {
  ChangeDetectionStrategy, Component, OnInit,
  inject, signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

import { AdminApiService, AdminProvider, ApiError } from '../../services/admin-api.service';

interface DayRow {
  day_of_week: number;
  label: string;
  working: boolean;
  start_time: string;
  end_time: string;
}

const DAY_LABELS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function blankWeek(): DayRow[] {
  return DAY_LABELS.map((label, i) => ({
    day_of_week: i, label, working: false,
    start_time: '09:00', end_time: '17:00',
  }));
}

@Component({
  selector: 'app-schedules-section',
  standalone: true,
  imports: [CommonModule, FormsModule],
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
    .day-off { color:#9ca3af; }
    input[type="time"] { padding:.38rem .5rem; border:1px solid #d1d5db; border-radius:4px; font:inherit; font-size:.88rem; }
    input[type="time"]:focus { outline:none; border-color:#2563eb; }
    input[type="time"]:disabled { background:#f9fafb; color:#9ca3af; }
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
        <select [ngModel]="selectedId()" (ngModelChange)="selectProvider($event)">
          <option value="">— Select provider —</option>
          @for (p of providers(); track p.id) {
            <option [value]="p.id">{{ p.name }}</option>
          }
        </select>
      }
    </div>

    @if (listErr()) { <div class="alert-err">{{ listErr() }}</div> }
    @if (savedOk())  { <div class="alert-ok">Schedule saved successfully.</div> }

    @if (selectedId() && !scheduleLoading()) {
      <div class="schedule-card">
        <table>
          <thead><tr>
            <th>Day</th><th>Working</th><th>Start</th><th>End</th>
          </tr></thead>
          <tbody>
            @for (row of week(); track row.day_of_week) {
              <tr [class.day-off]="!row.working">
                <td><strong>{{ row.label }}</strong></td>
                <td class="check-cell">
                  <input type="checkbox" [(ngModel)]="row.working" />
                </td>
                <td>
                  <input type="time" [(ngModel)]="row.start_time" [disabled]="!row.working" />
                </td>
                <td>
                  <input type="time" [(ngModel)]="row.end_time" [disabled]="!row.working" />
                </td>
              </tr>
            }
          </tbody>
        </table>
        <div class="actions-row">
          <button class="btn btn-blue" [disabled]="saving()" (click)="save()">
            {{ saving() ? 'Saving…' : 'Save Schedule' }}
          </button>
          @if (saveErr()) { <span style="color:#991b1b;font-size:.85rem">{{ saveErr() }}</span> }
        </div>
      </div>
    }

    @if (!selectedId() && !providersLoading()) {
      <div class="empty-msg">Select a provider above to view or edit their schedule.</div>
    }
    @if (selectedId() && scheduleLoading()) {
      <div class="empty-msg">Loading schedule…</div>
    }
  `,
})
export class SchedulesSectionComponent implements OnInit {
  private readonly api = inject(AdminApiService);

  readonly providers       = signal<AdminProvider[]>([]);
  readonly providersLoading = signal(true);
  readonly selectedId      = signal('');
  readonly week            = signal<DayRow[]>(blankWeek());
  readonly scheduleLoading = signal(false);
  readonly saving          = signal(false);
  readonly listErr         = signal<string | null>(null);
  readonly saveErr         = signal<string | null>(null);
  readonly savedOk         = signal(false);

  ngOnInit(): void {
    this.api.getProviders(false).subscribe({
      next:  (list) => { this.providers.set(list); this.providersLoading.set(false); },
      error: (e: ApiError) => { this.listErr.set(e.message); this.providersLoading.set(false); },
    });
  }

  selectProvider(id: string): void {
    this.selectedId.set(id);
    this.savedOk.set(false);
    this.saveErr.set(null);
    if (!id) { this.week.set(blankWeek()); return; }

    this.scheduleLoading.set(true);
    this.api.getSchedule(id).subscribe({
      next: (res) => {
        const w = blankWeek();
        for (const row of res.schedule) {
          const d = w[row.day_of_week];
          d.working    = true;
          d.start_time = row.start_time.slice(0, 5); // HH:MM:SS → HH:MM
          d.end_time   = row.end_time.slice(0, 5);
        }
        this.week.set(w);
        this.scheduleLoading.set(false);
      },
      error: (e: ApiError) => { this.listErr.set(e.message); this.scheduleLoading.set(false); },
    });
  }

  save(): void {
    this.saveErr.set(null);
    this.savedOk.set(false);

    const rows = this.week()
      .filter((r) => r.working)
      .map(({ day_of_week, start_time, end_time }) => ({ day_of_week, start_time, end_time }));

    this.saving.set(true);
    this.api.saveSchedule(this.selectedId(), rows).subscribe({
      next: (res) => {
        // Refresh week from server response to get canonical times.
        const w = blankWeek();
        for (const row of res.schedule) {
          const d = w[row.day_of_week];
          d.working    = true;
          d.start_time = row.start_time.slice(0, 5);
          d.end_time   = row.end_time.slice(0, 5);
        }
        this.week.set(w);
        this.saving.set(false);
        this.savedOk.set(true);
      },
      error: (e: ApiError) => { this.saving.set(false); this.saveErr.set(e.message); },
    });
  }
}
