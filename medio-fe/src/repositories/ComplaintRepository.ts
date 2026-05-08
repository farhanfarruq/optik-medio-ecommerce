import { apiClient } from '../core/api/axiosclient';

export interface ComplaintPayload {
  order_id?: number | null;
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

  async createComplaint(payload: ComplaintPayload): Promise<any> {
    const formData = new FormData();
    formData.append('subject', payload.subject);
    formData.append('message', payload.message);

    if (payload.order_id) {
      formData.append('order_id', String(payload.order_id));
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
