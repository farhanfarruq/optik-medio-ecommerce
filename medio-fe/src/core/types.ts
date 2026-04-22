export interface Product {
  id: number;
  category_id: number;
  name: string;
  slug: string;
  description: string;
  brand: string;
  price: number;
  stock: number;
  weight: number;
  dimensions: any;
  variants: any;
  images: string[];
  is_active: boolean;
  is_prescription_required: boolean;
  image_url?: string;
  brand_name?: string;
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
