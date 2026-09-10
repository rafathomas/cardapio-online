import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useApp } from '../AppContext';
import { Icon, type IconName } from '../../components/ui/Icon';
import { Badge } from '../../components/ui/Badge';

interface NavItem {
    to: string;
    label: string;
    icon: IconName;
    end?: boolean;
}

const NAV: NavItem[] = [
    { to: '/app', label: 'Dashboard', icon: 'grid', end: true },
    { to: '/app/cardapio', label: 'Cardápio', icon: 'store' },
    { to: '/app/categorias', label: 'Categorias', icon: 'list' },
    { to: '/app/produtos', label: 'Produtos', icon: 'package' },
    { to: '/app/adicionais', label: 'Adicionais', icon: 'sparkles' },
    { to: '/app/pedidos', label: 'Pedidos', icon: 'receipt' },
    { to: '/app/personalizacao', label: 'Personalização', icon: 'palette' },
    { to: '/app/qrcode', label: 'QR Code', icon: 'qr' },
    { to: '/app/assinatura', label: 'Assinatura', icon: 'card' },
    { to: '/app/configuracoes', label: 'Configurações', icon: 'settings' },
    { to: '/app/conta', label: 'Conta', icon: 'user' },
];

export function AppShell() {
    const { user, current, establishments, selectEstablishment, signOut } = useApp();
    const [menuOpen, setMenuOpen] = useState(false);
    const navigate = useNavigate();

    const handleSignOut = async () => {
        await signOut();
        navigate('/app/entrar', { replace: true });
    };

    const navLinkClass = ({ isActive }: { isActive: boolean }) =>
        `tap flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium ${
            isActive ? 'bg-brand-soft text-brand-strong' : 'text-ink-muted hover:bg-surface-muted hover:text-ink'
        }`;

    return (
        <div className="flex min-h-full">
            {/* Navegação lateral (desktop) */}
            <aside className="hidden w-60 shrink-0 border-r border-line bg-surface lg:flex lg:flex-col">
                <div className="flex items-center gap-2 border-b border-line px-4 py-4">
                    <span className="flex size-9 items-center justify-center rounded-xl bg-brand text-white">
                        <Icon name="store" className="size-5" />
                    </span>
                    <span className="font-display text-lg text-ink">Cardápio</span>
                </div>

                <nav className="flex-1 space-y-0.5 overflow-y-auto p-3" aria-label="Painel">
                    {NAV.map((item) => (
                        <NavLink key={item.to} to={item.to} end={item.end} className={navLinkClass}>
                            <Icon name={item.icon} className="size-4" />
                            {item.label}
                        </NavLink>
                    ))}
                </nav>

                <div className="border-t border-line p-3">
                    <button type="button" onClick={handleSignOut}
                            className="tap flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-muted hover:bg-surface-muted hover:text-ink">
                        <Icon name="logout" className="size-4" />
                        Sair
                    </button>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                {/* Barra superior */}
                <header className="sticky top-0 z-30 flex items-center gap-3 border-b border-line bg-surface/95 px-4 py-3 backdrop-blur">
                    <button type="button" onClick={() => setMenuOpen(true)}
                            className="btn-ghost px-2 lg:hidden" aria-label="Abrir menu">
                        <Icon name="menu" />
                    </button>

                    <div className="min-w-0 flex-1">
                        {current && (
                            <div className="flex items-center gap-2">
                                {establishments.length > 1 ? (
                                    <select
                                        value={current.id}
                                        onChange={(event) => selectEstablishment(Number(event.target.value))}
                                        aria-label="Trocar de estabelecimento"
                                        className="max-w-52 truncate rounded-lg border border-line bg-surface px-2 py-1 text-sm font-semibold"
                                    >
                                        {establishments.map((item) => (
                                            <option key={item.id} value={item.id}>{item.name}</option>
                                        ))}
                                    </select>
                                ) : (
                                    <p className="truncate font-semibold text-ink">{current.name}</p>
                                )}

                                {current.is_published ? (
                                    <Badge tone="success">No ar</Badge>
                                ) : (
                                    <Badge tone="warning">Rascunho</Badge>
                                )}
                            </div>
                        )}
                    </div>

                    {current?.is_published && (
                        <a href={current.public_url} target="_blank" rel="noopener"
                           className="btn-secondary px-3 py-2 text-sm">
                            <Icon name="external" className="size-4" />
                            <span className="hidden sm:inline">Ver cardápio</span>
                        </a>
                    )}
                </header>

                {!user?.email_verified && (
                    <div className="border-b border-warning/30 bg-warning-soft px-4 py-2.5 text-sm text-warning">
                        Confirme seu e-mail para publicar o cardápio.{' '}
                        <NavLink to="/app/conta" className="font-semibold underline">Reenviar confirmação</NavLink>
                    </div>
                )}

                <main className="mx-auto w-full max-w-5xl flex-1 p-4 pb-20 sm:p-6">
                    <Outlet />
                </main>
            </div>

            {/* Navegação lateral (mobile) */}
            {menuOpen && (
                <div className="fixed inset-0 z-50 bg-black/50 lg:hidden"
                     onClick={(event) => event.target === event.currentTarget && setMenuOpen(false)}>
                    <div className="animate-rise flex h-full w-64 flex-col bg-surface">
                        <div className="flex items-center justify-between border-b border-line px-4 py-4">
                            <span className="font-display text-lg text-ink">Cardápio</span>
                            <button type="button" onClick={() => setMenuOpen(false)}
                                    className="btn-ghost px-2" aria-label="Fechar menu">
                                <Icon name="x" />
                            </button>
                        </div>

                        <nav className="flex-1 space-y-0.5 overflow-y-auto p-3" aria-label="Painel">
                            {NAV.map((item) => (
                                <NavLink key={item.to} to={item.to} end={item.end}
                                         onClick={() => setMenuOpen(false)} className={navLinkClass}>
                                    <Icon name={item.icon} className="size-4" />
                                    {item.label}
                                </NavLink>
                            ))}
                        </nav>

                        <div className="border-t border-line p-3">
                            <button type="button" onClick={handleSignOut}
                                    className="tap flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-muted">
                                <Icon name="logout" className="size-4" />
                                Sair
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
