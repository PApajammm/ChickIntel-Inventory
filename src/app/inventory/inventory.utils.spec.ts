import { describe, expect, it } from 'vitest';
import {
  calculateStockPercentage,
  expirationStateForDate,
  parseInventoryDate,
  stockLevelForPercentage,
} from './inventory.utils';

describe('inventory utilities', () => {
  describe('calculateStockPercentage', () => {
    it('returns zero when stock or its reference is zero or below', () => {
      expect(calculateStockPercentage(0, 10)).toBe(0);
      expect(calculateStockPercentage(5, 0)).toBe(0);
      expect(calculateStockPercentage(-1, 10)).toBe(0);
    });

    it('calculates and caps the reference-stock percentage', () => {
      expect(calculateStockPercentage(2.5, 10)).toBe(25);
      expect(calculateStockPercentage(15, 10)).toBe(100);
    });
  });

  describe('stockLevelForPercentage', () => {
    it.each([
      [0, 'Depleted'],
      [1, 'Critical'],
      [25, 'Critical'],
      [26, 'Medium'],
      [50, 'Medium'],
      [51, 'Normal'],
    ] as const)('classifies %i percent as %s', (percentage, expected) => {
      expect(stockLevelForPercentage(percentage)).toBe(expected);
    });
  });

  describe('expirationStateForDate', () => {
    const today = new Date(2026, 9, 20, 15);

    it('treats today and earlier dates as expired', () => {
      expect(expirationStateForDate('2026-10-20', today)).toBe('Expired');
      expect(expirationStateForDate('2026-10-19', today)).toBe('Expired');
    });

    it('flags dates within seven days, including the seventh day', () => {
      expect(expirationStateForDate('2026-10-21', today)).toBe('Expiring soon');
      expect(expirationStateForDate('2026-10-27', today)).toBe('Expiring soon');
    });

    it('does not flag dates beyond seven days or missing dates', () => {
      expect(expirationStateForDate('2026-10-28', today)).toBeNull();
      expect(expirationStateForDate(undefined, today)).toBeNull();
    });
  });

  it('parses date-only inventory values in local calendar time', () => {
    const parsed = parseInventoryDate('2026-10-20');

    expect(parsed.getFullYear()).toBe(2026);
    expect(parsed.getMonth()).toBe(9);
    expect(parsed.getDate()).toBe(20);
  });
});