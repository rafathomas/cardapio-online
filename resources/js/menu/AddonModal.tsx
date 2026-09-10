import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { formatBRL } from '../lib/format';
import type { MenuProduct } from './types';

interface Props {
    product: MenuProduct;
    onClose: () => void;
    onConfirm: (addonIds: number[], notes: string, quantity: number) => void;
}

/**
 * Escolha de adicionais antes de adicionar ao carrinho.
 * As mesmas regras de mínimo/máximo são revalidadas no servidor.
 */
export function AddonModal({ product, onClose, onConfirm }: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [notes, setNotes] = useState('');
    const [quantity, setQuantity] = useState(1);
    const titleId = useId();
    const dialogRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        dialogRef.current?.focus();

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') onClose();
        };

        document.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';

        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [onClose]);

    const toggle = (groupId: number, addonId: number, maxOptions: number) => {
        setSelected((current) => {
            if (current.includes(addonId)) {
                return current.filter((id) => id !== addonId);
            }

            const group = product.addon_groups.find((g) => g.id === groupId);
            const inGroup = group?.addons.filter((a) => current.includes(a.id)) ?? [];

            // Grupo de escolha única troca a seleção em vez de bloquear.
            if (maxOptions === 1) {
                return [...current.filter((id) => !inGroup.some((a) => a.id === id)), addonId];
            }

            if (inGroup.length >= maxOptions) {
                return current;
            }

            return [...current, addonId];
        });
    };

    const { total, missing } = useMemo(() => {
        const addonsTotal = product.addon_groups
            .flatMap((group) => group.addons)
            .filter((addon) => selected.includes(addon.id))
            .reduce((sum, addon) => sum + addon.price_cents, 0);

        const unmet = product.addon_groups.filter((group) => {
            const chosen = group.addons.filter((a) => selected.includes(a.id)).length;

            return group.is_required && chosen < Math.max(1, group.min_options);
        });

        return {
            total: (product.price_cents + addonsTotal) * quantity,
            missing: unmet,
        };
    }, [product, selected, quantity]);

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
            onClick={(event) => event.target === event.currentTarget && onClose()}
        >
            <div
                ref={dialogRef}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                tabIndex={-1}
                className="animate-rise flex max-h-[90dvh] w-full max-w-lg flex-col rounded-t-2xl bg-surface sm:rounded-2xl"
            >
                <header className="flex items-start justify-between gap-3 border-b border-line p-4">
                    <div>
                        <h2 id={titleId} className="font-display text-lg text-ink">{product.name}</h2>
                        <p className="text-sm text-ink-muted">{product.price_formatted}</p>
                    </div>
                    <button type="button" onClick={onClose} className="btn-ghost -mt-1 -mr-1 px-2" aria-label="Fechar">
                        <svg className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                </header>

                <div className="flex-1 overflow-y-auto p-4">
                    {product.addon_groups.map((group) => {
                        const chosen = group.addons.filter((a) => selected.includes(a.id)).length;

                        return (
                            <fieldset key={group.id} className="mb-5">
                                <legend className="flex w-full items-center justify-between gap-2 pb-2">
                                    <span className="font-semibold text-ink">{group.name}</span>
                                    <span className={`badge ${group.is_required ? 'bg-brand-soft text-brand-strong' : 'bg-surface-muted text-ink-muted'}`}>
                                        {group.is_required ? 'Obrigatório' : 'Opcional'}
                                        {group.max_options > 1 && ` · até ${group.max_options}`}
                                    </span>
                                </legend>

                                <div className="space-y-1.5">
                                    {group.addons.map((addon) => {
                                        const isSelected = selected.includes(addon.id);
                                        const blocked = !isSelected && group.max_options > 1 && chosen >= group.max_options;

                                        return (
                                            <label
                                                key={addon.id}
                                                className={`tap flex items-center gap-3 rounded-xl border p-3 ${
                                                    isSelected ? 'border-brand bg-brand-soft' : 'border-line'
                                                } ${blocked ? 'opacity-40' : ''}`}
                                            >
                                                <input
                                                    type={group.max_options === 1 ? 'radio' : 'checkbox'}
                                                    name={`grupo-${group.id}`}
                                                    checked={isSelected}
                                                    disabled={blocked}
                                                    onChange={() => toggle(group.id, addon.id, group.max_options)}
                                                    className="size-4 accent-[var(--color-brand)]"
                                                />
                                                <span className="flex-1 text-sm">{addon.name}</span>
                                                {addon.price_cents > 0 && (
                                                    <span className="text-sm font-semibold text-ink-muted">
                                                        + {addon.price_formatted}
                                                    </span>
                                                )}
                                            </label>
                                        );
                                    })}
                                </div>
                            </fieldset>
                        );
                    })}

                    <label className="label" htmlFor="obs-item">Alguma observação?</label>
                    <textarea
                        id="obs-item"
                        value={notes}
                        onChange={(event) => setNotes(event.target.value)}
                        rows={2}
                        maxLength={255}
                        placeholder="Ex.: sem cebola, ponto da carne..."
                        className="field resize-none"
                    />
                </div>

                <footer className="flex items-center gap-3 border-t border-line p-4">
                    <div className="flex items-center gap-1 rounded-xl border border-line">
                        <button type="button" onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                                className="tap px-3 text-lg" aria-label="Diminuir quantidade">−</button>
                        <span className="w-6 text-center font-semibold" aria-live="polite">{quantity}</span>
                        <button type="button" onClick={() => setQuantity((q) => Math.min(99, q + 1))}
                                className="tap px-3 text-lg" aria-label="Aumentar quantidade">+</button>
                    </div>

                    <button
                        type="button"
                        disabled={missing.length > 0}
                        onClick={() => onConfirm(selected, notes, quantity)}
                        className="btn-primary flex-1 py-3"
                    >
                        {missing.length > 0
                            ? `Escolha: ${missing[0]!.name}`
                            : `Adicionar · ${formatBRL(total)}`}
                    </button>
                </footer>
            </div>
        </div>
    );
}
