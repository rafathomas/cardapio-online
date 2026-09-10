import { useCallback, useEffect, useMemo, useState } from 'react';
import type { CartItem, MenuPayload, MenuProduct } from './types';

/** Chave estável por combinação produto + adicionais + observação. */
function itemKey(productId: number, addonIds: number[], notes: string): string {
    return [productId, [...addonIds].sort((a, b) => a - b).join('.'), notes.trim()].join('|');
}

function storageKey(slug: string): string {
    return `cardapio:cart:${slug}`;
}

function readStored(slug: string): CartItem[] {
    try {
        const raw = localStorage.getItem(storageKey(slug));
        if (!raw) return [];

        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        // Modo privado ou storage bloqueado: o carrinho apenas não persiste.
        return [];
    }
}

export function useCart(slug: string, menu: MenuPayload) {
    const [items, setItems] = useState<CartItem[]>(() => readStored(slug));

    const products = useMemo(() => {
        const map = new Map<number, MenuProduct>();
        menu.categories.forEach((category) =>
            category.products.forEach((product) => map.set(product.id, product)),
        );

        return map;
    }, [menu]);

    // Descarta itens de produtos que saíram do cardápio desde a última visita.
    useEffect(() => {
        setItems((current) => current.filter((item) => products.has(item.productId)));
    }, [products]);

    useEffect(() => {
        try {
            localStorage.setItem(storageKey(slug), JSON.stringify(items));
        } catch {
            /* storage indisponível: seguimos apenas em memória */
        }
    }, [items, slug]);

    const add = useCallback((productId: number, addonIds: number[] = [], notes = '', quantity = 1) => {
        const key = itemKey(productId, addonIds, notes);

        setItems((current) => {
            const existing = current.find((item) => item.key === key);

            if (existing) {
                return current.map((item) =>
                    item.key === key
                        ? { ...item, quantity: Math.min(99, item.quantity + quantity) }
                        : item,
                );
            }

            return [...current, { key, productId, quantity, notes, addonIds }];
        });
    }, []);

    const changeQuantity = useCallback((key: string, delta: number) => {
        setItems((current) =>
            current
                .map((item) =>
                    item.key === key
                        ? { ...item, quantity: Math.min(99, item.quantity + delta) }
                        : item,
                )
                .filter((item) => item.quantity > 0),
        );
    }, []);

    const remove = useCallback((key: string) => {
        setItems((current) => current.filter((item) => item.key !== key));
    }, []);

    const clear = useCallback(() => setItems([]), []);

    /**
     * Total local, apenas para dar resposta instantânea na interface.
     * O valor que vale é sempre o recalculado pelo servidor.
     */
    const estimate = useMemo(() => {
        let cents = 0;
        let count = 0;

        for (const item of items) {
            const product = products.get(item.productId);
            if (!product) continue;

            const addons = product.addon_groups
                .flatMap((group) => group.addons)
                .filter((addon) => item.addonIds.includes(addon.id))
                .reduce((sum, addon) => sum + addon.price_cents, 0);

            cents += (product.price_cents + addons) * item.quantity;
            count += item.quantity;
        }

        return { cents, count };
    }, [items, products]);

    return { items, products, add, changeQuantity, remove, clear, estimate };
}
