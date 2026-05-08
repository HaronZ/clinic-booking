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

    /* ── Setup checklist banner ── */
    .setup-banner { margin:1rem 1.5rem 0; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:.875rem 1.125rem; }
    .setup-banner__top { display:flex; justify-content:space-between; align-items:center; margin-bottom:.625rem; }
    .setup-banner__title { margin:0; font-size:.875rem; font-weight:700; color:#1e40af; }
    .setup-steps { display:flex; flex-wrap:wrap; align-items:center; gap:.375rem; }
    .setup-step { display:flex; align-items:center; gap:.35rem; padding:.3rem .75rem; border-radius:20px; font-size:.8rem; font-weight:600; border:1px solid #93c5fd; background:#fff; color:#1d4ed8; cursor:pointer; transition:background .12s; white-space:nowrap; }
    .setup-step:hover { background:#dbeafe; }
    .setup-step.visited { background:#d1fae5; border-color:#6ee7b7; color:#065f46; cursor:default; }
    .step-num { display:inline-flex; align-items:center; justify-content:center; width:1rem; height:1rem; background:#1d4ed8; color:#fff; border-radius:50%; font-size:.62rem; font-weight:700; flex-shrink:0; }
    .setup-step.visited .step-num { background:#059669; }
    .step-arrow { color:#9ca3af; font-size:.8rem; user-select:none; }
    .btn-dismiss-setup { background:none; border:none; color:#93c5fd; font-size:.78rem; cursor:pointer; padding:.2rem .5rem; border-radius:3px; font-weight:600; }
    .btn-dismiss-setup:hover { background:#dbeafe; color:#1e40af; }

    /* ── Tab description subtitle ── */
    .tab-desc { padding:.4rem 1.5rem; background:#fff; border-bottom:1px solid #e5e7eb; }
    .tab-desc p { margin:0; font-size:.82rem; color:#6b7280; }
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
      <button class="tab-btn" [class.active]="tab()==='providers'"  (click)="selectTab('providers')">Providers</button>
      <button class="tab-btn" [class.active]="tab()==='types'"      (click)="selectTab('types')">Appointment Types</button>
      <button class="tab-btn" [class.active]="tab()==='schedules'"  (click)="selectTab('schedules')">Schedules</button>
      <button class="tab-btn" [class.active]="tab()==='staff'"      (click)="selectTab('staff')">Staff Accounts</button>
    </nav>

    <!-- Setup checklist — shown until dismissed via localStorage -->
    @if (!setupDismissed()) {
      <div class="setup-banner">
        <div class="setup-banner__top">
          <p class="setup-banner__title">🚀 Getting started — work through each step in order</p>
          <button class="btn-dismiss-setup" (click)="dismissSetup()" title="Dismiss this guide">Got it ✕</button>
        </div>
        <div class="setup-steps">
          <button class="setup-step" [class.visited]="visited().has('providers')"  (click)="selectTab('providers')">
            <span class="step-num">1</span> Providers @if (visited().has('providers')) { ✓ }
          </button>
          <span class="step-arrow">→</span>
          <button class="setup-step" [class.visited]="visited().has('types')"      (click)="selectTab('types')">
            <span class="step-num">2</span> Appointment Types @if (visited().has('types')) { ✓ }
          </button>
          <span class="step-arrow">→</span>
          <button class="setup-step" [class.visited]="visited().has('schedules')"  (click)="selectTab('schedules')">
            <span class="step-num">3</span> Schedules @if (visited().has('schedules')) { ✓ }
          </button>
          <span class="step-arrow">→</span>
          <button class="setup-step" [class.visited]="visited().has('staff')"      (click)="selectTab('staff')">
            <span class="step-num">4</span> Staff Accounts @if (visited().has('staff')) { ✓ }
          </button>
        </div>
      </div>
    }

    <!-- Tab description subtitle -->
    <div class="tab-desc">
      @if (tab()==='providers')  { <p>Doctors and practitioners that patients can book appointments with</p> }
      @if (tab()==='types')      { <p>Types of visits patients can choose from when booking</p> }
      @if (tab()==='schedules')  { <p>Working hours for each provider — sets when they're available for bookings</p> }
      @if (tab()==='staff')      { <p>Login accounts for your clinic's receptionists and doctors</p> }
    </div>

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

  @Output() loggedOut    = new EventEmitter<void>();
  @Output() goDashboard_ = new EventEmitter<void>();

  readonly staff = this.auth.staff;
  readonly tab   = signal<Tab>('providers');

  /** Which tabs have been explicitly clicked — drives the setup checklist. */
  readonly visited = signal<Set<Tab>>(new Set<Tab>());

  /** True once the user has dismissed the checklist (persisted in localStorage). */
  readonly setupDismissed = signal(
    typeof localStorage !== 'undefined' && localStorage.getItem('clinic_setup_done') === '1',
  );

  /** Switch tab and mark it visited in the setup checklist. */
  selectTab(t: Tab): void {
    this.tab.set(t);
    this.visited.update(v => { v.add(t); return new Set(v); });
  }

  dismissSetup(): void {
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('clinic_setup_done', '1');
    }
    this.setupDismissed.set(true);
  }

  goDashboard(): void { this.goDashboard_.emit(); }

  logout(): void {
    this.auth.logout();
    this.loggedOut.emit();
  }

  /** Bubbled up from a section when the API returns 401 mid-action. */
  onUnauthorized(): void { this.logout(); }
}
