import { http } from '../lib/http';
import type {
    AddonGroup, Category, DashboardData, Establishment, Order, Paginated, Payment,
    Plan, PlanSummary, Product, QrCode, Subscription, User,
} from './types';

/**
 * Chamadas da API do painel.
 *
 * Uma função por endpoint mantém as páginas livres de detalhes de transporte
 * e concentra a tipagem das respostas em um lugar só.
 */
export const api = {
    /* Autenticação */
    register: (payload: { name: string; email: string; password: string; password_confirmation: string }) =>
        http.post<{ user: User }>('/auth/register', payload).then((r) => r.data.user),

    login: (payload: { email: string; password: string; remember?: boolean }) =>
        http.post<{ user: User }>('/auth/login', payload).then((r) => r.data.user),

    logout: () => http.post('/auth/logout').then(() => undefined),

    me: () => http.get<{ user: User }>('/me').then((r) => r.data.user),

    forgotPassword: (email: string) =>
        http.post<{ message: string }>('/auth/forgot-password', { email }).then((r) => r.data.message),

    resetPassword: (payload: { token: string; email: string; password: string; password_confirmation: string }) =>
        http.post('/auth/reset-password', payload).then(() => undefined),

    resendVerification: () =>
        http.post<{ message: string }>('/auth/email/resend').then((r) => r.data.message),

    updateProfile: (payload: { name: string; email: string }) =>
        http.put<{ user: User }>('/me', payload).then((r) => r.data.user),

    updatePassword: (payload: { current_password: string; password: string; password_confirmation: string }) =>
        http.put('/me/password', payload).then(() => undefined),

    /* Planos */
    plans: () => http.get<{ data: Plan[] }>('/plans').then((r) => r.data.data),

    /* Estabelecimentos */
    establishments: () =>
        http.get<{ data: Establishment[] }>('/establishments').then((r) => r.data.data),

    createEstablishment: (payload: Record<string, unknown>) =>
        http.post<{ data: Establishment }>('/establishments', payload).then((r) => r.data.data),

    establishment: (id: number) =>
        http.get<{ data: Establishment; plan: PlanSummary }>(`/establishments/${id}`).then((r) => r.data),

    updateEstablishment: (id: number, payload: Payload) =>
        http.post<{ data: Establishment }>(`/establishments/${id}`, body(payload)).then((r) => r.data.data),

    publish: (id: number) =>
        http.post<{ message: string; public_url: string }>(`/establishments/${id}/publish`).then((r) => r.data),

    unpublish: (id: number) => http.post(`/establishments/${id}/unpublish`).then(() => undefined),

    updateBusinessHours: (id: number, hours: unknown[]) =>
        http.put(`/establishments/${id}/business-hours`, { hours }).then(() => undefined),

    /* Painel */
    dashboard: (id: number) =>
        http.get<DashboardData>(`/establishments/${id}/dashboard`).then((r) => r.data),

    /* Categorias */
    categories: (id: number) =>
        http.get<{ data: Category[] }>(`/establishments/${id}/categories`).then((r) => r.data.data),

    createCategory: (id: number, payload: Record<string, unknown>) =>
        http.post<{ data: Category }>(`/establishments/${id}/categories`, payload).then((r) => r.data.data),

    updateCategory: (id: number, categoryId: number, payload: Record<string, unknown>) =>
        http.put<{ data: Category }>(`/establishments/${id}/categories/${categoryId}`, payload).then((r) => r.data.data),

    deleteCategory: (id: number, categoryId: number) =>
        http.delete(`/establishments/${id}/categories/${categoryId}`).then(() => undefined),

    reorderCategories: (id: number, ids: number[]) =>
        http.post(`/establishments/${id}/categories/reorder`, { ids }).then(() => undefined),

    /* Produtos */
    products: (id: number, params: Record<string, string | number> = {}) =>
        http.get<Paginated<Product> & { plan: PlanSummary }>(`/establishments/${id}/products`, { params })
            .then((r) => r.data),

    createProduct: (id: number, payload: Payload) =>
        http.post<{ data: Product }>(`/establishments/${id}/products`, body(payload)).then((r) => r.data.data),

    updateProduct: (id: number, productId: number, payload: Payload) =>
        http.post<{ data: Product }>(`/establishments/${id}/products/${productId}`, body(payload)).then((r) => r.data.data),

    deleteProduct: (id: number, productId: number) =>
        http.delete(`/establishments/${id}/products/${productId}`).then(() => undefined),

    duplicateProduct: (id: number, productId: number) =>
        http.post<{ data: Product }>(`/establishments/${id}/products/${productId}/duplicate`).then((r) => r.data.data),

    toggleProduct: (id: number, productId: number) =>
        http.post<{ data: Product }>(`/establishments/${id}/products/${productId}/toggle`).then((r) => r.data.data),

    reorderProducts: (id: number, ids: number[]) =>
        http.post(`/establishments/${id}/products/reorder`, { ids }).then(() => undefined),

    importProducts: (id: number, file: File) => {
        const form = new FormData();
        form.append('file', file);

        return http.post<{ created: number; errors: { row: number; message: string }[]; plan: PlanSummary; message: string }>(
            `/establishments/${id}/products/import`,
            form,
        ).then((r) => r.data);
    },

    /* Adicionais */
    addonGroups: (id: number) =>
        http.get<{ data: AddonGroup[] }>(`/establishments/${id}/addon-groups`).then((r) => r.data.data),

    createAddonGroup: (id: number, payload: Record<string, unknown>) =>
        http.post<{ data: AddonGroup }>(`/establishments/${id}/addon-groups`, payload).then((r) => r.data.data),

    updateAddonGroup: (id: number, groupId: number, payload: Record<string, unknown>) =>
        http.put<{ data: AddonGroup }>(`/establishments/${id}/addon-groups/${groupId}`, payload).then((r) => r.data.data),

    deleteAddonGroup: (id: number, groupId: number) =>
        http.delete(`/establishments/${id}/addon-groups/${groupId}`).then(() => undefined),

    /* Pedidos */
    orders: (id: number, params: Record<string, string | number> = {}) =>
        http.get<Paginated<Order>>(`/establishments/${id}/orders`, { params }).then((r) => r.data),

    updateOrderStatus: (id: number, orderId: number, status: string) =>
        http.put<{ data: Order }>(`/establishments/${id}/orders/${orderId}/status`, { status }).then((r) => r.data.data),

    /* QR Code */
    qrCode: (id: number) =>
        http.get<{ data: QrCode }>(`/establishments/${id}/qrcode`).then((r) => r.data.data),

    regenerateQrCode: (id: number) =>
        http.post<{ data: QrCode }>(`/establishments/${id}/qrcode/regenerate`).then((r) => r.data.data),

    /* Assinatura */
    subscription: (id: number) =>
        http.get<{ data: Subscription | null; payments: Payment[] }>(`/establishments/${id}/subscription`)
            .then((r) => r.data),

    checkout: (id: number, planId: number, method: 'checkout' | 'pix' = 'checkout') =>
        http.post<{ data: Payment; checkout_url: string | null }>(
            `/establishments/${id}/subscription/checkout`,
            { plan_id: planId, method },
        ).then((r) => r.data),

    syncPayment: (id: number, paymentId: number) =>
        http.post<{ data: Payment; subscription: Subscription | null }>(
            `/establishments/${id}/payments/${paymentId}/sync`,
        ).then((r) => r.data),

    cancelSubscription: (id: number) =>
        http.post<{ data: Subscription }>(`/establishments/${id}/subscription/cancel`).then((r) => r.data.data),
};

/** Campos de um formulário: valores simples ou um arquivo de upload. */
export type Payload = Record<string, string | number | boolean | File | null | undefined | (string | number)[]>;

/**
 * Escolhe o formato do corpo da requisição.
 *
 * Sem arquivo, envia JSON — mais leve e preserva os tipos (booleano continua
 * booleano). Multipart só entra em cena quando há de fato um upload.
 *
 * As rotas de criação e atualização aceitam POST, então não há method
 * spoofing: acrescentar _method=PUT transformaria um create em update.
 */
function body(payload: Payload): FormData | Record<string, unknown> {
    const hasFile = Object.values(payload).some((value) => value instanceof File);

    if (!hasFile) {
        return Object.fromEntries(
            Object.entries(payload).filter(([, value]) => value !== undefined),
        );
    }

    const form = new FormData();

    for (const [key, value] of Object.entries(payload)) {
        if (value === undefined || value === null) continue;

        if (value instanceof File) {
            form.append(key, value);
        } else if (typeof value === 'boolean') {
            form.append(key, value ? '1' : '0');
        } else if (Array.isArray(value)) {
            value.forEach((entry) => form.append(`${key}[]`, String(entry)));
        } else {
            form.append(key, String(value));
        }
    }

    return form;
}
