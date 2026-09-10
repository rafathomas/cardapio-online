import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthLayout } from './AuthLayout';
import { api } from '../api';
import { useApp } from '../AppContext';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Field';

export function LoginPage() {
    const { setUser, refreshEstablishments } = useApp();
    const navigate = useNavigate();

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [errors, setErrors] = useState<ApiErrors>({});
    const [message, setMessage] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});
        setMessage(null);

        try {
            const user = await api.login({ email, password, remember });
            setUser(user);
            const list = await refreshEstablishments();

            navigate(list.length === 0 ? '/app/comecar' : '/app', { replace: true });
        } catch (error) {
            const failure = toFailure(error);
            setErrors(failure.errors);
            setMessage(failure.status === 429
                ? 'Muitas tentativas. Aguarde um minuto e tente de novo.'
                : Object.keys(failure.errors).length === 0 ? failure.message : null);
            setLoading(false);
        }
    };

    return (
        <AuthLayout
            title="Entrar"
            subtitle="Acesse o painel do seu cardápio."
            footer={<>Ainda não tem conta? <Link to="/app/criar-conta" className="font-semibold text-brand hover:underline">Criar grátis</Link></>}
        >
            <form onSubmit={submit} className="space-y-4" noValidate>
                {message && (
                    <p role="alert" className="rounded-xl bg-danger-soft p-3 text-sm font-medium text-danger">{message}</p>
                )}

                <Input
                    id="login-email" label="E-mail" type="email" value={email} required autoComplete="email" autoFocus
                    onChange={(e) => setEmail(e.target.value)} error={fieldError(errors, 'email')}
                />

                <Input
                    id="login-senha" label="Senha" type="password" value={password} required autoComplete="current-password"
                    onChange={(e) => setPassword(e.target.value)} error={fieldError(errors, 'password')}
                />

                <div className="flex items-center justify-between">
                    <label className="tap flex items-center gap-2 text-sm text-ink-muted">
                        <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)}
                               className="size-4 accent-[var(--color-brand)]" />
                        Manter conectado
                    </label>

                    <Link to="/app/recuperar-senha" className="text-sm font-semibold text-brand hover:underline">
                        Esqueci a senha
                    </Link>
                </div>

                <Button type="submit" loading={loading} size="lg" className="w-full">Entrar</Button>
            </form>
        </AuthLayout>
    );
}
