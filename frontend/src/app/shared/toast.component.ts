import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';

import { ToastService } from '../services/toast.service';

/**
 * Single host for the global toast bus. Mount once at the root of the app.
 * Reads ToastService.current() and renders a fixed-position banner in the
 * top-right of the viewport. Self-dismisses; user can click to dismiss early.
 */
@Component({
  selector: 'app-toast',
  standalone: true,
  imports: [CommonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (toast.current(); as t) {
      <div
        class="toast"
        [class.toast-error]="t.kind === 'error'"
        [class.toast-success]="t.kind === 'success'"
        [class.toast-info]="t.kind === 'info'"
        role="status"
        aria-live="polite"
        (click)="toast.dismiss()"
      >
        <span class="icon">
          @switch (t.kind) {
            @case ('error')   { ⚠ }
            @case ('success') { ✓ }
            @default          { ℹ }
          }
        </span>
        <span class="text">{{ t.text }}</span>
        <button class="dismiss" type="button" aria-label="Dismiss" (click)="toast.dismiss(); $event.stopPropagation()">×</button>
      </div>
    }
  `,
  styles: [`
    .toast {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 1000;
      display: flex;
      align-items: center;
      gap: .6rem;
      max-width: 420px;
      padding: .7rem .9rem .7rem 1rem;
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(0,0,0,.15);
      font-family: system-ui, sans-serif;
      font-size: .9rem;
      cursor: default;
      animation: slide-in .25s ease-out;
    }
    .toast-error   { background:#fef2f2; border:1px solid #fca5a5; color:#991b1b; }
    .toast-success { background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; }
    .toast-info    { background:#dbeafe; border:1px solid #93c5fd; color:#1e40af; }
    .icon { font-size: 1rem; line-height: 1; flex-shrink: 0; }
    .text { flex: 1; line-height: 1.35; }
    .dismiss {
      background: none;
      border: none;
      font: inherit;
      font-size: 1.2rem;
      line-height: 1;
      color: currentColor;
      opacity: .55;
      cursor: pointer;
      padding: 0 .25rem;
    }
    .dismiss:hover { opacity: 1; }
    @keyframes slide-in {
      from { transform: translateX(100%); opacity: 0; }
      to   { transform: translateX(0);     opacity: 1; }
    }
  `],
})
export class ToastComponent {
  readonly toast = inject(ToastService);
}
