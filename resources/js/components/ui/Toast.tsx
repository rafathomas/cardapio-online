import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
import { Icon } from './Icon';

type ToastTone = 'success' | 'error' | 'info';

interface Toast {
    id: number;
    tone: ToastTone;
    message: string;
}

interface ToastApi {
    success: (message: string) => void;
    error: (message: string) => void;
    info: (message: string) => void;
}

const ToastContext = createContext<ToastApi | null>(null);

const TONES: Record<ToastTone, { classes: string; icon: 'check' | 'alert' | 'info' }> = {
    success: { classes: 'bg-success text-white', icon: 'check' },
    error: { classes: 'bg-danger text-white', icon: 'alert' },
    info: { classes: 'bg-ink text-white', icon: 'info' },
};

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<Toast[]>([]);

    const push = useCallback((tone: ToastTone, message: string) => {
        const id = Date.now() + Math.random();

        setToasts((current) => [...current, { id, tone, message }]);
        window.setTimeout(() => {
            setToasts((current) => current.filter((toast) => toast.id !== id));
        }, 5000);
    }, []);

    const api = useMemo<ToastApi>(() => ({
        success: (message) => push('success', message),
        error: (message) => push('error', message),
        info: (message) => push('info', message),
    }), [push]);

    return (
        <ToastContext.Provider value={api}>
            {children}

            <div
                className="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:inset-x-auto sm:right-0 sm:items-end"
                role="region"
                aria-label="Notificações"
            >
                {toasts.map((toast) => (
                    <div
                        key={toast.id}
                        role="status"
                        aria-live="polite"
                        className={`animate-rise pointer-events-auto flex w-full max-w-sm items-start gap-2.5 rounded-xl px-4 py-3 text-sm font-medium shadow-lifted ${TONES[toast.tone].classes}`}
                    >
                        <Icon name={TONES[toast.tone].icon} className="mt-0.5 size-4 shrink-0" />
                        <span className="flex-1">{toast.message}</span>
                        <button
                            type="button"
                            onClick={() => setToasts((current) => current.filter((t) => t.id !== toast.id))}
                            aria-label="Dispensar"
                            className="cursor-pointer opacity-70 hover:opacity-100"
                        >
                            <Icon name="x" className="size-4" />
                        </button>
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast(): ToastApi {
    const context = useContext(ToastContext);

    if (!context) {
        throw new Error('useToast precisa estar dentro de <ToastProvider>.');
    }

    return context;
}
