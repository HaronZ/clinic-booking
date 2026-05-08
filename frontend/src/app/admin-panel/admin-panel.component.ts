import {
  ChangeDetectionStrategy, Component, EventEmitter,
  Output, inject, signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';

import { AuthService }              from '../services/auth.service';
import { ProvidersSectionComponent } from './sections/providers-section.component';
import { TypesSectionComponent }     from './sections/types-section.component';
import { SchedulesSectionComponent } from './sections/schedules-section.component';
import { StaffSectionComponent }     from './sections/staff-section.component';

type Tab = 'providers' | 'types' | 'schedules' | 'staff';

@Component({
  selector: 'app-admin-panel',
  standalone: true,
  imports: [
    CommonModule,
    ProvidersSectionComponent,
    TypesSectionComponent,
    SchedulesSectionComponent,
    StaffSectionComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  styles: [`
    :host { display:block; min-height:100vh; background:#f3f4f6; font-family:system-ui,-apple-system,sans-serif; color:#1f2937; }

    /* ── Topbar ── */
    .topbar { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.75rem; background:#2563eb; color:#fff; padding:.85rem 1.5rem; }
    .topbar__brand { display:flex; align-items:center; gap:.5rem; font-size:1.1rem; font-weight:700; }
    .topbar__logo { font-size:1.4rem; }
    .topbar__user { display:flex; align-items:center; gap:.75rem; font-size:.9rem; }
    .badge-role { background:rgba(255,255,255,.22); color:#fff; padding:.15rem .5rem; border-radius:999px; font-size:.75rem; font-weight:700; }
    .btn-topbar { background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.35); color:#fff; padding:.3rem .75rem; border-radius:4px; font:inherit; cursor:pointer; font-size:.85rem; }
    .btn-topbar:hover { background:rgba(255,255,255,.28); }

    /* ── Tab bar ── */
    .tabs { display:flex; gap:0; background:#fff; border-bottom:1px solid #e5e7eb; padding:0 1.5rem; overflow-x:auto; }
    .tab-btn { padding:.75rem 1.1rem; font:inherit; font-size:.9rem; font-weight:600; color:#6b7280; background:none; border:none; border-bottom:3px solid transparent; cursor:pointer; white-space:nowrap; transition:color .15s; }
    .tab-btn:hover { color:#374151; }
    .tab-btn.active { color:#2563eb; border-bottom-color:#2563eb; }

    /* ── Dashboard link ── */
    .back-link { display:inline-block; margin:.75rem 1.5rem 0; font-size:.83rem; color:#6b7280; text-decoration:none; cursor:pointer; background:none; border:none; font:inherit; padding:0; }
    .back-link:hover { color:#374151; text-decoration:underline; }
  `],
  template: `
    <!-- Top bar -->
    <header class="topbar">
      <div class="topbar__brand">
        <span class="topbar__logo">🏥</span>
        <span>Clinic Admin</span>
      </div>
      @if (staff(); as s) {
        <div class="topbar__user">
          <span>{{ s.name }}</span>
          <span class="badge-role">{{ s.role }}</span>
          <button class="btn-topbar" (click)="goDashboard()">Staff dashboard</button>
          <button class="btn-topbar" (click)="logout()">Sign out</button>
        </div>
      }
    </header>

    <!-- Tab navigation -->
    <nav class="tabs">
      <button class="tab-btn" [class.active]="tab()==='providers'"  (click)="tab.set('providers')">Providers</button>
      <button class="tab-btn" [class.active]="tab()==='types'"      (click)="tab.set('types')">Appointment Types</button>
      <button class="tab-btn" [class.active]="tab()==='schedules'"  (click)="tab.set('schedules')">Schedules</button>
      <button class="tab-btn" [class.active]="tab()==='staff'"      (click)="tab.set('staff')">Staff Accounts</button>
    </nav>

    <!-- Active section. Each section emits (unauthorized) on a 401 so the
         shell can log the user out cleanly instead of leaving them stuck on
         a dead admin page. -->
    @if (tab() === 'providers')  { <app-providers-section  (unauthorized)="onUnauthorized()" /> }
    @if (tab() === 'types')      { <app-types-section      (unauthorized)="onUnauthorized()" /> }
    @if (tab() === 'schedules')  { <app-schedules-section  (unauthorized)="onUnauthorized()" /> }
    @if (tab() === 'staff')      { <app-staff-section      (unauthorized)="onUnauthorized()" /> }
  `,
})
export class AdminPanelComponent {
  private readonly auth = inject(AuthService);

  @Output() loggedOut   = new EventEmitter<void>();
  @Output() goDashboard_ = new EventEmitter<void>();

  readonly staff = this.auth.staff;
  readonly tab   = signal<Tab>('providers');

  goDashboard(): void { this.goDashboard_.emit(); }

  logout(): void {
    this.auth.logout();
    this.loggedOut.emit();
  }

  /** Bubbled up from a section when the API returns 401 mid-action. */
  onUnauthorized(): void { this.logout(); }
}
