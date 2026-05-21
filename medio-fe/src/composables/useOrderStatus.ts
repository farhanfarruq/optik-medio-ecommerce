/**
 * useOrderStatus — composable untuk pure helper terkait status order.
 *
 * P1-9 (Phase 3): pre-extract dari Profile.vue (1.520 LOC) sebagai langkah
 * pertama refactor god component. Pure functions tanpa state — aman untuk
 * di-import dari komponen manapun (Profile, OrderDetail, Tracking, dst).
 *
 * Phase berikutnya:
 * - Extract `orderStatusTabs` reactive state + `filteredOrders` computed
 *   ke composable terpisah `useOrderHistory` yang menerima `orders` ref.
 */

export interface OrderStatusTab {
  label: string;
  value: string;
  statuses: string[];
}

export const ORDER_STATUS_TABS: OrderStatusTab[] = [
  { label: 'Semua', value: 'all', statuses: [] },
  { label: 'Menunggu Bayar', value: 'unpaid', statuses: ['unpaid', 'pending'] },
  { label: 'Diproses', value: 'processing', statuses: ['paid', 'processing'] },
  { label: 'Dikirim', value: 'shipped', statuses: ['shipped'] },
  { label: 'Selesai', value: 'completed', statuses: ['delivered', 'completed'] },
  { label: 'Dibatalkan', value: 'cancelled', statuses: ['cancelled', 'failed', 'expired'] },
];

export function normalizeOrderStatus(status: string | null | undefined): string {
  return String(status || '').toLowerCase();
}

const STATUS_LABELS: Record<string, string> = {
  unpaid: 'Menunggu Pembayaran',
  pending: 'Menunggu Pembayaran',
  paid: 'Diproses',
  processing: 'Diproses',
  shipped: 'Dikirim',
  delivered: 'Terkirim',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
  failed: 'Gagal',
  expired: 'Kadaluarsa',
};

export function orderStatusLabel(status: string | null | undefined): string {
  const normalized = normalizeOrderStatus(status);
  return STATUS_LABELS[normalized] ?? (status || '-');
}

const STATUS_CLASSES: Record<string, string> = {
  unpaid: 'bg-amber-100 text-amber-700',
  pending: 'bg-amber-100 text-amber-700',
  paid: 'bg-blue-100 text-blue-700',
  processing: 'bg-blue-100 text-blue-700',
  shipped: 'bg-indigo-100 text-indigo-700',
  delivered: 'bg-emerald-100 text-emerald-700',
  completed: 'bg-emerald-100 text-emerald-700',
  cancelled: 'bg-rose-100 text-rose-700',
  failed: 'bg-rose-100 text-rose-700',
  expired: 'bg-stone-200 text-stone-700',
};

export function orderStatusClass(status: string | null | undefined): string {
  const normalized = normalizeOrderStatus(status);
  return STATUS_CLASSES[normalized] ?? 'bg-stone-200 text-stone-700';
}
