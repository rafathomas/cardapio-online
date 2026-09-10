import type { ReactNode } from 'react';

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'brand' | 'info';

const TONES: Record<Tone, string> = {
    neutral: 'bg-surface-muted text-ink-muted',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    brand: 'bg-brand-soft text-brand-strong',
    info: 'bg-sky-100 text-sky-800',
};

export function Badge({ tone = 'neutral', children }: { tone?: Tone; children: ReactNode }) {
    return <span className={`badge ${TONES[tone]}`}>{children}</span>;
}

export type { Tone };
