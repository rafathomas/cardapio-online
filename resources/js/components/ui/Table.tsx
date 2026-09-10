import type { ReactNode } from 'react';

/** Tabela com rolagem horizontal contida: nunca estoura a viewport. */
export function Table({ head, children, caption }: {
    head: ReactNode;
    children: ReactNode;
    caption?: string;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[36rem] text-left text-sm">
                {caption && <caption className="sr-only">{caption}</caption>}
                <thead className="border-b border-line bg-surface-muted text-xs font-semibold tracking-wide text-ink-muted uppercase">
                    <tr>{head}</tr>
                </thead>
                <tbody className="divide-y divide-line">{children}</tbody>
            </table>
        </div>
    );
}

export function Th({ children, className = '' }: { children?: ReactNode; className?: string }) {
    return <th scope="col" className={`px-4 py-3 ${className}`}>{children}</th>;
}

export function Td({ children, className = '' }: { children?: ReactNode; className?: string }) {
    return <td className={`px-4 py-3 ${className}`}>{children}</td>;
}
