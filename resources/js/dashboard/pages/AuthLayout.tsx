import type { ReactNode } from 'react';
import { Icon } from '../../components/ui/Icon';

export function AuthLayout({ title, subtitle, children, footer }: {
    title: string;
    subtitle: string;
    children: ReactNode;
    footer?: ReactNode;
}) {
    return (
        <div className="flex min-h-full flex-col items-center justify-center bg-surface-warm p-4">
            <div className="w-full max-w-md">
                <a href="/" className="mb-6 flex items-center justify-center gap-2">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-brand text-white">
                        <Icon name="store" />
                    </span>
                    <span className="font-display text-xl text-ink">Cardápio Online</span>
                </a>

                <div className="card p-6 sm:p-8">
                    <h1 className="font-display text-2xl text-ink">{title}</h1>
                    <p className="mt-1.5 text-sm text-ink-muted">{subtitle}</p>

                    <div className="mt-6">{children}</div>
                </div>

                {footer && <div className="mt-5 text-center text-sm text-ink-muted">{footer}</div>}
            </div>
        </div>
    );
}
