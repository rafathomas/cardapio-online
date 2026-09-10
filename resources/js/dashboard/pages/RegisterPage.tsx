import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AuthLayout } from './AuthLayout';
import { api } from '../api';
import { useApp } from '../AppContext';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Field';

export function RegisterPage() {
    const { setUser } = useApp();
    const navigate = useNavigate();

    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<ApiErrors>({});
    const [message, setMessage] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});
        setMessage(null);

        try {
            const user = await api.register({
                name,
                email,
                password,
                password_confirmation: confirmation,
            });

            setUser(user);
            // Conta criada leva direto para o onboarding do cardápio.
            navigate('/app/comecar', { replace: true });
        } catch (error) {
            const failure = toFailure(error);
            setErrors(failure.errors);
            setMessage(Object.keys(failure.errors).length === 0 ? failure.message : null);
            setLoading(false);
        }
    };

    return (
        <AuthLayout
            title="Criar conta"
            subtitle="Leva menos de um minuto. Não pedimos cartão de crédito."
            footer={<>Já tem conta? <Link to="/app/entrar" className="font-semibold text-brand hover:underline">Entrar</Link></>}
        >
            <form onSubmit={submit} className="space-y-4" noValidate>
                {message && (
                    <p role="alert" className="rounded-xl bg-danger-soft p-3 text-sm font-medium text-danger">{message}</p>
                )}

                <Input id="registro-nome" label="Seu nome" value={name} required autoComplete="name" autoFocus
                       onChange={(e) => setName(e.target.value)} error={fieldError(errors, 'name')} />

                <Input id="registro-email" label="E-mail" type="email" value={email} required autoComplete="email"
                       onChange={(e) => setEmail(e.target.value)} error={fieldError(errors, 'email')} />

                <Input id="registro-senha" label="Senha" type="password" value={password} required autoComplete="new-password"
                       hint="Mínimo de 8 caracteres, com letras e números."
                       onChange={(e) => setPassword(e.target.value)} error={fieldError(errors, 'password')} />

                <Input id="registro-senha-confirmacao" label="Confirmar senha" type="password" value={confirmation} required autoComplete="new-password"
                       onChange={(e) => setConfirmation(e.target.value)} />

                <Button type="submit" loading={loading} size="lg" className="w-full">Criar minha conta</Button>
            </form>
        </AuthLayout>
    );
}
