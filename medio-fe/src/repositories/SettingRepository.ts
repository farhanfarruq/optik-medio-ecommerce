import { apiClient } from '../core/api/axiosclient';

export interface AppSettings {
  store_name: string;
  store_address: string;
  store_phone: string;
  store_opening_hours: string;
  store_location_url: string;
  store_testimonials: Testimonial[];
  [key: string]: any;
}

export interface Testimonial {
  name: string;
  review: string;
  rating: number;
}

class SettingRepository {
  async getSettings(): Promise<AppSettings> {
    const { data } = await apiClient.get('/settings');
    return data.data;
  }
}

export const settingRepository = new SettingRepository();
