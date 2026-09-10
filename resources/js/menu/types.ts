export interface MenuAddon {
    id: number;
    name: string;
    price_cents: number;
    price_formatted: string;
}

export interface MenuAddonGroup {
    id: number;
    name: string;
    is_required: boolean;
    min_options: number;
    max_options: number;
    addons: MenuAddon[];
}

export interface MenuProduct {
    id: number;
    name: string;
    description: string | null;
    price_cents: number;
    price_formatted: string;
    original_price_formatted: string | null;
    has_discount: boolean;
    image_url: string | null;
    is_featured: boolean;
    addon_groups: MenuAddonGroup[];
}

export interface MenuCategory {
    id: number;
    name: string;
    slug: string;
    products: MenuProduct[];
}

export interface MenuPayload {
    establishment: {
        id: number;
        name: string;
        slug: string;
        is_open: boolean;
        primary_color: string;
        secondary_color: string;
        whatsapp: string;
    };
    categories: MenuCategory[];
}

/** Item no carrinho local. Preço nunca é persistido aqui: só ids. */
export interface CartItem {
    key: string;
    productId: number;
    quantity: number;
    notes: string;
    addonIds: number[];
}

export type DeliveryType = 'pickup' | 'delivery';
