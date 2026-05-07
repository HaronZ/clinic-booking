import { Injectable, computed, inject, signal } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpHeaders } from '@angular/common/http';
import { Observable, map, throwError, catchError, tap } from 'rxjs';

import { LoginResponse, StaffInfo } from '../models/staff.model';

const TOKEN_KEY = 'clinic_jwt';
const STAFF_KEY = 'clinic_staff';

interface SuccessEnvelope<T> {
  data: T;
  meta: Record<string, unknown>;
}
interface ErrorEnvelope {
  error: { code: string; message: string };
}

/**
 * Manages JWT authentication.
 * - Persists token + staff info in localStorage across reloads.
 * - Exposes isLoggedIn / staff / token as signals for reactive components.
 * - Does NOT inject ApiService — uses HttpClient directly to avoid circular DI.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  private readonly _token = signal<string | null>(localStorage.getItem(TOKEN_KEY));
  private readonly _staff = signal<StaffInfo | null>(this.loadStaff());

  readonly isLoggedIn = computed(() => this._token() !== null);
  readonly staff      = this._staff.asReadonly();
  readonly token      = this._token.asReadonly();

  login(username: string, password: string): Observable<StaffInfo> {
    return this.http
      .post<SuccessEnvelope<LoginResponse>>('/api/auth/login', { username, password })
      .pipe(
        map((env) => env.data),
        tap((res) => {
          localStorage.setItem(TOKEN_KEY, res.token);
          localStorage.setItem(STAFF_KEY, JSON.stringify(res.staff));
          this._token.set(res.token);
          this._staff.set(res.staff);
        }),
        map((res) => res.staff),
        catchError((err: HttpErrorResponse) => {
          const body = err.error as ErrorEnvelope | null;
          return throwError(() => ({
            status: err.status,
            code:    body?.error?.code    ?? 'NETWORK_ERROR',
            message: body?.error?.message ?? err.message,
          }));
        }),
      );
  }

  /**
   * Exchange current + new password. On success updates the stored token so
   * the frontend immediately reflects must_change_password = false.
   */
  changePassword(currentPassword: string, newPassword: string): Observable<StaffInfo> {
    const token   = this._token();
    const headers = new HttpHeaders(
      token ? { Authorization: `Bearer ${token}` } : {},
    );

    return this.http
      .post<SuccessEnvelope<LoginResponse>>(
        '/api/auth/change-password',
        { current_password: currentPassword, new_password: newPassword },
        { headers },
      )
      .pipe(
        map((env) => env.data),
        tap((res) => this.refreshToken(res.token, res.staff)),
        map((res) => res.staff),
        catchError((err: HttpErrorResponse) => {
          const body = err.error as ErrorEnvelope | null;
          return throwError(() => ({
            status: err.status,
            code:    body?.error?.code    ?? 'NETWORK_ERROR',
            message: body?.error?.message ?? err.message,
          }));
        }),
      );
  }

  /** Swap stored token + staff (e.g. after password change). */
  refreshToken(token: string, staff: StaffInfo): void {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(STAFF_KEY, JSON.stringify(staff));
    this._token.set(token);
    this._staff.set(staff);
  }

  logout(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(STAFF_KEY);
    this._token.set(null);
    this._staff.set(null);
  }

  private loadStaff(): StaffInfo | null {
    try {
      const raw = localStorage.getItem(STAFF_KEY);
      return raw ? (JSON.parse(raw) as StaffInfo) : null;
    } catch {
      return null;
    }
  }
}
