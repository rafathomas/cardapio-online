import { http } from '../lib/http';
import type { Establishment, Payment, Plan, Subscription, User } from '../dashboard/types';

export interface AdminUser extends User {
    blocked: boolean;
    blocked_at: string | null;
    establishments_count?: number;
}

export interface AdminPaymentEvent {
    id: number;
    gateway: string;
    event_key: string;
    event_type: string | null;
    event_action: string | null;
    gateway_resource_id: string | null;
    status: string;
    error_message: string | null;
    payment_id: number | null;
    processed_at: string | null;
    created_at: string | null;
}

export interface AdminMetrics {
    users_total: number;
    users_blocked: number;
    establishments_total: number;
    establishments_published: number;
    subscriptions_by_status: Record<string, number>;
    payments_approved: number;
    revenue_total_cents: number;
    mrr_cents: number;
    mrr_formatted: string;
    orders_total: number;
}

interface Page<T> {
    data: T[];
    meta: { current_page: number; last_page: number; total: number };
}

export const adminApi = {
    me: () => http.get<{ user: User }>('/me').then((r) => r.data.user),
    logout: () => http.post('/auth/logout').then(() => undefined),

    metrics: () => http.get<AdminMetrics>('/admin/metrics').then((r) => r.data),

    users: (params: Record<string, string | number> = {}) =>
        http.get<Page<AdminUser>>('/admin/users', { params }).then((r) => r.data),

    blockUser: (id: number) => http.post(`/admin/users/${id}/block`).then(() => undefined),
    unblockUser: (id: number) => http.post(`/admin/users/${id}/unblock`).then(() => undefined),

    establishments: (params: Record<string, string | number> = {}) =>
        http.get<Page<Establishment & { owner: { id: number; name: string; email: string } }>>(
            '/admin/establishments', { params },
        ).then((r) => r.data),

    plans: () => http.get<{ data: Plan[] }>('/admin/plans').then((r) => r.data.data),

    updatePlan: (id: number, payload: Record<string, unknown>) =>
        http.put<{ data: Plan }>(`/admin/plans/${id}`, payload).then((r) => r.data.data),

    subscriptions: (params: Record<string, string | number> = {}) =>
        http.get<Page<Subscription & { establishment: { id: number; name: string; slug: string } }>>(
            '/admin/subscriptions', { params },
        ).then((r) => r.data),

    cancelSubscription: (id: number, immediately = false) =>
        http.post(`/admin/subscriptions/${id}/cancel`, { immediately }).then(() => undefined),

    payments: (params: Record<string, string | number> = {}) =>
        http.get<Page<Payment & { establishment: { id: number; name: string } }>>(
            '/admin/payments', { params },
        ).then((r) => r.data),

    paymentEvents: (params: Record<string, string | number> = {}) =>
        http.get<Page<AdminPaymentEvent>>('/admin/payment-events', { params }).then((r) => r.data),
};
