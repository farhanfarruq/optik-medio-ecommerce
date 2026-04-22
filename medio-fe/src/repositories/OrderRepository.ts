import { apiClient } from '../core/api/axiosclient';

class OrderRepository {
  async createOrder(payload: any): Promise<any> {
    const { data } = await apiClient.post('/orders', payload);
    return data;
  }
  
  async getUserOrders(): Promise<any[]> {
    const { data } = await apiClient.get('/orders');
    return data.data || data;
  }

  async getOrderDetails(id: number): Promise<any> {
    const { data } = await apiClient.get(`/orders/${id}`);
    return data.data || data;
  }
}

export const orderRepository = new OrderRepository();