import { HttpClient, HttpErrorResponse, HttpHeaders, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, finalize, map, of, shareReplay, switchMap, tap } from 'rxjs';
import { environment } from '../../environments/environment';

interface ApiEnvelope<T> {
  data: T;
}

interface CsrfResponse {
  csrfToken: string;
}

@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  private csrfToken: string | null = null;
  private csrfRequest: Observable<string> | null = null;

  get<T>(route: string): Observable<T> {
    return this.http
      .get<ApiEnvelope<T>>(this.url(), {
        params: new HttpParams().set('route', route),
        withCredentials: true,
      })
      .pipe(map((response) => response.data));
  }

  post<T>(route: string, body: object): Observable<T> {
    return this.mutate<T>('POST', route, body);
  }

  patch<T>(route: string, body: object): Observable<T> {
    return this.mutate<T>('PATCH', route, body);
  }

  delete<T>(route: string): Observable<T> {
    return this.withCsrfToken().pipe(
      switchMap((token) =>
        this.http.delete<ApiEnvelope<T>>(this.url(), {
          params: new HttpParams().set('route', route),
          headers: new HttpHeaders({ 'X-CSRF-Token': token }),
          withCredentials: true,
        }),
      ),
      map((response) => response.data),
    );
  }

  setCsrfToken(token: string): void {
    this.csrfToken = token;
  }

  clearCsrfToken(): void {
    this.csrfToken = null;
  }

  private mutate<T>(method: 'POST' | 'PATCH', route: string, body: object): Observable<T> {
    return this.withCsrfToken().pipe(
      switchMap((token) =>
        this.http.request<ApiEnvelope<T>>(method, this.url(), {
          params: new HttpParams().set('route', route),
          headers: new HttpHeaders({ 'X-CSRF-Token': token }),
          body,
          withCredentials: true,
        }),
      ),
      map((response) => response.data),
      tap((response) => {
        if (isCsrfResponse(response)) this.setCsrfToken(response.csrfToken);
      }),
    );
  }

  private withCsrfToken(): Observable<string> {
    if (this.csrfToken) return of(this.csrfToken);
    if (this.csrfRequest) return this.csrfRequest;

    this.csrfRequest = this.http
      .get<ApiEnvelope<CsrfResponse>>(this.url(), {
        params: new HttpParams().set('route', 'auth/csrf'),
        withCredentials: true,
      })
      .pipe(
        map((response) => response.data.csrfToken),
        tap((token) => {
          this.setCsrfToken(token);
        }),
        shareReplay({ bufferSize: 1, refCount: false }),
        finalize(() => {
          this.csrfRequest = null;
        }),
      );

    return this.csrfRequest;
  }

  private url(): string {
    return environment.apiUrl;
  }
}

function isCsrfResponse(value: unknown): value is CsrfResponse {
  return typeof value === 'object' && value !== null && 'csrfToken' in value && typeof value.csrfToken === 'string';
}

export function apiErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof HttpErrorResponse) {
    const body: unknown = error.error;
    if (typeof body === 'object' && body !== null && 'error' in body) {
      const apiError: unknown = body.error;
      if (typeof apiError === 'object' && apiError !== null && 'message' in apiError && typeof apiError.message === 'string') {
        return apiError.message;
      }
    }
    if (error.status === 0) {
      return 'Cannot reach the PHP API. Check that Apache and MySQL are running, then retry.';
    }
  }
  return fallback;
}
