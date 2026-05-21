import { describe, it, expect } from 'vitest';
import { formatMoney, formatNumber } from '../../src/composables/useFormatMoney';

describe('useFormatMoney', () => {
  describe('formatMoney', () => {
    it('formats integer to Indonesian Rupiah', () => {
      const result = formatMoney(150000);
      // Output mungkin pakai NBSP atau regular space tergantung Intl impl;
      // assertion fokus ke struktur saja.
      expect(result).toMatch(/^Rp.*150\.000$/);
    });

    it('handles zero', () => {
      expect(formatMoney(0)).toMatch(/^Rp.*0$/);
    });

    it('handles null and undefined as zero', () => {
      expect(formatMoney(null)).toMatch(/^Rp.*0$/);
      expect(formatMoney(undefined)).toMatch(/^Rp.*0$/);
    });

    it('handles string input', () => {
      expect(formatMoney('25000')).toMatch(/^Rp.*25\.000$/);
    });

    it('handles invalid string by falling back to 0', () => {
      expect(formatMoney('not-a-number')).toMatch(/^Rp.*0$/);
    });

    it('rounds decimal numbers (no fraction digits)', () => {
      expect(formatMoney(1234.56)).toMatch(/^Rp.*1\.235$/);
    });

    it('handles large numbers correctly', () => {
      expect(formatMoney(1500000000)).toMatch(/^Rp.*1\.500\.000\.000$/);
    });
  });

  describe('formatNumber', () => {
    it('formats without currency symbol', () => {
      expect(formatNumber(150000)).toBe('150.000');
    });

    it('handles null/undefined as 0', () => {
      expect(formatNumber(null)).toBe('0');
      expect(formatNumber(undefined)).toBe('0');
    });

    it('handles invalid input as 0', () => {
      expect(formatNumber(NaN)).toBe('0');
      expect(formatNumber(Infinity)).toBe('0');
    });
  });
});
