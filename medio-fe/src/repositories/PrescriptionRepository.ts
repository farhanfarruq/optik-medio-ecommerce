import { apiClient } from '../core/api/axiosclient';

export interface PrescriptionProfile {
  id: number;
  label: string;
  lens_type?: string | null;
  right_sphere?: number | null;
  right_cylinder?: number | null;
  right_axis?: number | null;
  right_add?: number | null;
  left_sphere?: number | null;
  left_cylinder?: number | null;
  left_axis?: number | null;
  left_add?: number | null;
  pd_single?: number | null;
  pd_right?: number | null;
  pd_left?: number | null;
  notes?: string | null;
  admin_notes?: string | null;
  verification_status?: 'pending' | 'approved' | 'rejected' | null;
  attachment_path?: string | null;
  verified_at?: string | null;
  is_default: boolean;
}

export type PrescriptionPayload = Omit<
  PrescriptionProfile,
  'id' | 'attachment_path' | 'verified_at'
>;

class PrescriptionRepository {
  async list(): Promise<PrescriptionProfile[]> {
    const { data } = await apiClient.get('/prescriptions');
    return data;
  }

  async create(payload: PrescriptionPayload | FormData): Promise<PrescriptionProfile> {
    const { data } = await apiClient.post('/prescriptions', payload);
    return data;
  }

  async update(id: number, payload: PrescriptionPayload | FormData): Promise<PrescriptionProfile> {
    const { data } = await apiClient.put(`/prescriptions/${id}`, payload);
    return data;
  }

  async setDefault(id: number): Promise<PrescriptionProfile> {
    const { data } = await apiClient.post(`/prescriptions/${id}/set-default`);
    return data;
  }

  async delete(id: number): Promise<void> {
    await apiClient.delete(`/prescriptions/${id}`);
  }
}

export const prescriptionRepository = new PrescriptionRepository();
