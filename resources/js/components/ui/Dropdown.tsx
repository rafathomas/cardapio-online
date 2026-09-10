import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Icon, type IconName } from './Icon';

export interface DropdownItem {
    label: string;
    icon?: IconName;
    destructive?: boolean;
    onSelect: () => void;
}

export function Dropdown({ label, items, align = 'right' }: {
    label: ReactNode;
    items: DropdownItem[];
    align?: 'left' | 'right';
}) {
    const [open, setOpen] = useState(false);
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        const onOutside = (event: MouseEvent) => {
            if (!container.current?.contains(event.target as Node)) setOpen(false);
        };
        const onEscape = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);

        document.addEventListener('mousedown', onOutside);
        document.addEventListener('keydown', onEscape);

        return () => {
            document.removeEventListener('mousedown', onOutside);
            document.removeEventListener('keydown', onEscape);
        };
    }, [open]);

    return (
        <div className="relative" ref={container}>
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-haspopup="menu"
                className="btn-secondary px-3 py-2"
            >
                {label}
                <Icon name="chevron-down" className="size-4" />
            </button>

            {open && (
                <div
                    role="menu"
                    className={`animate-rise absolute z-30 mt-1 min-w-48 rounded-xl border border-line bg-surface p-1 shadow-lifted ${
                        align === 'right' ? 'right-0' : 'left-0'
                    }`}
                >
                    {items.map((item) => (
                        <button
                            key={item.label}
                            type="button"
                            role="menuitem"
                            onClick={() => {
                                setOpen(false);
                                item.onSelect();
                            }}
                            className={`tap flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm ${
                                item.destructive ? 'text-danger hover:bg-danger-soft' : 'text-ink hover:bg-surface-muted'
                            }`}
                        >
                            {item.icon && <Icon name={item.icon} className="size-4" />}
                            {item.label}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
