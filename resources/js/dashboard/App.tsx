import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { AppProvider, useApp } from './AppContext';
import { AppShell } from './layout/AppShell';
import { ToastProvider } from '../components/ui/Toast';
import { Spinner } from '../components/ui/Spinner';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';
import { ForgotPasswordPage } from './pages/ForgotPasswordPage';
import { ResetPasswordPage } from './pages/ResetPasswordPage';
import { OnboardingPage } from './pages/OnboardingPage';
import { DashboardPage } from './pages/DashboardPage';
import { MenuPage } from './pages/MenuPage';
import { CategoriesPage } from './pages/CategoriesPage';
import { ProductsPage } from './pages/ProductsPage';
import { AddonsPage } from './pages/AddonsPage';
import { OrdersPage } from './pages/OrdersPage';
import { CustomizationPage } from './pages/CustomizationPage';
import { QrCodePage } from './pages/QrCodePage';
import { SubscriptionPage } from './pages/SubscriptionPage';
import { SettingsPage } from './pages/SettingsPage';
import { AccountPage } from './pages/AccountPage';

/** Rotas do painel: exigem sessão e ao menos um estabelecimento. */
function RequireEstablishment() {
    const { user, current, booting } = useApp();
    const location = useLocation();

    if (booting) return <Spinner label="Carregando painel" />;

    if (!user) {
        return <Navigate to="/app/entrar" replace state={{ from: location.pathname }} />;
    }

    if (!current) {
        return <Navigate to="/app/comecar" replace />;
    }

    return <AppShell />;
}

/** Rotas públicas: quem já está logado não precisa vê-las. */
function GuestOnly({ children }: { children: React.ReactElement }) {
    const { user, current, booting } = useApp();

    if (booting) return <Spinner />;
    if (user) return <Navigate to={current ? '/app' : '/app/comecar'} replace />;

    return children;
}

function RequireAuth({ children }: { children: React.ReactElement }) {
    const { user, booting } = useApp();

    if (booting) return <Spinner />;
    if (!user) return <Navigate to="/app/entrar" replace />;

    return children;
}

export function App() {
    return (
        <BrowserRouter>
            <ToastProvider>
                <AppProvider>
                    <Routes>
                        {/* Rotas de autenticação */}
                        <Route path="/app/entrar" element={<GuestOnly><LoginPage /></GuestOnly>} />
                        <Route path="/app/criar-conta" element={<GuestOnly><RegisterPage /></GuestOnly>} />
                        <Route path="/app/recuperar-senha" element={<GuestOnly><ForgotPasswordPage /></GuestOnly>} />
                        <Route path="/redefinir-senha/:token" element={<ResetPasswordPage />} />

                        {/* Compatibilidade com a rota nomeada `login` do Laravel */}
                        <Route path="/login" element={<Navigate to="/app/entrar" replace />} />

                        {/* Onboarding: exige sessão, mas ainda não tem estabelecimento */}
                        <Route path="/app/comecar" element={<RequireAuth><OnboardingPage /></RequireAuth>} />

                        {/* Painel */}
                        <Route element={<RequireEstablishment />}>
                            <Route path="/app" element={<DashboardPage />} />
                            <Route path="/app/cardapio" element={<MenuPage />} />
                            <Route path="/app/categorias" element={<CategoriesPage />} />
                            <Route path="/app/produtos" element={<ProductsPage />} />
                            <Route path="/app/adicionais" element={<AddonsPage />} />
                            <Route path="/app/pedidos" element={<OrdersPage />} />
                            <Route path="/app/personalizacao" element={<CustomizationPage />} />
                            <Route path="/app/qrcode" element={<QrCodePage />} />
                            <Route path="/app/assinatura" element={<SubscriptionPage />} />
                            <Route path="/app/configuracoes" element={<SettingsPage />} />
                            <Route path="/app/conta" element={<AccountPage />} />
                        </Route>

                        <Route path="*" element={<Navigate to="/app" replace />} />
                    </Routes>
                </AppProvider>
            </ToastProvider>
        </BrowserRouter>
    );
}
