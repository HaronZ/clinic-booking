import { Injectable, signal } from '@angular/core';

type ToastKind = 'error' | 'success' | 'info';
interface Toast { kind: ToastKind; text: string; }

/**
 * Tiny global toast bus. Replaces window.alert() across the app so that
 * errors and success notices appear inline in the page chrome instead of
 * a jarring browser-native dialog the user has to dismiss.
 *
 * Mounted exactly once via <app-toast> in AppComponent. Any service /
 * component can call show() and the host re-renders.
 */
@Injectable({ providedIn: 'root' })
export class ToastService {
  readonly current = signal<Toast | null>(null);
  private timer: ReturnType<typeof setTimeout> | null = null;

  show(text: string, kind: ToastKind = 'info', durationMs = 4000): void {
    if (this.timer) { clearTimeout(this.timer); this.timer = null; }
    this.current.set({ kind, text });
    this.timer = setTimeout(() => this.current.set(null), durationMs);
  }

  error(text: string):   void { this.show(text, 'error',   5000); }
  success(text: string): void { this.show(text, 'success', 3000); }
  dismiss(): void {
    if (this.timer) { clearTimeout(this.timer); this.timer = null; }
    this.current.set(null);
  }
}
