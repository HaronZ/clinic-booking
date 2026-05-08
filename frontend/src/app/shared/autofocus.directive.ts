import { AfterViewInit, Directive, ElementRef, inject } from '@angular/core';

/**
 * Auto-focuses the host element after the view initialises. Drop this
 * directive on the first input of a modal so users can type immediately
 * without reaching for the mouse.
 *
 * Usage: <input formControlName="name" appAutofocus />
 */
@Directive({
  selector: '[appAutofocus]',
  standalone: true,
})
export class AutofocusDirective implements AfterViewInit {
  private readonly el = inject(ElementRef<HTMLElement>);

  ngAfterViewInit(): void {
    // setTimeout 0 lets Angular finish painting the modal before we focus.
    setTimeout(() => {
      const node = this.el.nativeElement;
      if (typeof (node as HTMLInputElement).focus === 'function') {
        (node as HTMLInputElement).focus();
      }
    });
  }
}
