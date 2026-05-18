import { apiClient } from '../core/api/axiosclient';

export interface ComplaintPayload {
  order_id?: number | null;
  complaint_type?: 'general' | 'shipping_protection';
  subject: string;
  message: string;
  contact_phone?: string;
  attachment?: File | null;
}

class ComplaintRepository {
  async getComplaints(page = 1): Promise<any> {
    const { data } = await apiClient.get('/complaints', { params: { page } });
    return data.data;
  }

  async getComplaint(id: number): Promise<any> {
    const { data } = await apiClient.get(`/complaints/${id}`);
    return data.data;
  }

  async getComplaintByOrder(orderId: number, complaintType?: 'general' | 'shipping_protection'): Promise<any | null> {
    const result = await this.getComplaints(1);
    const items: any[] = result?.data ?? [];
    return items.find((c: any) => c.order_id === orderId && (!complaintType || c.complaint_type === complaintType)) ?? null;
  }

  async createComplaint(payload: ComplaintPayload): Promise<any> {
    const formData = new FormData();
    formData.append('subject', payload.subject);
    formData.append('message', payload.message);

    if (payload.order_id) {
      formData.append('order_id', String(payload.order_id));
    }

    if (payload.complaint_type) {
      formData.append('complaint_type', payload.complaint_type);
    }

    if (payload.contact_phone) {
      formData.append('contact_phone', payload.contact_phone);
    }

    if (payload.attachment) {
      formData.append('attachment', payload.attachment);
    }

    const { data } = await apiClient.post('/complaints', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    return data.data;
  }
}

export const complaintRepository = new ComplaintRepository();
