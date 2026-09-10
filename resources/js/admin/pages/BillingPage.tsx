import { useCallback, useEffect, useState } from 'react';
import { adminApi, type AdminPaymentEvent } from '../api';
import { toFailure } from '../../lib/http';
import { Badge, type Tone } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card, CardHeader } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Spinner } from '../../components/ui/Spinner';
import { Table, Td, Th } from '../../components/ui/Table';
import { useToast } from '../../components/ui/Toast';
import type { Payment, Subscription } from '../../dashboard/types';

type SubscriptionRow = Subscription & { establishment: { id: number; name: string; slug: string } };
type PaymentRow = Payment & { establishment: { id: number; name: string } };

const TONES: Record<string, Tone> = {
    active: 'success', approved: 'success', processed: 'success',
    pending: 'warning', past_due: 'warning', received: 'warning', in_process: 'warning',
    canceled: 'neutral', ignored: 'neutral',
    expired: 'danger', rejected: 'danger', failed: 'danger',
};

type Tab = 'subscriptions' | 'payments' | 'events';

export function BillingPage() {
    const toast = useToast();
    const [tab, setTab] = useState<Tab>('subscriptions');
    const [subscriptions, setSubscriptions] = useState<SubscriptionRow[]>([]);
    const [payments, setPayments] = useState<PaymentRow[]>([]);
    const [events, setEvents] = useState<AdminPaymentEvent[]>([]);
    const [loading, setLoading] = useState(true);
    const [canceling, setCanceling] = useState<SubscriptionRow | null>(null);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            if (tab === 'subscriptions') {
                setSubscriptions((await adminApi.subscriptions()).data);
            } else if (tab === 'payments') {
                setPayments((await adminApi.payments()).data);
            } else {
                setEvents((await adminApi.paymentEvents()).data);
            }
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setLoading(false);
        }
    }, [tab, toast]);

    useEffect(() => {
        load();
    }, [load]);

    const cancel = async () => {
        if (!canceling) return;

        setBusy(true);

        try {
            await adminApi.cancelSubscription(canceling.id);
            toast.success('Assinatura cancelada.');
            setCanceling(null);
            load();
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setBusy(false);
        }
    };

    const TABS: { value: Tab; label: string }[] = [
        { value: 'subscriptions', label: 'Assinaturas' },
        { value: 'payments', label: 'Pagamentos' },
        { value: 'events', label: 'Eventos de webhook' },
    ];

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">Financeiro</h1>
                <p className="mt-1 text-sm text-ink-muted">
                    Assinaturas, cobranças e o rastro de eventos recebidos do gateway.
                </p>
            </div>

            <div className="flex flex-wrap gap-2" role="tablist">
                {TABS.map((item) => (
                    <button
                        key={item.value}
                        type="button"
                        role="tab"
                        aria-selected={tab === item.value}
                        onClick={() => setTab(item.value)}
                        className={`tap rounded-full border px-4 py-1.5 text-sm font-semibold ${
                            tab === item.value ? 'border-ink bg-ink text-white' : 'border-line text-ink-muted'
                        }`}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            <Card>
                <CardHeader title={TABS.find((item) => item.value === tab)!.label} />

                {loading ? (
                    <Spinner />
                ) : tab === 'subscriptions' ? (
                    <Table
                        caption="Assinaturas"
                        head={<><Th>Estabelecimento</Th><Th>Plano</Th><Th>Status</Th><Th>Renova em</Th><Th /></>}
                    >
                        {subscriptions.map((row) => (
                            <tr key={row.id}>
                                <Td className="font-medium">{row.establishment.name}</Td>
                                <Td>{row.plan?.name ?? '—'}</Td>
                                <Td><Badge tone={TONES[row.status] ?? 'neutral'}>{row.status_label}</Badge></Td>
                                <Td className="text-ink-muted">
                                    {row.renews_at ? new Date(row.renews_at).toLocaleDateString('pt-BR') : '—'}
                                </Td>
                                <Td>
                                    {row.status !== 'canceled' && (
                                        <Button variant="ghost" size="sm" onClick={() => setCanceling(row)}>
                                            Cancelar
                                        </Button>
                                    )}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                ) : tab === 'payments' ? (
                    <Table
                        caption="Pagamentos"
                        head={<><Th>Data</Th><Th>Estabelecimento</Th><Th>Valor</Th><Th>Método</Th><Th>Status</Th></>}
                    >
                        {payments.map((row) => (
                            <tr key={row.id}>
                                <Td className="text-ink-muted">
                                    {row.created_at && new Date(row.created_at).toLocaleString('pt-BR')}
                                </Td>
                                <Td className="font-medium">{row.establishment.name}</Td>
                                <Td>{row.amount_formatted}</Td>
                                <Td className="text-ink-muted">{row.payment_method ?? '—'}</Td>
                                <Td><Badge tone={TONES[row.status] ?? 'neutral'}>{row.status_label}</Badge></Td>
                            </tr>
                        ))}
                    </Table>
                ) : (
                    <Table
                        caption="Eventos de webhook"
                        head={<><Th>Recebido</Th><Th>Tipo</Th><Th>Recurso</Th><Th>Status</Th><Th>Detalhe</Th></>}
                    >
                        {events.map((row) => (
                            <tr key={row.id}>
                                <Td className="text-ink-muted">
                                    {row.created_at && new Date(row.created_at).toLocaleString('pt-BR')}
                                </Td>
                                <Td>{row.event_action ?? row.event_type ?? '—'}</Td>
                                <Td className="font-mono text-xs">{row.gateway_resource_id ?? '—'}</Td>
                                <Td><Badge tone={TONES[row.status] ?? 'neutral'}>{row.status}</Badge></Td>
                                <Td className="max-w-64 truncate text-ink-muted">{row.error_message ?? '—'}</Td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>

            <ConfirmDialog
                open={canceling !== null}
                title="Cancelar assinatura"
                message={`A assinatura de ${canceling?.establishment.name} será cancelada. O acesso segue até o fim do período pago.`}
                confirmLabel="Cancelar assinatura"
                destructive
                loading={busy}
                onConfirm={cancel}
                onCancel={() => setCanceling(null)}
            />
        </div>
    );
}
