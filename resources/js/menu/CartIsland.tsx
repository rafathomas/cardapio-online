import { useCallback, useEffect, useState } from 'react';
import { AddonModal } from './AddonModal';
import { CartDrawer } from './CartDrawer';
import { useCart } from './useCart';
import { formatBRL } from '../lib/format';
import type { MenuPayload, MenuProduct } from './types';

interface Props {
    slug: string;
    isOpen: boolean;
    menu: MenuPayload;
}

/**
 * Ilha de interatividade do cardápio.
 *
 * O conteúdo do cardápio é HTML renderizado no servidor (bom para SEO e para
 * o primeiro carregamento). Este componente só cuida do carrinho, ouvindo os
 * cliques nos botões "Adicionar" via delegação de eventos.
 */
export function CartIsland({ slug, isOpen, menu }: Props) {
    const { items, products, add, changeQuantity, remove, clear, estimate } = useCart(slug, menu);
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [pending, setPending] = useState<MenuProduct | null>(null);
    const [flash, setFlash] = useState<string | null>(null);

    const announce = useCallback((text: string) => {
        setFlash(text);
        window.setTimeout(() => setFlash(null), 2000);
    }, []);

    useEffect(() => {
        const onClick = (event: MouseEvent) => {
            const target = (event.target as HTMLElement | null)?.closest<HTMLElement>('[data-add-product]');
            if (!target) return;

            event.preventDefault();

            const productId = Number(target.dataset.addProduct);
            const product = products.get(productId);
            if (!product) return;

            if (product.addon_groups.length > 0) {
                setPending(product);

                return;
            }

            add(productId);
            announce(`${product.name} adicionado`);
        };

        document.addEventListener('click', onClick);

        return () => document.removeEventListener('click', onClick);
    }, [products, add, announce]);

    return (
        <>
            {/* Região viva: leitores de tela anunciam cada item adicionado. */}
            <p role="status" aria-live="polite" className="sr-only">{flash}</p>

            {estimate.count > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-40 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <button
                        type="button"
                        onClick={() => setDrawerOpen(true)}
                        className="btn-primary mx-auto flex w-full max-w-2xl items-center justify-between px-5 py-3.5 shadow-lifted"
                    >
                        <span className="flex items-center gap-2">
                            <svg className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                <circle cx="8" cy="21" r="1" /><circle cx="19" cy="21" r="1" />
                                <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" />
                            </svg>
                            Ver pedido
                            <span className="rounded-full bg-white/25 px-2 py-0.5 text-xs font-bold">
                                {estimate.count}
                            </span>
                        </span>
                        <span className="font-bold">{formatBRL(estimate.cents)}</span>
                    </button>
                </div>
            )}

            {pending && (
                <AddonModal
                    product={pending}
                    onClose={() => setPending(null)}
                    onConfirm={(addonIds, notes, quantity) => {
                        add(pending.id, addonIds, notes, quantity);
                        announce(`${pending.name} adicionado`);
                        setPending(null);
                    }}
                />
            )}

            <CartDrawer
                slug={slug}
                items={items}
                products={products}
                estimateCents={estimate.cents}
                isOpen={drawerOpen && isOpen}
                onClose={() => setDrawerOpen(false)}
                onChangeQuantity={changeQuantity}
                onRemove={remove}
                onOrdered={clear}
            />
        </>
    );
}
