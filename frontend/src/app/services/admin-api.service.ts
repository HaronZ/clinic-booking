import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpHeaders } from '@angular/common/http';
import { Observable, map, catchError, throwError } from 'rxjs';

import { AuthService } from './auth.service';

// ── Domain types ──────────────────────────────────────────────────────────────

export interface AdminProvider {
  id: string;
  name: string;
  specialty: string;
  slug: string;
  is_active: number;
}

export interface AdminAppointmentType {
  id: string;
  name: string;
  slug: string;
  duration_minutes: number;
  is_active: number;
}

export interface ScheduleRow {
  id?: string;
  day_of_week: number;
  start_time: string;
  end_time: string;
}

export interface AdminStaff {
  id: string;
  username: string;
  name: string;
  role: 'admin' | 'receptionist' | 'doctor';
  provider_id: string | null;
  is_active: number;
  must_change_password: number;
}

export interface ApiError {
  status: number;
  code: string;
  message: string;
}

interface SuccessEnvelope<T> { data: T; meta: Record<string, unknown>; }
interface ErrorEnvelope    { error: { code: string; message: string }; }

/**
 * Wraps every /api/admin/* and /api/auth/* endpoint needed by the admin panel.
 * Mirrors the style of ApiService: unwraps envelopes, normalises errors.
 */
@Injectable({ providedIn: 'root' })
export class AdminApiService {
  private readonly http = inject(HttpClient);
  private readonly auth = inject(AuthService);
  private readonly base = '/api/admin';

  // ── Providers ────────────────────────────────────────────────────────────────

  getProviders(includeInactive = false): Observable<AdminProvider[]> {
    const qs = includeInactive ? '?include_inactive=1' : '';
    return this.unwrap(
      this.http.get<SuccessEnvelope<AdminProvider[]>>(
        `${this.base}/providers${qs}`, { headers: this.h() },
      ),
    );
  }

  createProvider(body: { name: string; specialty: string; slug?: string }): Observable<AdminProvider> {
    return this.unwrap(
      this.http.post<SuccessEnvelope<AdminProvider>>(
        `${this.base}/providers`, body, { headers: this.h() },
      ),
    );
  }

  updateProvider(
    id: string,
    body: Partial<{ name: string; specialty: string; slug: string }>,
  ): Observable<AdminProvider> {
    return this.unwrap(
      this.http.patch<SuccessEnvelope<AdminProvider>>(
        `${this.base}/providers/${enc(id)}`, body, { headers: this.h() },
      ),
    );
  }

  deleteProvider(id: string): Observable<{ id: string; is_active: number }> {
    return this.unwrap(
      this.http.delete<SuccessEnvelope<{ id: string; is_active: number }>>(
        `${this.base}/providers/${enc(id)}`, { headers: this.h() },
      ),
    );
  }

  restoreProvider(id: string): Observable<{ id: string; is_active: number }> {
    return this.unwrap(
      this.http.post<SuccessEnvelope<{ id: string; is_active: number }>>(
        `${this.base}/providers/${enc(id)}/restore`, {}, { headers: this.h() },
      ),
    );
  }

  // ── Appointment types ─────────────────────────────────────────────────────

  getTypes(includeInactive = false): Observable<AdminAppointmentType[]> {
    const qs = includeInactive ? '?include_inactive=1' : '';
    return this.unwrap(
      this.http.get<SuccessEnvelope<AdminAppointmentType[]>>(
        `${this.base}/appointment-types${qs}`, { headers: this.h() },
      ),
    );
  }

  createType(body: { name: string; duration_minutes: number; slug?: string }): Observable<AdminAppointmentType> {
    return this.unwrap(
      this.http.post<SuccessEnvelope<AdminAppointmentType>>(
        `${this.base}/appointment-types`, body, { headers: this.h() },
      ),
    );
  }

  updateType(
    id: string,
    body: Partial<{ name: string; duration_minutes: number; slug: string }>,
  ): Observable<AdminAppointmentType> {
    return this.unwrap(
      this.http.patch<SuccessEnvelope<AdminAppointmentType>>(
        `${this.base}/appointment-types/${enc(id)}`, body, { headers: this.h() },
      ),
    );
  }

  deleteType(id: string): Observable<{ id: string; is_active: number }> {
    return this.unwrap(
      this.http.delete<SuccessEnvelope<{ id: string; is_active: number }>>(
        `${this.base}/appointment-types/${enc(id)}`, { headers: this.h() },
      ),
    );
  }

  // ── Schedules ─────────────────────────────────────────────────────────────

  getSchedule(providerId: string): Observable<{ provider_id: string; schedule: ScheduleRow[] }> {
    return this.unwrap(
      this.http.get<SuccessEnvelope<{ provider_id: string; schedule: ScheduleRow[] }>>(
        `${this.base}/providers/${enc(providerId)}/schedule`, { headers: this.h() },
      ),
    );
  }

  saveSchedule(
    providerId: string,
    rows: Omit<ScheduleRow, 'id'>[],
  ): Observable<{ provider_id: string; schedule: ScheduleRow[] }> {
    return this.unwrap(
      this.http.put<SuccessEnvelope<{ provider_id: string; schedule: ScheduleRow[] }>>(
        `${this.base}/providers/${enc(providerId)}/schedule`,
        { rows },
        { headers: this.h() },
      ),
    );
  }

  // ── Staff ─────────────────────────────────────────────────────────────────

  getStaff(includeInactive = false): Observable<AdminStaff[]> {
    const qs = includeInactive ? '?include_inactive=1' : '';
    return this.unwrap(
      this.http.get<SuccessEnvelope<AdminStaff[]>>(
        `${this.base}/staff${qs}`, { headers: this.h() },
      ),
    );
  }

  createStaff(body: {
    username: string; name: string; password: string;
    role: string; provider_id?: string;
  }): Observable<AdminStaff> {
    return this.unwrap(
      this.http.post<SuccessEnvelope<AdminStaff>>(
        `${this.base}/staff`, body, { headers: this.h() },
      ),
    );
  }

  updateStaff(
    id: string,
    body: Partial<{ username: string; name: string; role: string; provider_id: string | null; password: string }>,
  ): Observable<AdminStaff> {
    return this.unwrap(
      this.http.patch<SuccessEnvelope<AdminStaff>>(
        `${this.base}/staff/${enc(id)}`, body, { headers: this.h() },
      ),
    );
  }

  deleteStaff(id: string): Observable<{ id: string; is_active: number }> {
    return this.unwrap(
      this.http.delete<SuccessEnvelope<{ id: string; is_active: number }>>(
        `${this.base}/staff/${enc(id)}`, { headers: this.h() },
      ),
    );
  }

  // ── helpers ───────────────────────────────────────────────────────────────

  private h(): HttpHeaders {
    const token = this.auth.token();
    return token ? new HttpHeaders({ Authorization: `Bearer ${token}` }) : new HttpHeaders();
  }

  private unwrap<T>(req: Observable<SuccessEnvelope<T>>): Observable<T> {
    return req.pipe(
      map((env) => env.data),
      catchError((err: HttpErrorResponse) => {
        const body = err.error as ErrorEnvelope | null;
        return throwError(() => ({
          status:  err.status,
          code:    body?.error?.code    ?? 'NETWORK_ERROR',
          message: body?.error?.message ?? err.message,
        } as ApiError));
      }),
    );
  }
}

function enc(s: string): string { return encodeURIComponent(s); }
