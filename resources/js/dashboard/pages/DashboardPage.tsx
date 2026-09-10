import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';
import { useEstablishment } from '../AppContext';
import { toFailure } from '../../lib/http';
import { Badge } from '../../components/ui/Badge';
import { Card, CardBody, CardHeader } from '../../components/ui/Card';
import { EmptyState } from '../../components/ui/EmptyState';
import { Icon, type IconName } from '../../components/ui/Icon';
import { Spinner } from '../../components/ui/Spinner';
import { useToast } from '../../components/ui/Toast';
import type { DashboardData } from '../types';

export function DashboardPage() {
    const establishment = useEstablishment();
    const toast = useToast();
    const [data, setData] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let active = true;
        setLoading(true);

        api.dashboard(establishment.id)
            .then((result) => active && setData(result))
            .catch((error) => active && toast.error(toFailure(error).message))
            .finally(() => active && setLoading(false));

        return () => { active = false; };
    }, [establishment.id, toast]);

    if (loading) return <Spinner />;
    if (!data) return null;

    const { metrics, plan, checklist, recent_orders: recentOrders } = data;
    const pendingSteps = checklist.filter((step) => !step.done);

    return (
        <div className="space-y-6">
            <div>
                <h1 className="font-display text-2xl text-ink">Dashboard</h1>
                <p className="mt-1 text-sm text-ink-muted">Um resumo do seu cardápio nos últimos 30 dias.</p>
            </div>

            {pendingSteps.length > 0 && (
                <Card className="border-brand-border bg-brand-soft">
                    <CardBody>
                        <h2 className="font-display text-lg text-ink">Termine de montar seu cardápio</h2>
                        <ol className="mt-3 space-y-2">
                            {checklist.map((step) => (
                                <li key={step.key} className="flex items-center gap-2.5 text-sm">
                                    <span className={`flex size-5 shrink-0 items-center justify-center rounded-full ${
                                        step.done ? 'bg-success text-white' : 'border border-brand-border bg-surface'
                                    }`}>
                                        {step.done && <Icon name="check" className="size-3" />}
                                    </span>
                                    <span className={step.done ? 'text-ink-muted line-through' : 'font-medium text-ink'}>
                                        {step.label}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </CardBody>
                </Card>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Metric icon="package" label="Produtos" value={String(metrics.products_total)}
                        detail={`${metrics.products_active} ativos`} />
                <Metric icon="eye" label="Visualizações" value={metrics.views_last_30_days.toLocaleString('pt-BR')}
                        detail="últimos 30 dias" />
                <Metric icon="receipt" label="Pedidos" value={String(metrics.orders_last_30_days)}
                        detail={metrics.orders_pending > 0 ? `${metrics.orders_pending} em aberto` : 'últimos 30 dias'} />
                <Metric
                    icon="chart"
                    label="Faturamento"
                    value={metrics.revenue_last_30_days_cents === null
                        ? '—'
                        : (metrics.revenue_last_30_days_cents / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                    detail={metrics.revenue_last_30_days_cents === null ? 'disponível no Pro' : 'últimos 30 dias'}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader
                        title="Últimos pedidos"
                        action={<Link to="/app/pedidos" className="btn-ghost text-sm">Ver todos</Link>}
                    />
                    {recentOrders.length === 0 ? (
                        <EmptyState
                            icon="receipt"
                            title="Nenhum pedido ainda"
                            description="Assim que um cliente finalizar um pedido pelo cardápio, ele aparece aqui."
                        />
                    ) : (
                        <ul className="divide-y divide-line">
                            {recentOrders.map((order) => (
                                <li key={order.id} className="flex items-center justify-between gap-3 p-4">
                                    <div className="min-w-0">
                                        <p className="truncate font-medium text-ink">{order.customer_name}</p>
                                        <p className="text-sm text-ink-muted">
                                            {order.code} · {order.items?.length ?? 0} item(ns)
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-semibold text-ink">{order.total_formatted}</p>
                                        <Badge tone={order.status === 'delivered' ? 'success' : 'brand'}>
                                            {order.status_label}
                                        </Badge>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <div className="space-y-6">
                    <Card>
                        <CardHeader title="Seu plano" />
                        <CardBody className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="font-display text-xl text-ink">{plan.plan.name}</span>
                                <Badge tone={plan.plan.is_free ? 'neutral' : 'brand'}>
                                    {data.establishment.subscription?.status_label ?? 'Ativo'}
                                </Badge>
                            </div>

                            {data.establishment.subscription?.renews_at && (
                                <p className="text-sm text-ink-muted">
                                    Renova em {new Date(data.establishment.subscription.renews_at).toLocaleDateString('pt-BR')}
                                </p>
                            )}

                            <UsageBar
                                label="Produtos"
                                used={plan.limits.products_used}
                                max={plan.limits.max_products}
                            />
                            <UsageBar
                                label="Categorias"
                                used={plan.limits.categories_used}
                                max={plan.limits.max_categories}
                            />

                            <Link to="/app/assinatura" className="btn-secondary w-full">
                                {plan.plan.is_free ? 'Conhecer o Pro' : 'Gerenciar assinatura'}
                            </Link>
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="Atalhos" />
                        <CardBody className="grid grid-cols-2 gap-2">
                            <Shortcut to="/app/produtos" icon="plus" label="Novo produto" />
                            <Shortcut to="/app/categorias" icon="list" label="Categorias" />
                            <Shortcut to="/app/qrcode" icon="qr" label="QR Code" />
                            <Shortcut to="/app/personalizacao" icon="palette" label="Aparência" />
                        </CardBody>
                    </Card>
                </div>
            </div>
        </div>
    );
}

function Metric({ icon, label, value, detail }: {
    icon: IconName;
    label: string;
    value: string;
    detail: string;
}) {
    return (
        <Card>
            <CardBody>
                <div className="flex items-center gap-2 text-ink-muted">
                    <Icon name={icon} className="size-4" />
                    <span className="text-sm font-medium">{label}</span>
                </div>
                <p className="mt-2 font-display text-2xl text-ink">{value}</p>
                <p className="text-xs text-ink-muted">{detail}</p>
            </CardBody>
        </Card>
    );
}

function UsageBar({ label, used, max }: { label: string; used: number; max: number | null }) {
    if (max === null) {
        return (
            <p className="flex justify-between text-sm">
                <span className="text-ink-muted">{label}</span>
                <span className="font-medium text-ink">{used} · ilimitado</span>
            </p>
        );
    }

    const percentage = Math.min(100, Math.round((used / max) * 100));

    return (
        <div>
            <p className="flex justify-between text-sm">
                <span className="text-ink-muted">{label}</span>
                <span className="font-medium text-ink">{used} de {max}</span>
            </p>
            <div
                className="mt-1 h-1.5 overflow-hidden rounded-full bg-line"
                role="progressbar"
                aria-valuenow={used}
                aria-valuemin={0}
                aria-valuemax={max}
                aria-label={`${label}: ${used} de ${max}`}
            >
                <div className={`h-full rounded-full ${percentage >= 100 ? 'bg-danger' : 'bg-brand'}`}
                     style={{ width: `${percentage}%` }} />
            </div>
        </div>
    );
}

function Shortcut({ to, icon, label }: { to: string; icon: IconName; label: string }) {
    return (
        <Link to={to}
              className="tap flex flex-col items-center gap-1.5 rounded-xl border border-line p-3 text-center text-xs font-medium text-ink-muted hover:border-brand hover:text-brand">
            <Icon name={icon} className="size-5" />
            {label}
        </Link>
    );
}
