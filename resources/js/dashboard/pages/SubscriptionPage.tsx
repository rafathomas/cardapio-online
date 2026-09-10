import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { api } from '../api';
import { useApp, useEstablishment } from '../AppContext';
import { toFailure } from '../../lib/http';
import { Badge, type Tone } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card, CardBody, CardHeader } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Icon } from '../../components/ui/Icon';
import { Spinner } from '../../components/ui/Spinner';
import { Table, Td, Th } from '../../components/ui/Table';
import { useToast } from '../../components/ui/Toast';
import type { Payment, Plan, Subscription } from '../types';

const STATUS_TONES: Record<string, Tone> = {
    active: 'success',
    trial: 'info',
    pending: 'warning',
    past_due: 'warning',
    canceled: 'neutral',
    expired: 'danger',
    approved: 'success',
    rejected: 'danger',
};

export function SubscriptionPage() {
    const establishment = useEstablishment();
    const { refreshEstablishments } = useApp();
    const toast = useToast();
    const [params, setParams] = useSearchParams();

    const [subscription, setSubscription] = useState<Subscription | null>(null);
    const [payments, setPayments] = useState<Payment[]>([]);
    const [plans, setPlans] = useState<Plan[]>([]);
    const [loading, setLoading] = useState(true);
    const [checkingOut, setCheckingOut] = useState<number | null>(null);
    const [canceling, setCanceling] = useState(false);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            const [subscriptionData, planList] = await Promise.all([
                api.subscription(establishment.id),
                api.plans(),
            ]);

            setSubscription(subscriptionData.data);
            setPayments(subscriptionData.payments);
            setPlans(planList);

            return subscriptionData.payments;
        } catch (error) {
            toast.error(toFailure(error).message);

            return [];
        } finally {
            setLoading(false);
        }
    }, [establishment.id, toast]);

    useEffect(() => {
        load();
    }, [load]);

    /**
     * Retorno do checkout do Mercado Pago.
     *
     * A URL diz "sucesso", mas isso não é prova de pagamento: reconsultamos o
     * gateway pelo backend antes de mostrar qualquer coisa ao usuário.
     */
    useEffect(() => {
        const status = params.get('status');
        if (!status) return;

        (async () => {
            const currentPayments = await load();
            const pending = currentPayments.find((payment) => payment.status !== 'approved');

            if (pending) {
                try {
                    const result = await api.syncPayment(establishment.id, pending.id);

                    if (result.data.status === 'approved') {
                        toast.success('Pagamento confirmado. Plano Pro ativo!');
                        await refreshEstablishments();
                    } else {
                        toast.info('Ainda estamos confirmando seu pagamento com o Mercado Pago.');
                    }

                    await load();
                } catch {
                    toast.info('Estamos confirmando seu pagamento. Isso pode levar alguns instantes.');
                }
            }

            params.delete('status');
            setParams(params, { replace: true });
        })();
        // Executa apenas na volta do checkout.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const startCheckout = async (plan: Plan) => {
        setCheckingOut(plan.id);

        try {
            const result = await api.checkout(establishment.id, plan.id);

            if (result.checkout_url) {
                window.location.href = result.checkout_url;

                return;
            }

            toast.info('Cobrança criada. Conclua o pagamento para ativar o plano.');
            load();
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setCheckingOut(null);
        }
    };

    const cancel = async () => {
        setBusy(true);

        try {
            await api.cancelSubscription(establishment.id);
            await refreshEstablishments();
            toast.success('Assinatura cancelada. O acesso continua até o fim do período pago.');
            setCanceling(false);
            load();
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setBusy(false);
        }
    };

    if (loading) return <Spinner />;

    const currentPlanId = subscription?.plan?.id;

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">Assinatura</h1>
                <p className="mt-1 text-sm text-ink-muted">Gerencie seu plano e veja o histórico de cobranças.</p>
            </div>

            <Card>
                <CardHeader
                    title="Plano atual"
                    action={subscription && (
                        <Badge tone={STATUS_TONES[subscription.status] ?? 'neutral'}>
                            {subscription.status_label}
                        </Badge>
                    )}
                />
                <CardBody className="space-y-3">
                    <p className="font-display text-2xl text-ink">{subscription?.plan?.name ?? 'Free'}</p>

                    {subscription?.renews_at && subscription.status === 'active' && (
                        <p className="text-sm text-ink-muted">
                            Renova em {new Date(subscription.renews_at).toLocaleDateString('pt-BR')}
                        </p>
                    )}

                    {subscription?.canceled_at && (
                        <p className="text-sm text-warning">
                            Cancelada em {new Date(subscription.canceled_at).toLocaleDateString('pt-BR')}
                            {subscription.current_period_end &&
                                ` · acesso até ${new Date(subscription.current_period_end).toLocaleDateString('pt-BR')}`}
                        </p>
                    )}

                    {subscription?.status === 'pending' && (
                        <p className="rounded-xl bg-warning-soft p-3 text-sm font-medium text-warning">
                            Estamos aguardando a confirmação do pagamento. Assim que o Mercado Pago confirmar,
                            o plano é ativado automaticamente.
                        </p>
                    )}

                    {subscription && !subscription.plan?.is_free && subscription.status !== 'canceled' && (
                        <Button variant="ghost" onClick={() => setCanceling(true)}>Cancelar assinatura</Button>
                    )}
                </CardBody>
            </Card>

            <div className="grid gap-4 md:grid-cols-2">
                {plans.map((plan) => {
                    const isCurrent = plan.id === currentPlanId;

                    return (
                        <Card key={plan.id} className={isCurrent ? 'ring-2 ring-brand' : ''}>
                            <CardBody className="flex h-full flex-col">
                                <div className="flex items-center justify-between gap-2">
                                    <h2 className="font-display text-xl text-ink">{plan.name}</h2>
                                    {isCurrent && <Badge tone="brand">Seu plano</Badge>}
                                </div>

                                <p className="mt-1 text-sm text-ink-muted">{plan.description}</p>

                                <p className="mt-4 flex items-baseline gap-1">
                                    <span className="text-3xl font-bold text-ink">{plan.price_formatted}</span>
                                    {!plan.is_free && <span className="text-ink-muted">/mês</span>}
                                </p>

                                <ul className="mt-4 flex-1 space-y-2 text-sm">
                                    <li className="flex items-center gap-2">
                                        <Icon name="check" className="size-4 shrink-0 text-success" />
                                        {plan.max_products === null ? 'Produtos ilimitados' : `Até ${plan.max_products} produtos`}
                                    </li>
                                    <li className="flex items-center gap-2">
                                        <Icon name="check" className="size-4 shrink-0 text-success" />
                                        {plan.max_categories === null ? 'Categorias ilimitadas' : `Até ${plan.max_categories} categorias`}
                                    </li>
                                    {plan.features.map((feature) => (
                                        <li key={feature} className="flex items-center gap-2">
                                            <Icon name="check" className="size-4 shrink-0 text-success" />
                                            {FEATURE_LABELS[feature] ?? feature}
                                        </li>
                                    ))}
                                </ul>

                                {!isCurrent && !plan.is_free && (
                                    <Button
                                        className="mt-5 w-full"
                                        loading={checkingOut === plan.id}
                                        onClick={() => startCheckout(plan)}
                                    >
                                        Assinar {plan.name}
                                    </Button>
                                )}
                            </CardBody>
                        </Card>
                    );
                })}
            </div>

            <Card>
                <CardHeader title="Histórico de cobranças" />

                {payments.length === 0 ? (
                    <CardBody>
                        <p className="text-sm text-ink-muted">Nenhuma cobrança registrada.</p>
                    </CardBody>
                ) : (
                    <Table
                        caption="Histórico de cobranças da assinatura"
                        head={<><Th>Data</Th><Th>Plano</Th><Th>Valor</Th><Th>Status</Th><Th /></>}
                    >
                        {payments.map((payment) => (
                            <tr key={payment.id}>
                                <Td>{payment.created_at && new Date(payment.created_at).toLocaleDateString('pt-BR')}</Td>
                                <Td>{payment.plan?.name ?? '—'}</Td>
                                <Td className="font-medium">{payment.amount_formatted}</Td>
                                <Td>
                                    <Badge tone={STATUS_TONES[payment.status] ?? 'neutral'}>{payment.status_label}</Badge>
                                </Td>
                                <Td>
                                    {payment.status !== 'approved' && payment.checkout_url && (
                                        <a href={payment.checkout_url} className="text-sm font-semibold text-brand hover:underline">
                                            Pagar
                                        </a>
                                    )}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>

            <ConfirmDialog
                open={canceling}
                title="Cancelar assinatura"
                message="Você continua com os recursos do plano até o fim do período já pago. Depois disso, a conta volta para o plano Free."
                confirmLabel="Cancelar assinatura"
                destructive
                loading={busy}
                onConfirm={cancel}
                onCancel={() => setCanceling(false)}
            />
        </div>
    );
}

const FEATURE_LABELS: Record<string, string> = {
    remove_branding: 'Sem a marca da plataforma',
    statistics: 'Estatísticas',
    customization: 'Personalização de cores',
    addons: 'Adicionais nos produtos',
    cover_image: 'Foto de capa',
};
