import { useState } from 'react';
import { Link } from 'react-router-dom';
import { AuthLayout } from './AuthLayout';
import { api } from '../api';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Field';

export function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [sent, setSent] = useState<string | null>(null);
    const [errors, setErrors] = useState<ApiErrors>({});
    const [loading, setLoading] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            setSent(await api.forgotPassword(email));
        } catch (error) {
            const failure = toFailure(error);
            setErrors(failure.errors);

            if (failure.status === 429) {
                setErrors({ email: ['Muitas tentativas. Tente novamente em alguns minutos.'] });
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthLayout
            title="Recuperar senha"
            subtitle="Enviamos um link para você criar uma nova senha."
            footer={<Link to="/app/entrar" className="font-semibold text-brand hover:underline">Voltar para o login</Link>}
        >
            {sent ? (
                <p role="status" className="rounded-xl bg-success-soft p-4 text-sm font-medium text-success">{sent}</p>
            ) : (
                <form onSubmit={submit} className="space-y-4" noValidate>
                    <Input label="E-mail da conta" type="email" value={email} required autoFocus autoComplete="email"
                           onChange={(e) => setEmail(e.target.value)} error={fieldError(errors, 'email')} />

                    <Button type="submit" loading={loading} size="lg" className="w-full">Enviar link</Button>
                </form>
            )}
        </AuthLayout>
    );
}
