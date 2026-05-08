import { apiClient } from '../core/api/axiosclient';
import type { Product } from '../types';

export interface Category {
  id: number;
  name: string;
  slug: string;
  description?: string;
  products_count?: number;
}

export interface IProductRepository {
  getProducts(filters?: any): Promise<any>;
  getProductBySlug(slug: string): Promise<Product>;
  getCategories(): Promise<Category[]>;
  getBrands(): Promise<string[]>;
}

class ProductRepository implements IProductRepository {
  async getProducts(filters: any = {}): Promise<any> {
    const { data } = await apiClient.get('/products', { params: { per_page: 24, ...filters } });
    return data;
  }

  async getProductBySlug(slug: string): Promise<Product> {
    const { data } = await apiClient.get(`/products/${slug}`);
    return data.data || data;
  }

  async getCategories(): Promise<Category[]> {
    const { data } = await apiClient.get('/categories');
    return data;
  }

  async getBrands(): Promise<string[]> {
    const { data } = await apiClient.get('/products/brands');
    return data;
  }
}

export const productRepository = new ProductRepository();