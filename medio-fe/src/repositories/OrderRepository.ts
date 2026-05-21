import { apiClient } from '../core/api/axiosclient';

export interface PaginatedOrders {
  data: any[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

class OrderRepository {
  async createOrder(payload: any): Promise<any> {
    const { data } = await apiClient.post('/orders', payload);
    return data;
  }

  /**
   * Ambil pesanan user dengan support pagination
   */
  async getUserOrders(page = 1, perPage = 10): Promise<PaginatedOrders> {
    const { data } = await apiClient.get('/orders', { params: { page, per_page: perPage } });
    // Handle both paginated and non-paginated responses
    if (data.data && data.last_page !== undefined) {
      return data as PaginatedOrders;
    }
    // Fallback jika backend belum paginate
    const items = data.data || data;
    return {
      data: items,
      current_page: 1,
      last_page: 1,
      total: items.length,
      per_page: perPage,
    };
  }

  async getOrderDetails(id: number): Promise<any> {
    const { data } = await apiClient.get(`/orders/${id}`);
    return data.data || data;
  }

  async getTracking(id: number): Promise<any> {
    const { data } = await apiClient.get(`/orders/${id}/tracking`);
    return data.data || data;
  }

  async uploadPaymentProof(id: number, file: File): Promise<any> {
    const formData = new FormData();
    formData.append('payment_proof', file);

    const { data } = await apiClient.post(`/orders/${id}/payment-proof`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    return data.data || data;
  }

  async cancelOrder(id: number): Promise<any> {
    const { data } = await apiClient.post(`/orders/${id}/cancel`);
    return data;
  }

  /**
   * Sync payment status dari Xendit gateway.
   */
  async syncPayment(id: number): Promise<{ message: string; status: string; order: any }> {
    const { data } = await apiClient.post(`/orders/${id}/sync-payment`);
    return data;
  }

  /**
   * Polling ringan untuk cek status pembayaran.
   * Digunakan oleh WaitingPayment page.
   */
  async getPaymentStatus(id: number): Promise<{
    order_id: number;
    order_number: string;
    order_status: string;
    is_payment_verified: boolean;
    paid_at: string | null;
    payment: {
      provider: string;
      status: string;
      payment_type: string | null;
      payment_method: string | null;
      checkout_url: string | null;
      paid_at: string | null;
    } | null;
    should_redirect: boolean;
    is_expired: boolean;
  }> {
    const { data } = await apiClient.get(`/orders/${id}/payment-status`);
    return data;
  }

  /**
   * Konfirmasi penerimaan barang oleh customer.
   * Returns points_earned dan updated order.
   */
  async confirmDelivery(id: number): Promise<{ message: string; points_earned: number; order: any }> {
    const { data } = await apiClient.post(`/orders/${id}/confirm-delivery`);
    return data;
  }
}

export const orderRepository = new OrderRepository();
