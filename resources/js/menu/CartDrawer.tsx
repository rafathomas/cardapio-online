import { useEffect, useId, useState } from 'react';
import { fieldError, http, toFailure, type ApiErrors } from '../lib/http';
import { formatBRL, maskPhone } from '../lib/format';
import type { CartItem, DeliveryType, MenuProduct } from './types';

interface Props {
    slug: string;
    items: CartItem[];
    products: Map<number, MenuProduct>;
    estimateCents: number;
    isOpen: boolean;
    onClose: () => void;
    onChangeQuantity: (key: string, delta: number) => void;
    onRemove: (key: string) => void;
    onOrdered: () => void;
}

interface ServerTotals {
    total_cents: number;
    total_formatted: string;
}

const PAYMENT_METHODS = ['Pix', 'Cartão de crédito', 'Cartão de débito', 'Dinheiro'];

export function CartDrawer({
    slug, items, products, estimateCents, isOpen,
    onClose, onChangeQuantity, onRemove, onOrdered,
}: Props) {
    const [step, setStep] = useState<'cart' | 'checkout'>('cart');
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [deliveryType, setDeliveryType] = useState<DeliveryType>('pickup');
    const [address, setAddress] = useState('');
    const [paymentMethod, setPaymentMethod] = useState(PAYMENT_METHODS[0]!);
    const [notes, setNotes] = useState('');

    const [totals, setTotals] = useState<ServerTotals | null>(null);
    const [errors, setErrors] = useState<ApiErrors>({});
    const [message, setMessage] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);
    const titleId = useId();

    // Confirma o total no servidor sempre que o carrinho muda.
    useEffect(() => {
        if (!isOpen || items.length === 0) {
            setTotals(null);

            return;
        }

        const controller = new AbortController();

        http.post(`/menu/${slug}/cart/preview`, {
            items: items.map((item) => ({
                product_id: item.productId,
                quantity: item.quantity,
                notes: item.notes || null,
                addon_ids: item.addonIds,
            })),
        }, { signal: controller.signal })
            .then((response) => setTotals(response.data.data))
            .catch(() => setTotals(null));

        return () => controller.abort();
    }, [isOpen, items, slug]);

    useEffect(() => {
        if (!isOpen) return;

        const onKey = (event: KeyboardEvent) => event.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [isOpen, onClose]);

    if (!isOpen) return null;

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setMessage(null);

        try {
            const response = await http.post(`/menu/${slug}/orders`, {
                customer: {
                    name,
                    phone: phone || null,
                    delivery_type: deliveryType,
                    address: deliveryType === 'delivery' ? address : null,
                    payment_method: paymentMethod,
                    notes: notes || null,
                },
                items: items.map((item) => ({
                    product_id: item.productId,
                    quantity: item.quantity,
                    notes: item.notes || null,
                    addon_ids: item.addonIds,
                })),
            });

            onOrdered();

            // Abre o WhatsApp do estabelecimento com a mensagem já montada.
            window.location.href = response.data.whatsapp_url;
        } catch (error) {
            const failure = toFailure(error);
            setErrors(failure.errors);
            setMessage(failure.message);
            setSubmitting(false);
        }
    };

    const displayTotal = totals?.total_formatted ?? formatBRL(estimateCents);

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
            onClick={(event) => event.target === event.currentTarget && onClose()}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                className="animate-rise flex max-h-[92dvh] w-full max-w-lg flex-col rounded-t-2xl bg-surface sm:rounded-2xl"
            >
                <header className="flex items-center justify-between gap-3 border-b border-line p-4">
                    <h2 id={titleId} className="font-display text-lg text-ink">
                        {step === 'cart' ? 'Seu pedido' : 'Finalizar pedido'}
                    </h2>
                    <button type="button" onClick={onClose} className="btn-ghost -mr-1 px-2" aria-label="Fechar carrinho">
                        <svg className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                {items.length === 0 ? (
                    <div className="p-10 text-center">
                        <p className="text-ink-muted">Seu carrinho está vazio.</p>
                        <button type="button" onClick={onClose} className="btn-secondary mt-4">
                            Ver o cardápio
                        </button>
                    </div>
                ) : step === 'cart' ? (
                    <>
                        <ul className="flex-1 divide-y divide-line overflow-y-auto">
                            {items.map((item) => {
                                const product = products.get(item.productId);
                                if (!product) return null;

                                const addons = product.addon_groups
                                    .flatMap((group) => group.addons)
                                    .filter((addon) => item.addonIds.includes(addon.id));

                                const unitCents =
                                    product.price_cents + addons.reduce((sum, a) => sum + a.price_cents, 0);

                                return (
                                    <li key={item.key} className="flex gap-3 p-4">
                                        <div className="min-w-0 flex-1">
                                            <p className="font-semibold text-ink">{product.name}</p>

                                            {addons.length > 0 && (
                                                <p className="mt-0.5 text-sm text-ink-muted">
                                                    {addons.map((a) => a.name).join(', ')}
                                                </p>
                                            )}

                                            {item.notes && (
                                                <p className="mt-0.5 text-sm text-ink-muted italic">{item.notes}</p>
                                            )}

                                            <p className="mt-1.5 font-semibold text-brand">
                                                {formatBRL(unitCents * item.quantity)}
                                            </p>
                                        </div>

                                        <div className="flex flex-col items-end gap-2">
                                            <div className="flex items-center gap-1 rounded-xl border border-line">
                                                <button type="button" onClick={() => onChangeQuantity(item.key, -1)}
                                                        className="tap px-2.5" aria-label={`Diminuir ${product.name}`}>−</button>
                                                <span className="w-5 text-center text-sm font-semibold">{item.quantity}</span>
                                                <button type="button" onClick={() => onChangeQuantity(item.key, 1)}
                                                        className="tap px-2.5" aria-label={`Aumentar ${product.name}`}>+</button>
                                            </div>

                                            <button type="button" onClick={() => onRemove(item.key)}
                                                    className="text-sm text-ink-muted underline hover:text-danger">
                                                Remover
                                            </button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>

                        <footer className="border-t border-line p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <span className="text-ink-muted">Total</span>
                                <span className="text-xl font-bold text-ink" aria-live="polite">{displayTotal}</span>
                            </div>
                            <button type="button" onClick={() => setStep('checkout')} className="btn-primary w-full py-3">
                                Continuar
                            </button>
                        </footer>
                    </>
                ) : (
                    <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col" noValidate>
                        <div className="flex-1 space-y-4 overflow-y-auto p-4">
                            {message && (
                                <p role="alert" className="rounded-xl bg-danger-soft p-3 text-sm font-medium text-danger">
                                    {message}
                                </p>
                            )}

                            <div>
                                <label className="label" htmlFor="cliente-nome">Seu nome</label>
                                <input id="cliente-nome" value={name} required autoComplete="name"
                                       onChange={(e) => setName(e.target.value)} className="field" />
                                {fieldError(errors, 'customer.name') && (
                                    <p className="error">{fieldError(errors, 'customer.name')}</p>
                                )}
                            </div>

                            <div>
                                <label className="label" htmlFor="cliente-telefone">Telefone</label>
                                <input id="cliente-telefone" value={phone} inputMode="tel" autoComplete="tel"
                                       placeholder="(11) 98888-7777"
                                       onChange={(e) => setPhone(maskPhone(e.target.value))} className="field" />
                                <p className="hint">Para o estabelecimento entrar em contato se precisar.</p>
                                {fieldError(errors, 'customer.phone') && (
                                    <p className="error">{fieldError(errors, 'customer.phone')}</p>
                                )}
                            </div>

                            <fieldset>
                                <legend className="label">Como quer receber?</legend>
                                <div className="grid grid-cols-2 gap-2">
                                    {([['pickup', 'Retirar no local'], ['delivery', 'Entrega']] as const).map(([value, label]) => (
                                        <label key={value}
                                               className={`tap flex items-center justify-center rounded-xl border p-3 text-sm font-semibold ${
                                                   deliveryType === value ? 'border-brand bg-brand-soft text-brand-strong' : 'border-line'
                                               }`}>
                                            <input type="radio" name="entrega" value={value} className="sr-only"
                                                   checked={deliveryType === value}
                                                   onChange={() => setDeliveryType(value)} />
                                            {label}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>

                            {deliveryType === 'delivery' && (
                                <div>
                                    <label className="label" htmlFor="cliente-endereco">Endereço de entrega</label>
                                    <input id="cliente-endereco" value={address} autoComplete="street-address"
                                           placeholder="Rua, número, complemento e bairro"
                                           onChange={(e) => setAddress(e.target.value)} className="field" />
                                    {fieldError(errors, 'customer.address') && (
                                        <p className="error">{fieldError(errors, 'customer.address')}</p>
                                    )}
                                </div>
                            )}

                            <div>
                                <label className="label" htmlFor="pagamento">Forma de pagamento</label>
                                <select id="pagamento" value={paymentMethod} className="field"
                                        onChange={(e) => setPaymentMethod(e.target.value)}>
                                    {PAYMENT_METHODS.map((method) => (
                                        <option key={method} value={method}>{method}</option>
                                    ))}
                                </select>
                                <p className="hint">O pagamento é combinado direto com o estabelecimento.</p>
                            </div>

                            <div>
                                <label className="label" htmlFor="obs-pedido">Observação do pedido</label>
                                <textarea id="obs-pedido" value={notes} rows={2} maxLength={500}
                                          onChange={(e) => setNotes(e.target.value)}
                                          className="field resize-none" />
                            </div>
                        </div>

                        <footer className="border-t border-line p-4">
                            <div className="mb-3 flex items-center justify-between">
                                <span className="text-ink-muted">Total</span>
                                <span className="text-xl font-bold text-ink">{displayTotal}</span>
                            </div>

                            <div className="flex gap-2">
                                <button type="button" onClick={() => setStep('cart')} className="btn-secondary px-4 py-3">
                                    Voltar
                                </button>
                                <button type="submit" disabled={submitting} className="btn-primary flex-1 py-3">
                                    {submitting ? 'Enviando...' : 'Enviar pelo WhatsApp'}
                                </button>
                            </div>
                        </footer>
                    </form>
                )}
            </div>
        </div>
    );
}
