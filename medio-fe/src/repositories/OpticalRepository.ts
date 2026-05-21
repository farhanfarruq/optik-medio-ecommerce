import { apiClient } from '../core/api/axiosclient';
import type { LensOption } from './ProductRepository';

export interface LensCoating {
  id: number;
  name: string;
  price: number;
  description?: string | null;
  is_active: boolean;
}

export interface OpticalQuote {
  compatible: boolean;
  warnings: string[];
  price_breakdown: {
    frame_price: number;
    lens_price: number;
    coating_price: number;
    total: number;
  };
  configuration_snapshot: Record<string, any>;
}

export interface ConfigurePayload {
  frame_product_id: number;
  lens_option_id?: number | null;
  lens_coating_id?: number | null;
  prescription_profile_id?: number | null;
  prescription?: Record<string, any> | null;
}

class OpticalRepository {
  async getLensCoatings(): Promise<LensCoating[]> {
    const { data } = await apiClient.get('/optical/lens-coatings');
    return data;
  }

  async configure(payload: ConfigurePayload): Promise<OpticalQuote> {
    const { data } = await apiClient.post('/optical/configure', payload);
    return data;
  }
}

export const opticalRepository = new OpticalRepository();
export type { LensOption };
