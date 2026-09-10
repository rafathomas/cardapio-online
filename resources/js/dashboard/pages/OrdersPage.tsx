import { useCallback, useEffect, useState } from 'react';
import { api } from '../api';
import { useEstablishment } from '../AppContext';
import { toFailure } from '../../lib/http';
import { Badge, type Tone } from '../../components/ui/Badge';
import { Card, CardHeader } from '../../components/ui/Card';
import { EmptyState } from '../../components/ui/EmptyState';
import { Spinner } from '../../components/ui/Spinner';
import { useToast } from '../../components/ui/Toast';
import type { Order } from '../types';

const STATUSES = [
    { value: 'received', label: 'Recebido', tone: 'brand' as Tone },
    { value: 'preparing', label: 'Em preparo', tone: 'warning' as Tone },
    { value: 'ready', label: 'Pronto', tone: 'info' as Tone },
    { value: 'delivered', label: 'Entregue', tone: 'success' as Tone },
    { value: 'canceled', label: 'Cancelado', tone: 'danger' as Tone },
];

export function OrdersPage() {
    const establishment = useEstablishment();
    const toast = useToast();

    const [orders, setOrders] = useState<Order[]>([]);
    const [loading, setLoading] = useState(true);
    const [filter, setFilter] = useState('');
    const [expanded, setExpanded] = useState<number | null>(null);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            const response = await api.orders(establishment.id, filter ? { status: filter } : {});
            setOrders(response.data);
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setLoading(false);
        }
    }, [establishment.id, filter, toast]);

    useEffect(() => {
        load();
    }, [load]);

    const changeStatus = async (order: Order, status: string) => {
        try {
            const updated = await api.updateOrderStatus(establishment.id, order.id, status);
            setOrders((current) => current.map((item) => (item.id === updated.id ? { ...item, ...updated } : item)));
            toast.success('Status atualizado.');
        } catch (error) {
            toast.error(toFailure(error).message);
        }
    };

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">Pedidos</h1>
                <p className="mt-1 text-sm text-ink-muted">
                    Todo pedido enviado pelo cardápio fica registrado aqui, mesmo que a conversa siga no WhatsApp.
                </p>
            </div>

            <div className="flex flex-wrap gap-2">
                <button type="button" onClick={() => setFilter('')} aria-pressed={filter === ''}
                        className={`tap rounded-full border px-4 py-1.5 text-sm font-semibold ${
                            filter === '' ? 'border-brand bg-brand-soft text-brand-strong' : 'border-line text-ink-muted'
                        }`}>
                    Todos
                </button>

                {STATUSES.map((status) => (
                    <button key={status.value} type="button" onClick={() => setFilter(status.value)}
                            aria-pressed={filter === status.value}
                            className={`tap rounded-full border px-4 py-1.5 text-sm font-semibold ${
                                filter === status.value ? 'border-brand bg-brand-soft text-brand-strong' : 'border-line text-ink-muted'
                            }`}>
                        {status.label}
                    </button>
                ))}
            </div>

            <Card>
                <CardHeader title={`${orders.length} pedido(s)`} />

                {loading ? (
                    <Spinner />
                ) : orders.length === 0 ? (
                    <EmptyState
                        icon="receipt"
                        title="Nenhum pedido por aqui"
                        description="Quando um cliente finalizar um pedido no seu cardápio, ele aparece nesta lista."
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {orders.map((order) => {
                            const tone = STATUSES.find((s) => s.value === order.status)?.tone ?? 'neutral';
                            const isOpen = expanded === order.id;

                            return (
                                <li key={order.id}>
                                    <button
                                        type="button"
                                        onClick={() => setExpanded(isOpen ? null : order.id)}
                                        aria-expanded={isOpen}
                                        className="tap flex w-full items-center gap-3 p-4 text-left hover:bg-surface-muted"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="flex flex-wrap items-center gap-2 font-medium text-ink">
                                                {order.customer_name}
                                                <Badge tone={tone}>{order.status_label}</Badge>
                                            </p>
                                            <p className="text-sm text-ink-muted">
                                                {order.code} ·{' '}
                                                {order.created_at && new Date(order.created_at).toLocaleString('pt-BR', {
                                                    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
                                                })}
                                                {' · '}
                                                {order.delivery_type === 'delivery' ? 'Entrega' : 'Retirada'}
                                            </p>
                                        </div>

                                        <span className="font-semibold text-ink">{order.total_formatted}</span>
                                    </button>

                                    {isOpen && (
                                        <div className="border-t border-line bg-surface-muted p-4">
                                            <ul className="space-y-2 text-sm">
                                                {order.items?.map((item) => (
                                                    <li key={item.id} className="flex justify-between gap-3">
                                                        <span>
                                                            <strong>{item.quantity}x</strong> {item.product_name}
                                                            {item.addons.length > 0 && (
                                                                <span className="block text-ink-muted">
                                                                    + {item.addons.map((a) => a.name).join(', ')}
                                                                </span>
                                                            )}
                                                            {item.notes && (
                                                                <span className="block text-ink-muted italic">{item.notes}</span>
                                                            )}
                                                        </span>
                                                        <span className="shrink-0 font-medium">{item.total_formatted}</span>
                                                    </li>
                                                ))}
                                            </ul>

                                            <dl className="mt-4 space-y-1 border-t border-line pt-3 text-sm text-ink-muted">
                                                {order.customer_phone && (
                                                    <div className="flex gap-2">
                                                        <dt className="font-medium">Telefone:</dt>
                                                        <dd>
                                                            <a href={`tel:${order.customer_phone}`} className="hover:text-brand">
                                                                {order.customer_phone}
                                                            </a>
                                                        </dd>
                                                    </div>
                                                )}
                                                {order.address_line && (
                                                    <div className="flex gap-2">
                                                        <dt className="font-medium">Endereço:</dt>
                                                        <dd>{order.address_line}</dd>
                                                    </div>
                                                )}
                                                {order.payment_method && (
                                                    <div className="flex gap-2">
                                                        <dt className="font-medium">Pagamento:</dt>
                                                        <dd>{order.payment_method}</dd>
                                                    </div>
                                                )}
                                                {order.notes && (
                                                    <div className="flex gap-2">
                                                        <dt className="font-medium">Observação:</dt>
                                                        <dd>{order.notes}</dd>
                                                    </div>
                                                )}
                                            </dl>

                                            <div className="mt-4">
                                                <label className="label" htmlFor={`status-${order.id}`}>Atualizar status</label>
                                                <select
                                                    id={`status-${order.id}`}
                                                    value={order.status}
                                                    onChange={(event) => changeStatus(order, event.target.value)}
                                                    className="field max-w-56"
                                                >
                                                    {STATUSES.map((status) => (
                                                        <option key={status.value} value={status.value}>{status.label}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </Card>
        </div>
    );
}
