import { Injectable, inject, signal } from '@angular/core';
import { Observable, catchError, map, of, switchMap, tap, throwError } from 'rxjs';
import { ApiService } from './api.service';

export interface AuthUser {
  id: number;
  fullName: string;
  email: string;
}

interface AuthResponse {
  user: AuthUser;
  csrfToken: string;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly api = inject(ApiService);
  readonly user = signal<AuthUser | null>(null);

  currentUser(): Observable<AuthUser | null> {
    if (this.user()) return of(this.user());
    return this.api.get<AuthResponse>('auth/me').pipe(
      tap((response) => {
        this.user.set(response.user);
        this.api.setCsrfToken(response.csrfToken);
      }),
      map((response) => response.user),
      catchError((error: unknown) => {
        if (isUnauthorized(error)) {
          this.user.set(null);
          this.api.clearCsrfToken();
          return of(null);
        }
        return throwError(() => error);
      }),
    );
  }

  login(email: string, password: string): Observable<AuthUser> {
    return this.api.post<AuthResponse>('auth/login', { email, password }).pipe(
      tap((response) => this.setSession(response)),
      map((response) => response.user),
    );
  }

  register(fullName: string, email: string, password: string): Observable<AuthUser> {
    return this.api.post<AuthResponse>('auth/register', { fullName, email, password }).pipe(
      tap((response) => this.setSession(response)),
      map((response) => response.user),
    );
  }

  logout(): Observable<void> {
    return this.api.post<{ success: boolean }>('auth/logout', {}).pipe(
      tap(() => {
        this.user.set(null);
        this.api.clearCsrfToken();
      }),
      map(() => undefined),
    );
  }

  private setSession(response: AuthResponse): void {
    this.user.set(response.user);
    this.api.setCsrfToken(response.csrfToken);
  }
}

function isUnauthorized(error: unknown): boolean {
  return typeof error === 'object' && error !== null && 'status' in error && error.status === 401;
}
