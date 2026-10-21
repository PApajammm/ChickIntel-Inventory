import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ApiService } from './api.service';

describe('ApiService', () => {
  let api: ApiService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [ApiService, provideHttpClient(), provideHttpClientTesting()],
    });
    api = TestBed.inject(ApiService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('refreshes an expired CSRF token and retries the request once', () => {
    let result: { success: boolean } | undefined;
    api.post<{ success: boolean }>('auth/login', { email: 'user@example.com', password: 'password123' })
      .subscribe((response) => {
        result = response;
      });

    http.expectOne((request) => request.params.get('route') === 'auth/csrf')
      .flush({ data: { csrfToken: 'expired-token' } });

    http.expectOne((request) =>
      request.method === 'POST' && request.params.get('route') === 'auth/login',
    ).flush(
      { error: { message: 'Security token expired. Refresh the page and try again.' } },
      { status: 403, statusText: 'Forbidden' },
    );

    http.expectOne((request) => request.params.get('route') === 'auth/csrf')
      .flush({ data: { csrfToken: 'fresh-token' } });

    http.expectOne((request) =>
      request.method === 'POST'
      && request.params.get('route') === 'auth/login'
      && request.headers.get('X-CSRF-Token') === 'fresh-token',
    ).flush({ data: { success: true } });

    expect(result).toEqual({ success: true });
  });
});
