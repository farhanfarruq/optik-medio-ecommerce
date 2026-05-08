import { apiClient } from '../core/api/axiosclient';

export interface ReturnReason {
  value: string;
  label: string;
}

export const RETURN_REASONS: ReturnReason[] = [
  { value: 'Produk rusak saat diterima', label: 'Produk rusak saat diterima' },
  { value: 'Produk tidak sesuai deskripsi', label: 'Produk tidak sesuai deskripsi' },
  { value: 'Produk salah ukuran/warna', label: 'Produk salah ukuran/warna' },
  { value: 'Produk tidak berfungsi', label: 'Produk tidak berfungsi' },
  { value: 'Lainnya', label: 'Lainnya' },
];

class ReturnRepository {
  async submitReturn(orderId: number, reason: string, description?: string): Promise<void> {
    await apiClient.post('/returns', { order_id: orderId, reason, description });
  }
}

export const returnRepository = new ReturnRepository();
