import type { ReactNode } from 'react';

export function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <div className={`card ${className}`}>{children}</div>;
}

export function CardHeader({ title, description, action }: {
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-line p-5">
            <div>
                <h2 className="font-display text-lg text-ink">{title}</h2>
                {description && <p className="mt-0.5 text-sm text-ink-muted">{description}</p>}
            </div>
            {action}
        </div>
    );
}

export function CardBody({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <div className={`p-5 ${className}`}>{children}</div>;
}
