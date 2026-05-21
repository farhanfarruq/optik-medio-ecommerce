export interface Product {
  id: number;
  category_id: number;
  name: string;
  slug: string;
  description: string;
  brand: string;
  gender?: string | null;
  frame_shape?: string | null;
  frame_material?: string | null;
  frame_color?: string | null;
  face_size_fit?: string | null;
  lens_width?: number | null;
  bridge_width?: number | null;
  temple_length?: number | null;
  frame_width?: number | null;
  google_product_category?: string | null;
  gtin?: string | null;
  mpn?: string | null;
  condition?: string | null;
  price: number;
  stock: number;
  weight: number;
  dimensions: any;
  variants: any;
  images: string[];
  is_active: boolean;
  is_prescription_required: boolean;
  prescription_rules?: Record<string, string | number | boolean> | null;
  image_url?: string;
  brand_name?: string;
  buy_promos?: any[];
  discount_promos?: any[];
  buy_promos_many?: any[];
  discount_promos_many?: any[];
}

export interface Prescription {
  od: {
    sph: string;
    cyl: string;
    axis: string;
    add: string;
  };
  os: {
    sph: string;
    cyl: string;
    axis: string;
    add: string;
  };
  pdRight?: string;
  pdLeft?: string;
  pdSingle?: string;
}

export interface CartItem extends Product {
  cart_id: string;
  quantity: number;
  prescription: Prescription | null;
  parent_item_id?: string;
  variant?: any;
}

export interface User {
  id: number;
  name: string;
  email: string;
  loyalty_points: number;
}

export interface Order {
  id: number;
  order_number: string;
  total_amount: number;
  status: string;
  created_at: string;
  items: any[];
}
