import { useEffect, useState } from 'react';
import { BrowserRouter, NavLink, Navigate, Route, Routes, useNavigate } from 'react-router-dom';
import { adminApi } from './api';
import { toFailure } from '../lib/http';
import { Icon, type IconName } from '../components/ui/Icon';
import { Spinner } from '../components/ui/Spinner';
import { ToastProvider } from '../components/ui/Toast';
import { MetricsPage } from './pages/MetricsPage';
import { UsersPage } from './pages/UsersPage';
import { EstablishmentsPage } from './pages/EstablishmentsPage';
import { PlansPage } from './pages/PlansPage';
import { BillingPage } from './pages/BillingPage';
import type { User } from '../dashboard/types';

const NAV: { to: string; label: string; icon: IconName; end?: boolean }[] = [
    { to: '/admin', label: 'Visão geral', icon: 'chart', end: true },
    { to: '/admin/usuarios', label: 'Usuários', icon: 'users' },
    { to: '/admin/estabelecimentos', label: 'Estabelecimentos', icon: 'store' },
    { to: '/admin/planos', label: 'Planos', icon: 'card' },
    { to: '/admin/financeiro', label: 'Financeiro', icon: 'receipt' },
];

function AdminShell() {
    const navigate = useNavigate();

    const signOut = async () => {
        await adminApi.logout().catch(() => undefined);
        window.location.href = '/app/entrar';
    };

    return (
        <div className="flex min-h-full">
            <aside className="hidden w-56 shrink-0 border-r border-line bg-surface lg:flex lg:flex-col">
                <div className="flex items-center gap-2 border-b border-line px-4 py-4">
                    <span className="flex size-9 items-center justify-center rounded-xl bg-ink text-white">
                        <Icon name="lock" className="size-4" />
                    </span>
                    <span className="font-display text-ink">Plataforma</span>
                </div>

                <nav className="flex-1 space-y-0.5 p-3" aria-label="Administração">
                    {NAV.map((item) => (
                        <NavLink
                            key={item.to}
                            to={item.to}
                            end={item.end}
                            className={({ isActive }) =>
                                `tap flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium ${
                                    isActive ? 'bg-ink text-white' : 'text-ink-muted hover:bg-surface-muted'
                                }`
                            }
                        >
                            <Icon name={item.icon} className="size-4" />
                            {item.label}
                        </NavLink>
                    ))}
                </nav>

                <div className="space-y-1 border-t border-line p-3">
                    <button type="button" onClick={() => navigate('/app')}
                            className="tap flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-ink-muted hover:bg-surface-muted">
                        <Icon name="arrow-left" className="size-4" />
                        Meu painel
                    </button>
                    <button type="button" onClick={signOut}
                            className="tap flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-ink-muted hover:bg-surface-muted">
                        <Icon name="logout" className="size-4" />
                        Sair
                    </button>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <nav className="flex gap-1 overflow-x-auto border-b border-line bg-surface px-3 py-2 lg:hidden"
                     aria-label="Administração (móvel)">
                    {NAV.map((item) => (
                        <NavLink key={item.to} to={item.to} end={item.end}
                                 className={({ isActive }) =>
                                     `tap rounded-lg px-3 py-1.5 text-sm font-medium whitespace-nowrap ${
                                         isActive ? 'bg-ink text-white' : 'text-ink-muted'
                                     }`
                                 }>
                            {item.label}
                        </NavLink>
                    ))}
                </nav>

                <main className="mx-auto w-full max-w-6xl flex-1 p-4 sm:p-6">
                    <Routes>
                        <Route path="/admin" element={<MetricsPage />} />
                        <Route path="/admin/usuarios" element={<UsersPage />} />
                        <Route path="/admin/estabelecimentos" element={<EstablishmentsPage />} />
                        <Route path="/admin/planos" element={<PlansPage />} />
                        <Route path="/admin/financeiro" element={<BillingPage />} />
                        <Route path="*" element={<Navigate to="/admin" replace />} />
                    </Routes>
                </main>
            </div>
        </div>
    );
}

export function AdminApp() {
    const [user, setUser] = useState<User | null>(null);
    const [state, setState] = useState<'loading' | 'ready' | 'denied'>('loading');

    useEffect(() => {
        adminApi.me()
            .then((loaded) => {
                setUser(loaded);
                setState(loaded.is_admin ? 'ready' : 'denied');
            })
            .catch((error) => {
                if (toFailure(error).status === 401) {
                    window.location.href = '/app/entrar';

                    return;
                }

                setState('denied');
            });
    }, []);

    if (state === 'loading') return <Spinner label="Verificando acesso" />;

    if (state === 'denied') {
        return (
            <div className="flex min-h-full flex-col items-center justify-center p-6 text-center">
                <span className="flex size-14 items-center justify-center rounded-2xl bg-danger-soft text-danger">
                    <Icon name="lock" className="size-6" />
                </span>
                <h1 className="mt-4 font-display text-xl text-ink">Acesso restrito</h1>
                <p className="mt-2 text-sm text-ink-muted">
                    {user ? 'Sua conta não tem permissão de administrador da plataforma.' : 'Faça login para continuar.'}
                </p>
                <a href="/app" className="btn-secondary mt-5">Ir para o meu painel</a>
            </div>
        );
    }

    return (
        <BrowserRouter>
            <ToastProvider>
                <AdminShell />
            </ToastProvider>
        </BrowserRouter>
    );
}
