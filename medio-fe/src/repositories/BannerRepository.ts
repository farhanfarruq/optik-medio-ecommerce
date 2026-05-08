import { apiClient } from '../core/api/axiosclient';

export interface BannerItem {
  id: number;
  title: string;
  subtitle?: string | null;
  image_path?: string | null;
  cta_label?: string | null;
  link_type?: 'product' | 'category' | 'external' | null;
  product?: { id: number; name: string; slug: string } | null;
  category?: { id: number; name: string; slug: string } | null;
  external_url?: string | null;
}

class BannerRepository {
  async getBanners(): Promise<BannerItem[]> {
    const { data } = await apiClient.get('/banners');
    return data.data || [];
  }
}

export const bannerRepository = new BannerRepository();
