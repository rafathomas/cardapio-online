import { useEffect, useId, useRef, type ReactNode } from 'react';
import { Icon } from './Icon';

interface Props {
    open: boolean;
    title: string;
    description?: string;
    onClose: () => void;
    children: ReactNode;
    footer?: ReactNode;
    size?: 'sm' | 'md' | 'lg';
}

const SIZES = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' };

export function Modal({ open, title, description, onClose, children, footer, size = 'md' }: Props) {
    const titleId = useId();
    const panelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        const previouslyFocused = document.activeElement as HTMLElement | null;
        panelRef.current?.focus();
        document.body.style.overflow = 'hidden';

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();

                return;
            }

            // Mantém o Tab dentro do modal.
            if (event.key !== 'Tab' || !panelRef.current) return;

            const focusable = panelRef.current.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            );

            if (focusable.length === 0) return;

            const first = focusable[0]!;
            const last = focusable[focusable.length - 1]!;

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('keydown', onKeyDown);
            document.body.style.overflow = '';
            previouslyFocused?.focus();
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
            onClick={(event) => event.target === event.currentTarget && onClose()}
        >
            <div
                ref={panelRef}
                tabIndex={-1}
                role="dialog"
                aria-modal="true"
                aria-labelledby={titleId}
                className={`animate-rise flex max-h-[92dvh] w-full ${SIZES[size]} flex-col rounded-t-2xl bg-surface shadow-lifted sm:rounded-2xl`}
            >
                <header className="flex items-start justify-between gap-3 border-b border-line p-5">
                    <div>
                        <h2 id={titleId} className="font-display text-lg text-ink">{title}</h2>
                        {description && <p className="mt-0.5 text-sm text-ink-muted">{description}</p>}
                    </div>
                    <button type="button" onClick={onClose} className="btn-ghost -mt-1 -mr-1 px-2" aria-label="Fechar">
                        <Icon name="x" />
                    </button>
                </header>

                <div className="flex-1 overflow-y-auto p-5">{children}</div>

                {footer && <footer className="flex justify-end gap-2 border-t border-line p-5">{footer}</footer>}
            </div>
        </div>
    );
}
