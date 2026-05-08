import {
  ChangeDetectionStrategy,
  Component,
  EventEmitter,
  OnInit,
  Output,
  computed,
  inject,
  signal,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';

import { ApiService, ApiError } from '../services/api.service';
import { Provider } from '../models/provider.model';
import { AppointmentType } from '../models/appointment-type.model';
import { Slot } from '../models/slot.model';

type Step = 1 | 2 | 3 | 4 | 5;

/**
 * 5-step booking flow. Single component owns the wizard state via signals.
 * Reactive forms hold the user's choices.
 */
@Component({
  selector: 'app-booking',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './booking.component.html',
  styleUrls: ['./booking.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BookingComponent implements OnInit {
  private readonly api = inject(ApiService);
  private readonly fb = inject(FormBuilder);

  @Output() bookingCreated = new EventEmitter<string>();

  readonly currentStep = signal<Step>(1);

  readonly providers = signal<Provider[]>([]);
  readonly appointmentTypes = signal<AppointmentType[]>([]);
  readonly slots = signal<Slot[]>([]);

  readonly slotsLoading = signal(false);
  readonly slotsError = signal<string | null>(null);

  readonly submitting = signal(false);
  readonly submitError = signal<string | null>(null);

  // Use *local* date, not UTC — `toISOString()` shifts to UTC, which can land
  // on yesterday for users east of GMT (e.g. PH at 1 AM is still UTC yesterday).
  readonly today   = this.formatLocalDate(new Date());
  readonly maxDate = this.formatLocalDate(this.addDays(new Date(), 90));

  /** True when slots loaded but every slot is taken — used for the empty-state copy. */
  readonly allSlotsTaken = computed(() => {
    const list = this.slots();
    return list.length > 0 && list.every((s) => !s.available);
  });

  private formatLocalDate(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  private addDays(d: Date, n: number): Date {
    const out = new Date(d);
    out.setDate(out.getDate() + n);
    return out;
  }

  /** Jump the date picker forward one day and refetch slots. */
  tryNextDay(): void {
    const cur = this.typeDateForm.value.date as string;
    if (!cur) return;
    const next = this.formatLocalDate(this.addDays(new Date(cur), 1));
    if (next > this.maxDate) return;
    this.typeDateForm.patchValue({ date: next });
    this.fetchSlots();
  }

  /** Step 1: provider. */
  readonly providerForm: FormGroup = this.fb.group({
    provider_id: ['', Validators.required],
  });

  /** Step 2: appointment type + date. */
  readonly typeDateForm: FormGroup = this.fb.group({
    appointment_type_id: ['', Validators.required],
    date: ['', Validators.required],
  });

  /** Step 3: slot. */
  readonly slotForm: FormGroup = this.fb.group({
    start_time: ['', Validators.required], // 'HH:mm'
  });

  /** Step 4: patient details. */
  readonly patientForm: FormGroup = this.fb.group({
    patient_name: ['', [Validators.required, Validators.maxLength(160)]],
    patient_phone: ['', [Validators.required, Validators.maxLength(30)]],
    patient_email: ['', [Validators.email, Validators.maxLength(254)]],
    patient_notes: ['', [Validators.maxLength(1000)]],
  });

  ngOnInit(): void {
    this.api.getProviders().subscribe({
      next: (rows) => this.providers.set(rows),
      error: (err: ApiError) =>
        this.submitError.set(`Could not load providers: ${err.message}`),
    });
    this.api.getAppointmentTypes().subscribe({
      next: (rows) => this.appointmentTypes.set(rows),
      error: (err: ApiError) =>
        this.submitError.set(`Could not load appointment types: ${err.message}`),
    });
  }

  // ---- step navigation ----

  goNext(): void {
    const step = this.currentStep();
    if (step === 1 && this.providerForm.valid) {
      this.currentStep.set(2);
      return;
    }
    if (step === 2 && this.typeDateForm.valid) {
      this.currentStep.set(3);
      this.fetchSlots();
      return;
    }
    if (step === 3 && this.slotForm.valid) {
      this.currentStep.set(4);
      return;
    }
    if (step === 4 && this.patientForm.valid) {
      this.currentStep.set(5);
      return;
    }
  }

  goBack(): void {
    const step = this.currentStep();
    if (step > 1) {
      this.currentStep.set((step - 1) as Step);
    }
  }

  // ---- step 3: load slots ----

  private fetchSlots(): void {
    const providerId = this.providerForm.value.provider_id as string;
    const typeId = this.typeDateForm.value.appointment_type_id as string;
    const date = this.typeDateForm.value.date as string;

    this.slotsLoading.set(true);
    this.slotsError.set(null);
    this.slots.set([]);
    this.slotForm.reset({ start_time: '' });

    this.api.getAvailability(providerId, typeId, date).subscribe({
      next: (resp) => {
        this.slots.set(resp.slots);
        this.slotsLoading.set(false);
      },
      error: (err: ApiError) => {
        this.slotsLoading.set(false);
        this.slotsError.set(err.message);
      },
    });
  }

  selectSlot(slot: Slot): void {
    if (!slot.available) return;
    this.slotForm.patchValue({ start_time: slot.start_time });
  }

  // ---- step 5: confirm ----

  confirm(): void {
    const date = this.typeDateForm.value.date as string;
    const time = this.slotForm.value.start_time as string; // 'HH:mm'

    this.submitting.set(true);
    this.submitError.set(null);

    const patientEmail = (this.patientForm.value.patient_email as string)?.trim();
    const patientNotes = (this.patientForm.value.patient_notes as string)?.trim();

    this.api
      .createBooking({
        provider_id: this.providerForm.value.provider_id,
        appointment_type_id: this.typeDateForm.value.appointment_type_id,
        start_time: `${date}T${time}:00`,
        patient_name: this.patientForm.value.patient_name,
        patient_phone: this.patientForm.value.patient_phone,
        ...(patientEmail ? { patient_email: patientEmail } : {}),
        ...(patientNotes ? { patient_notes: patientNotes } : {}),
      })
      .subscribe({
        next: (booking) => {
          this.submitting.set(false);
          this.bookingCreated.emit(booking.id);
        },
        error: (err: ApiError) => {
          this.submitting.set(false);
          if (err.status === 409) {
            this.submitError.set(
              'This time slot was just taken. Please go back and choose another slot.',
            );
            this.currentStep.set(3);
            this.fetchSlots(); // refresh slots so the picker reflects reality
          } else {
            this.submitError.set(err.message);
          }
        },
      });
  }

  // ---- selectors used by template ----

  selectedProviderName(): string {
    const id = this.providerForm.value.provider_id as string;
    return this.providers().find((p) => p.id === id)?.name ?? '';
  }

  selectedTypeName(): string {
    const id = this.typeDateForm.value.appointment_type_id as string;
    return this.appointmentTypes().find((t) => t.id === id)?.name ?? '';
  }

  selectedTypeDuration(): number {
    const id = this.typeDateForm.value.appointment_type_id as string;
    return this.appointmentTypes().find((t) => t.id === id)?.duration_minutes ?? 0;
  }
}
