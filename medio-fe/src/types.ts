export interface User {
    id: number;
    name: string;
    email: string;
    addresses?: any[];
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

export interface Product {
    id: number;
    name: string;
    slug: string;
    price: number;
    description: string;
    images: string[];
    image_url: string;
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
    is_prescription_required: boolean;
    prescription_rules?: Record<string, string | number | boolean> | null;
    stock: number;
    is_best_seller: boolean;
    is_not_for_sale?: boolean;
    lens_option_id?: number | null;
    lens_coating_id?: number | null;
    prescription_profile_id?: number | null;
    lens_price?: number;
    coating_price?: number;
    configuration_snapshot?: Record<string, any> | null;
    avg_rating?: number | null;
    review_count?: number;
    purchase_count?: number | null;
    variants?: {
        colors: { name: string; hex: string }[];
        sizes: string[];
    };
    buy_promos?: any[];
    discount_promos?: any[];
    buy_promos_many?: any[];
    discount_promos_many?: any[];
}

export interface CartItem extends Product {
    cart_id: string;
    quantity: number;
    variant?: {
        color?: string;
        size?: string;
    };
    prescription?: Prescription | null;
    parent_item_id?: string;
    weight: number;
}

export interface Order {
    id: number;
    order_number: string;
    total_amount: number;
    status: string;
    created_at: string;
    items: any[];
}

export interface Promo {
    id: number;
    name: string;
    description: string;
    type: 'buy_x_get_y' | 'transaction_discount' | 'product_discount';
    buy_product_id?: number;
    buy_quantity?: number;
    get_product_id?: number;
    get_quantity?: number;
    discount_type?: 'percentage' | 'fixed';
    discount_value?: number;
    discount_product_id?: number;
    min_transaction_amount?: number;
    buy_product?: { slug: string };
    discount_product?: { slug: string };
    is_banner_active?: boolean;
    buy_brands?: string[];
    discount_brands?: string[];
    buy_products?: any[];
    discount_products?: any[];
}
