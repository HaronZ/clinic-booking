import { Injectable, signal } from '@angular/core';

interface PendingConfirm {
  title:   string;
  message: string;
  confirmLabel: string;
  cancelLabel:  string;
  danger: boolean;
  resolve: (ok: boolean) => void;
}

interface AskOptions {
  title?:        string;
  confirmLabel?: string;
  cancelLabel?:  string;
  /** Renders the confirm button red, ESC defaults to "no". */
  danger?:       boolean;
}

/**
 * Promise-based confirm dialog bus. Replaces window.confirm() across the
 * app so destructive actions get a consistent in-app modal with proper
 * keyboard handling (Enter = confirm, Escape = cancel).
 *
 * Mounted exactly once via <app-confirm-dialog> in AppComponent.
 *
 * Usage:
 *   const ok = await this.confirm.ask('Deactivate this user?', { danger: true });
 *   if (!ok) return;
 */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  readonly current = signal<PendingConfirm | null>(null);

  ask(message: string, opts: AskOptions = {}): Promise<boolean> {
    return new Promise<boolean>((resolve) => {
      this.current.set({
        title:        opts.title        ?? 'Are you sure?',
        message,
        confirmLabel: opts.confirmLabel ?? 'Confirm',
        cancelLabel:  opts.cancelLabel  ?? 'Cancel',
        danger:       opts.danger       ?? false,
        resolve,
      });
    });
  }

  reply(ok: boolean): void {
    const c = this.current();
    if (!c) return;
    this.current.set(null);
    c.resolve(ok);
  }
}
