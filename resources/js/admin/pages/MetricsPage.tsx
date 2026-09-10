import { useEffect, useState } from 'react';
import { adminApi, type AdminMetrics } from '../api';
import { toFailure } from '../../lib/http';
import { Card, CardBody, CardHeader } from '../../components/ui/Card';
import { Icon, type IconName } from '../../components/ui/Icon';
import { Spinner } from '../../components/ui/Spinner';
import { useToast } from '../../components/ui/Toast';

const STATUS_LABELS: Record<string, string> = {
    trial: 'Em teste',
    active: 'Ativas',
    past_due: 'Em atraso',
    pending: 'Aguardando pagamento',
    canceled: 'Canceladas',
    expired: 'Expiradas',
};

export function MetricsPage() {
    const toast = useToast();
    const [metrics, setMetrics] = useState<AdminMetrics | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        adminApi.metrics()
            .then(setMetrics)
            .catch((error) => toast.error(toFailure(error).message))
            .finally(() => setLoading(false));
    }, [toast]);

    if (loading) return <Spinner />;
    if (!metrics) return null;

    return (
        <div className="space-y-6">
            <div>
                <h1 className="font-display text-2xl text-ink">Visão geral</h1>
                <p className="mt-1 text-sm text-ink-muted">Números da plataforma.</p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Metric icon="users" label="Usuários" value={metrics.users_total.toLocaleString('pt-BR')}
                        detail={`${metrics.users_blocked} bloqueados`} />
                <Metric icon="store" label="Estabelecimentos" value={metrics.establishments_total.toLocaleString('pt-BR')}
                        detail={`${metrics.establishments_published} publicados`} />
                <Metric icon="card" label="Receita recorrente" value={metrics.mrr_formatted} detail="MRR estimado" />
                <Metric icon="receipt" label="Pedidos" value={metrics.orders_total.toLocaleString('pt-BR')}
                        detail="total registrado" />
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader title="Assinaturas por status" />
                    <CardBody>
                        {Object.keys(metrics.subscriptions_by_status).length === 0 ? (
                            <p className="text-sm text-ink-muted">Nenhuma assinatura registrada.</p>
                        ) : (
                            <ul className="space-y-2">
                                {Object.entries(metrics.subscriptions_by_status).map(([status, total]) => (
                                    <li key={status} className="flex items-center justify-between text-sm">
                                        <span className="text-ink-muted">{STATUS_LABELS[status] ?? status}</span>
                                        <span className="font-semibold text-ink">{total}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Pagamentos" />
                    <CardBody>
                        <ul className="space-y-2 text-sm">
                            <li className="flex items-center justify-between">
                                <span className="text-ink-muted">Aprovados</span>
                                <span className="font-semibold text-ink">{metrics.payments_approved}</span>
                            </li>
                            <li className="flex items-center justify-between">
                                <span className="text-ink-muted">Receita acumulada</span>
                                <span className="font-semibold text-ink">
                                    {(metrics.revenue_total_cents / 100).toLocaleString('pt-BR', {
                                        style: 'currency', currency: 'BRL',
                                    })}
                                </span>
                            </li>
                        </ul>
                    </CardBody>
                </Card>
            </div>
        </div>
    );
}

function Metric({ icon, label, value, detail }: {
    icon: IconName; label: string; value: string; detail: string;
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
