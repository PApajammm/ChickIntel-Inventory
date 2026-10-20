import { ExpirationState, StockLevel } from './inventory.models';

export function calculateStockPercentage(quantity: number, referenceQuantity: number): number {
  if (quantity <= 0 || referenceQuantity <= 0) return 0;
  return Math.min(100, (quantity / referenceQuantity) * 100);
}

export function stockLevelForPercentage(percentage: number): StockLevel {
  if (percentage <= 0) return 'Depleted';
  if (percentage <= 25) return 'Critical';
  if (percentage <= 50) return 'Medium';
  return 'Normal';
}

export function expirationStateForDate(
  expirationDate: string | undefined,
  today = new Date(),
): ExpirationState {
  if (!expirationDate) return null;

  const expiration = parseInventoryDate(expirationDate);
  const startOfToday = new Date(today);
  startOfToday.setHours(0, 0, 0, 0);
  const daysUntilExpiration = Math.floor(
    (expiration.getTime() - startOfToday.getTime()) / 86_400_000,
  );

  if (daysUntilExpiration <= 0) return 'Expired';
  if (daysUntilExpiration <= 7) return 'Expiring soon';
  return null;
}

export function parseInventoryDate(date: string): Date {
  const [year, month, day] = date.split('-').map(Number);
  return new Date(year, month - 1, day);
}