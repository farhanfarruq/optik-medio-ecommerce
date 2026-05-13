import { apiClient } from '../core/api/axiosclient';
import type { Product } from '../types';

export interface Category {
  id: number;
  name: string;
  slug: string;
  description?: string;
  products_count?: number;
}

export interface ProductFilters {
  categories: Category[];
  brands: string[];
  genders: string[];
  frame_shapes: string[];
  frame_materials: string[];
  frame_colors: string[];
  face_size_fits: string[];
  price_range: {
    min: number;
    max: number;
  };
  flags: {
    in_stock_only: boolean;
    promo_only: boolean;
    prescription_supported: boolean;
    new_arrivals: boolean;
    featured: boolean;
  };
}

export interface LensOption {
  id: number;
  name: string;
  type: string;
  base_price: number;
  prescription_rules?: Record<string, string | number | boolean> | null;
  is_active: boolean;
}

export interface ProductRecommendations {
  similar_frames: Product[];
  compatible_lenses: Product[];
  compatible_lens_options: LensOption[];
  frequently_bought_together: Product[];
  related_products: Product[];
}

export interface ProductSearchSuggestions {
  products: Product[];
  categories: Category[];
}

export interface ProductCompareResponse {
  products: Product[];
  attributes: string[];
}

export interface IProductRepository {
  getProducts(filters?: any): Promise<any>;
  getProductBySlug(slug: string): Promise<Product>;
  getCategories(): Promise<Category[]>;
  getBrands(): Promise<string[]>;
  getFilters(): Promise<ProductFilters>;
  getRecommendations(slug: string): Promise<ProductRecommendations>;
  getSearchSuggestions(query: string): Promise<ProductSearchSuggestions>;
  compareProducts(productIds: number[]): Promise<ProductCompareResponse>;
  getSharedWishlist(token: string): Promise<{ products: Product[] }>;
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

  async getFilters(): Promise<ProductFilters> {
    const { data } = await apiClient.get('/products/filters');
    return data;
  }

  async getRecommendations(slug: string): Promise<ProductRecommendations> {
    const { data } = await apiClient.get(`/products/${slug}/recommendations`);
    return data;
  }

  async getSearchSuggestions(query: string): Promise<ProductSearchSuggestions> {
    const { data } = await apiClient.get('/products/search-suggestions', { params: { q: query } });
    return data;
  }

  async compareProducts(productIds: number[]): Promise<ProductCompareResponse> {
    const { data } = await apiClient.post('/products/compare', { product_ids: productIds });
    return data;
  }

  async getSharedWishlist(token: string): Promise<{ products: Product[] }> {
    const { data } = await apiClient.get(`/wishlist/shared/${encodeURIComponent(token)}`);
    return data;
  }
}

export const productRepository = new ProductRepository();
