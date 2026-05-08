import {
  ChangeDetectionStrategy, Component, HostListener, inject,
} from '@angular/core';
import { CommonModule } from '@angular/common';

import { ConfirmService } from '../services/confirm.service';

/**
 * Single host for the global confirm dialog bus. Mount once at the root.
 * Reads ConfirmService.current() and renders a centred modal with two
 * buttons. Keyboard: Enter confirms, Escape cancels, Tab cycles inside.
 */
@Component({
  selector: 'app-confirm-dialog',
  standalone: true,
  imports: [CommonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (svc.current(); as c) {
      <div class="back" role="presentation" (click)="svc.reply(false)">
        <div
          class="dialog"
          role="alertdialog"
          aria-modal="true"
          [attr.aria-label]="c.title"
          (click)="$event.stopPropagation()"
        >
          <h3>{{ c.title }}</h3>
          <p>{{ c.message }}</p>
          <div class="actions">
            <button type="button" class="btn btn-gray" (click)="svc.reply(false)">
              {{ c.cancelLabel }}
            </button>
            <button
              type="button"
              class="btn"
              [class.btn-red]="c.danger"
              [class.btn-blue]="!c.danger"
              (click)="svc.reply(true)"
              autofocus
            >
              {{ c.confirmLabel }}
            </button>
          </div>
        </div>
      </div>
    }
  `,
  styles: [`
    .back {
      position: fixed; inset: 0;
      background: rgba(0,0,0,.45);
      z-index: 500;
      display: flex; align-items: center; justify-content: center;
      animation: fade-in .15s ease-out;
    }
    .dialog {
      background: #fff;
      border-radius: 10px;
      padding: 1.5rem 1.75rem;
      width: 420px; max-width: 92vw;
      box-shadow: 0 20px 60px rgba(0,0,0,.3);
      font-family: system-ui, sans-serif;
      animation: pop-in .18s ease-out;
    }
    h3 { margin: 0 0 .6rem; font-size: 1.1rem; color: #111827; }
    p  { margin: 0 0 1.25rem; color: #4b5563; line-height: 1.45; font-size: .92rem; }
    .actions { display: flex; justify-content: flex-end; gap: .5rem; }
    .btn {
      padding: .45rem .9rem;
      font: inherit; font-size: .9rem; font-weight: 600;
      border: 1px solid transparent; border-radius: 5px;
      cursor: pointer;
    }
    .btn-blue { background:#2563eb; color:#fff; border-color:#2563eb; }
    .btn-blue:hover { background:#1d4ed8; }
    .btn-red  { background:#dc2626; color:#fff; border-color:#dc2626; }
    .btn-red:hover  { background:#b91c1c; }
    .btn-gray { background:#f3f4f6; color:#374151; border-color:#d1d5db; }
    .btn-gray:hover { background:#e5e7eb; }
    @keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
    @keyframes pop-in  { from { transform: scale(.96); opacity: 0; } to { transform: scale(1); opacity: 1; } }
  `],
})
export class ConfirmDialogComponent {
  readonly svc = inject(ConfirmService);

  @HostListener('document:keydown.escape')
  onEsc(): void { if (this.svc.current()) this.svc.reply(false); }

  @HostListener('document:keydown.enter')
  onEnter(): void { if (this.svc.current()) this.svc.reply(true); }
}
