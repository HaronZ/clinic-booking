import { HttpClient, HttpErrorResponse, HttpHeaders } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, throwError } from 'rxjs';
import { catchError } from 'rxjs/operators';

import { Provider } from '../models/provider.model';
import { AppointmentType } from '../models/appointment-type.model';
import { AvailabilityResponse } from '../models/slot.model';
import {
  Booking,
  BookingConfirmation,
  CreateBookingRequest,
} from '../models/booking.model';
import { ScheduleResponse } from '../models/staff.model';
import { AuthService } from './auth.service';

interface SuccessEnvelope<T> {
  data: T;
  meta: Record<string, unknown>;
}

interface ErrorEnvelope {
  error: { code: string; message: string };
  meta: Record<string, unknown>;
}

export interface ApiError {
  status: number;
  code: string;
  message: string;
}

/**
 * Single source of HTTP for the app. All methods unwrap the success envelope
 * (`{ data, meta }`) and translate error envelopes into a flat `ApiError`.
 *
 * Components/services NEVER inject HttpClient directly — go through here.
 */
@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private readonly base = '/api';

  getProviders(): Observable<Provider[]> {
    return this.unwrap(
      this.http.get<SuccessEnvelope<Provider[]>>(`${this.base}/providers`),
    );
  }

  getAppointmentTypes(): Observable<AppointmentType[]> {
    return this.unwrap(
      this.http.get<SuccessEnvelope<AppointmentType[]>>(
        `${this.base}/appointment-types`,
      ),
    );
  }

  getAvailability(
    providerId: string,
    typeId: string,
    date: string,
  ): Observable<AvailabilityResponse> {
    const params = new URLSearchParams({
      provider_id: providerId,
      appointment_type_id: typeId,
      date,
    });
    return this.unwrap(
      this.http.get<SuccessEnvelope<AvailabilityResponse>>(
        `${this.base}/availability?${params.toString()}`,
      ),
    );
  }

  createBooking(payload: CreateBookingRequest): Observable<Booking> {
    return this.unwrap(
      this.http.post<SuccessEnvelope<Booking>>(`${this.base}/bookings`, payload),
    );
  }

  getBooking(id: string): Observable<BookingConfirmation> {
    return this.unwrap(
      this.http.get<SuccessEnvelope<BookingConfirmation>>(
        `${this.base}/bookings/${encodeURIComponent(id)}`,
      ),
    );
  }

  // ---- Staff endpoints (require JWT) ----

  getSchedule(date: string): Observable<ScheduleResponse> {
    return this.unwrap(
      this.http.get<SuccessEnvelope<ScheduleResponse>>(
        `${this.base}/staff/schedule?date=${encodeURIComponent(date)}`,
        { headers: this.authHeaders() },
      ),
    );
  }

  updateBookingStatus(id: string, status: string): Observable<{ id: string; status: string }> {
    return this.unwrap(
      this.http.patch<SuccessEnvelope<{ id: string; status: string }>>(
        `${this.base}/bookings/${encodeURIComponent(id)}/status`,
        { status },
        { headers: this.authHeaders() },
      ),
    );
  }

  // ---- internal helpers ----

  private authHeaders(): HttpHeaders {
    const token = this.auth.token();
    return token
      ? new HttpHeaders({ Authorization: `Bearer ${token}` })
      : new HttpHeaders();
  }

  private unwrap<T>(req: Observable<SuccessEnvelope<T>>): Observable<T> {
    return req.pipe(
      map((envelope) => envelope.data),
      catchError((err: HttpErrorResponse) => {
        const body = err.error as ErrorEnvelope | null;
        const apiError: ApiError = {
          status: err.status,
          code: body?.error?.code ?? 'NETWORK_ERROR',
          message: body?.error?.message ?? err.message,
        };
        return throwError(() => apiError);
      }),
    );
  }
}
