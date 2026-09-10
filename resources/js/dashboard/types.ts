export interface User {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    email_verified: boolean;
    created_at: string | null;
}

export interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_cents: number;
    price_formatted: string;
    currency: string;
    billing_period: string;
    billing_period_label: string;
    is_free: boolean;
    max_products: number | null;
    max_categories: number | null;
    max_establishments: number | null;
    features: string[];
    is_active: boolean;
    is_default: boolean;
    sort_order: number;
}

export interface Subscription {
    id: number;
    status: string;
    status_label: string;
    grants_access: boolean;
    is_on_trial: boolean;
    trial_ends_at: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    renews_at: string | null;
    canceled_at: string | null;
    plan?: Plan;
}

export interface BusinessHour {
    weekday: number;
    weekday_label: string;
    is_closed: boolean;
    opens_at: string | null;
    closes_at: string | null;
}

export interface Establishment {
    id: number;
    name: string;
    legal_name: string | null;
    slug: string;
    segment: string;
    segment_label: string;
    description: string | null;
    logo_url: string | null;
    cover_url: string | null;
    phone: string | null;
    whatsapp: string;
    instagram: string | null;
    address: {
        street: string | null;
        number: string | null;
        complement: string | null;
        district: string | null;
        city: string | null;
        state: string | null;
        zipcode: string | null;
    };
    primary_color: string;
    secondary_color: string;
    manual_status: string | null;
    timezone: string;
    is_open: boolean;
    is_published: boolean;
    is_indexable: boolean;
    published_at: string | null;
    public_url: string;
    business_hours?: BusinessHour[];
    subscription?: Subscription;
    products_count?: number;
    categories_count?: number;
    created_at: string | null;
}

export interface Category {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    products_count?: number;
}

export interface AddonGroup {
    id: number;
    name: string;
    description: string | null;
    is_required: boolean;
    min_options: number;
    max_options: number;
    is_active: boolean;
    sort_order: number;
    addons?: { id: number; name: string; price_cents: number; price_formatted: string; is_active: boolean }[];
}

export interface Product {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    category_id: number | null;
    category?: Category;
    price_cents: number;
    price_formatted: string;
    promo_price_cents: number | null;
    promo_price_formatted: string | null;
    effective_price_cents: number;
    effective_price_formatted: string;
    has_discount: boolean;
    discount_percentage: number;
    image_url: string | null;
    is_active: boolean;
    is_featured: boolean;
    sort_order: number;
    addon_groups?: AddonGroup[];
}

export interface OrderItem {
    id: number;
    product_id: number | null;
    product_name: string;
    quantity: number;
    unit_price_cents: number;
    addons: { id: number; name: string; price_cents: number }[];
    addons_total_cents: number;
    total_cents: number;
    total_formatted: string;
    notes: string | null;
}

export interface Order {
    id: number;
    code: string;
    customer_name: string;
    customer_phone: string | null;
    delivery_type: string;
    address_line: string | null;
    payment_method: string | null;
    notes: string | null;
    subtotal_cents: number;
    subtotal_formatted: string;
    total_cents: number;
    total_formatted: string;
    status: string;
    status_label: string;
    channel: string;
    created_at: string | null;
    items?: OrderItem[];
}

export interface Payment {
    id: number;
    gateway: string;
    status: string;
    status_label: string;
    amount_cents: number;
    amount_formatted: string;
    currency: string;
    payment_method: string | null;
    payment_type: string | null;
    checkout_url: string | null;
    qr_code_payload: string | null;
    external_reference: string;
    approved_at: string | null;
    created_at: string | null;
    plan?: Plan;
}

export interface QrCode {
    id: number;
    target_url: string;
    image_url: string | null;
    version: number;
    updated_at: string | null;
}

export interface PlanSummary {
    plan: { id: number; name: string; slug: string; is_free: boolean };
    limits: {
        max_products: number | null;
        max_categories: number | null;
        products_used: number;
        categories_used: number;
        products_remaining: number | null;
        categories_remaining: number | null;
    };
    features: string[];
}

export interface DashboardData {
    establishment: Establishment;
    plan: PlanSummary;
    metrics: {
        products_total: number;
        products_active: number;
        categories_total: number;
        orders_last_30_days: number;
        orders_pending: number;
        views_last_30_days: number;
        revenue_last_30_days_cents: number | null;
    };
    recent_orders: Order[];
    checklist: { key: string; label: string; done: boolean }[];
}

export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; per_page?: number; total: number };
}
