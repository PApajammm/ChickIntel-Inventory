import { Injectable, inject, signal } from '@angular/core';
import { EMPTY, Observable, Subscription, catchError, exhaustMap, filter, forkJoin, map, tap, timer } from 'rxjs';
import { ApiService, apiErrorMessage } from '../core/api.service';
import { InventoryHistoryEntry, InventoryItem, InventoryItemInput } from './inventory.models';

@Injectable({ providedIn: 'root' })
export class InventoryService {
  private readonly api = inject(ApiService);
  private syncSubscription: Subscription | null = null;
  private hasLoaded = false;
  readonly items = signal<InventoryItem[]>([]);
  readonly history = signal<InventoryHistoryEntry[]>([]);
  readonly loading = signal(true);
  readonly loadError = signal('');
  readonly syncError = signal('');
  readonly lastSyncedAt = signal<Date | null>(null);

  loadHistory(): Observable<void> {
    return this.api.get<InventoryHistoryEntry[]>('history').pipe(
      tap((entries) => this.history.set(entries)),
      map(() => undefined),
    );
  }

  addItem(input: InventoryItemInput): Observable<InventoryItem> {
    return this.api.post<InventoryItem>('inventory', input).pipe(
      tap((item) => {
        this.items.update((items) => [item, ...items]);
        this.refreshHistory();
      }),
    );
  }

  updateItem(id: number, input: InventoryItemInput): Observable<InventoryItem> {
    return this.api.patch<InventoryItem>(`inventory/${id}`, input).pipe(
      tap((updated) => {
        this.items.update((items) => items.map((item) => item.id === id ? updated : item));
        this.refreshHistory();
      }),
    );
  }

  restockItem(id: number, quantity: number, notes?: string): Observable<InventoryItem> {
    return this.api.post<InventoryItem>(`inventory/${id}/restock`, { quantity, notes }).pipe(
      tap((updated) => {
        this.items.update((items) => items.map((item) => item.id === id ? updated : item));
        this.refreshHistory();
      }),
    );
  }

  deleteItem(id: number): Observable<void> {
    return this.api.delete<{ success: boolean }>(`inventory/${id}`).pipe(
      tap(() => {
        this.items.update((items) => items.filter((item) => item.id !== id));
        this.refreshHistory();
      }),
      map(() => undefined),
    );
  }

  startSync(): void {
    if (this.syncSubscription) return;

    this.syncSubscription = timer(0, 5000)
      .pipe(
        filter(() => !document.hidden),
        exhaustMap(() => this.refreshData()),
      )
      .subscribe();
  }

  stopSync(): void {
    this.syncSubscription?.unsubscribe();
    this.syncSubscription = null;
  }

  private refreshData(): Observable<void> {
    if (!this.hasLoaded) this.loading.set(true);

    return forkJoin({
      items: this.api.get<InventoryItem[]>('inventory'),
      history: this.api.get<InventoryHistoryEntry[]>('history'),
    }).pipe(
      tap(({ items, history }) => {
        this.items.set(items);
        this.history.set(history);
        this.hasLoaded = true;
        this.loading.set(false);
        this.syncError.set('');
        this.lastSyncedAt.set(new Date());
      }),
      map(() => undefined),
      catchError((error: unknown) => {
        this.loading.set(false);
        this.syncError.set(apiErrorMessage(error, 'Live updates are temporarily unavailable.'));
        return EMPTY;
      }),
    );
  }

  private refreshHistory(): void {
    this.loadHistory().subscribe({
      error: () => this.loadError.set('The inventory changed, but its history could not be refreshed. Reload the page to retry.'),
    });
  }
}
