import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { AuthLayout } from './AuthLayout';
import { api } from '../api';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Field';

export function ResetPasswordPage() {
    const { token = '' } = useParams();
    const [params] = useSearchParams();
    const navigate = useNavigate();

    const [email, setEmail] = useState(params.get('email') ?? '');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<ApiErrors>({});
    const [loading, setLoading] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            await api.resetPassword({
                token,
                email,
                password,
                password_confirmation: confirmation,
            });

            navigate('/app/entrar?senha-redefinida=1', { replace: true });
        } catch (error) {
            setErrors(toFailure(error).errors);
            setLoading(false);
        }
    };

    return (
        <AuthLayout
            title="Nova senha"
            subtitle="Escolha uma senha para voltar a acessar sua conta."
            footer={<Link to="/app/entrar" className="font-semibold text-brand hover:underline">Voltar para o login</Link>}
        >
            <form onSubmit={submit} className="space-y-4" noValidate>
                <Input label="E-mail" type="email" value={email} required autoComplete="email"
                       onChange={(e) => setEmail(e.target.value)} error={fieldError(errors, 'email')} />

                <Input label="Nova senha" type="password" value={password} required autoComplete="new-password"
                       hint="Mínimo de 8 caracteres, com letras e números."
                       onChange={(e) => setPassword(e.target.value)} error={fieldError(errors, 'password')} />

                <Input label="Confirmar nova senha" type="password" value={confirmation} required autoComplete="new-password"
                       onChange={(e) => setConfirmation(e.target.value)} />

                <Button type="submit" loading={loading} size="lg" className="w-full">Redefinir senha</Button>
            </form>
        </AuthLayout>
    );
}
