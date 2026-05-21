/**
 * useFormatMoney — composable untuk format mata uang Rupiah.
 *
 * P1-9..P1-12 (Phase 3): di-extract karena formatter ini di-duplicate di
 * 6+ god components (Profile, CheckoutView, ProductDetail, Product,
 * OrderDetail, CartView, dst). Centralized di sini agar konsisten.
 */

const formatter = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
});

export function formatMoney(value: number | string | null | undefined): string {
  const num = typeof value === 'number' ? value : Number(value ?? 0);
  if (!Number.isFinite(num)) return formatter.format(0);
  return formatter.format(num);
}

/**
 * Format number tanpa simbol mata uang — useful untuk input field
 * atau cell tabel yang punya kolom currency tersendiri.
 */
const numberFormatter = new Intl.NumberFormat('id-ID', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
});

export function formatNumber(value: number | string | null | undefined): string {
  const num = typeof value === 'number' ? value : Number(value ?? 0);
  if (!Number.isFinite(num)) return '0';
  return numberFormatter.format(num);
}
