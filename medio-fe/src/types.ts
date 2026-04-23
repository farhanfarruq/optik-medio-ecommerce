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
    is_prescription_required: boolean;
    stock: number;
    is_best_seller: boolean;
    is_not_for_sale?: boolean;
    variants?: {
        colors: { name: string; hex: string }[];
        sizes: string[];
    };
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
