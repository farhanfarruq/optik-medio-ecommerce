import { describe, it, expect } from 'vitest';
import {
  ORDER_STATUS_TABS,
  normalizeOrderStatus,
  orderStatusLabel,
  orderStatusClass,
} from '../../src/composables/useOrderStatus';

describe('useOrderStatus', () => {
  describe('ORDER_STATUS_TABS', () => {
    it('contains expected tabs', () => {
      const values = ORDER_STATUS_TABS.map((t) => t.value);
      expect(values).toEqual(['all', 'unpaid', 'processing', 'shipped', 'completed', 'cancelled']);
    });

    it('"all" tab has empty statuses array', () => {
      const allTab = ORDER_STATUS_TABS.find((t) => t.value === 'all');
      expect(allTab?.statuses).toEqual([]);
    });

    it('"unpaid" tab includes both unpaid and pending', () => {
      const unpaid = ORDER_STATUS_TABS.find((t) => t.value === 'unpaid');
      expect(unpaid?.statuses).toContain('unpaid');
      expect(unpaid?.statuses).toContain('pending');
    });
  });

  describe('normalizeOrderStatus', () => {
    it('lowercases the status', () => {
      expect(normalizeOrderStatus('PAID')).toBe('paid');
      expect(normalizeOrderStatus('Delivered')).toBe('delivered');
    });

    it('handles null and undefined as empty string', () => {
      expect(normalizeOrderStatus(null)).toBe('');
      expect(normalizeOrderStatus(undefined)).toBe('');
    });
  });

  describe('orderStatusLabel', () => {
    it('returns Indonesian label for known statuses', () => {
      expect(orderStatusLabel('unpaid')).toBe('Menunggu Pembayaran');
      expect(orderStatusLabel('paid')).toBe('Diproses');
      expect(orderStatusLabel('shipped')).toBe('Dikirim');
      expect(orderStatusLabel('delivered')).toBe('Terkirim');
      expect(orderStatusLabel('completed')).toBe('Selesai');
      expect(orderStatusLabel('cancelled')).toBe('Dibatalkan');
    });

    it('handles case-insensitively', () => {
      expect(orderStatusLabel('PAID')).toBe('Diproses');
      expect(orderStatusLabel('Shipped')).toBe('Dikirim');
    });

    it('returns the input as fallback for unknown status', () => {
      expect(orderStatusLabel('weird-status')).toBe('weird-status');
    });

    it('returns "-" for null/undefined', () => {
      expect(orderStatusLabel(null)).toBe('-');
      expect(orderStatusLabel(undefined)).toBe('-');
    });
  });

  describe('orderStatusClass', () => {
    it('returns expected color class for each status', () => {
      expect(orderStatusClass('unpaid')).toContain('amber');
      expect(orderStatusClass('paid')).toContain('blue');
      expect(orderStatusClass('shipped')).toContain('indigo');
      expect(orderStatusClass('delivered')).toContain('emerald');
      expect(orderStatusClass('cancelled')).toContain('rose');
    });

    it('returns neutral fallback for unknown status', () => {
      expect(orderStatusClass('weird')).toContain('stone');
    });
  });
});
