export type BookingStatus = 'pending' | 'confirmed' | 'completed' | 'cancelled';

/** Returned by POST /api/bookings — no PII fields. */
export interface Booking {
  id: string;
  provider_id: string;
  appointment_type_id: string;
  start_time: string;
  end_time: string;
  status: BookingStatus;
  created_at: string;
}

/** Returned by GET /api/bookings/{id} — for the confirmation page. */
export interface BookingConfirmation {
  id: string;
  start_time: string;
  end_time: string;
  status: BookingStatus;
  provider: {
    id: string;
    name: string;
    specialty: string;
  };
  appointment_type: {
    id: string;
    name: string;
    duration_minutes: number;
  };
}

/** Body shape for POST /api/bookings. */
export interface CreateBookingRequest {
  provider_id: string;
  appointment_type_id: string;
  start_time: string; // 'YYYY-MM-DDTHH:MM:SS'
  patient_name: string;
  patient_phone: string;
  patient_email?: string;
  patient_notes?: string;
}
