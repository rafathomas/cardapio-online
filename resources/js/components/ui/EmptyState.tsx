import type { ReactNode } from 'react';
import { Icon, type IconName } from './Icon';

export function EmptyState({ icon, title, description, action }: {
    icon: IconName;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center px-6 py-14 text-center">
            <span className="flex size-14 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                <Icon name={icon} className="size-6" />
            </span>
            <h3 className="mt-4 font-display text-lg text-ink">{title}</h3>
            <p className="mt-1.5 max-w-sm text-sm text-ink-muted">{description}</p>
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
