import { useState } from 'react';
import { api } from '../api';
import { useApp } from '../AppContext';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card, CardBody, CardHeader } from '../../components/ui/Card';
import { Input } from '../../components/ui/Field';
import { useToast } from '../../components/ui/Toast';

export function AccountPage() {
    const { user, setUser } = useApp();
    const toast = useToast();

    const [name, setName] = useState(user?.name ?? '');
    const [email, setEmail] = useState(user?.email ?? '');
    const [profileErrors, setProfileErrors] = useState<ApiErrors>({});
    const [savingProfile, setSavingProfile] = useState(false);

    const [currentPassword, setCurrentPassword] = useState('');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [passwordErrors, setPasswordErrors] = useState<ApiErrors>({});
    const [savingPassword, setSavingPassword] = useState(false);

    const [resending, setResending] = useState(false);

    const saveProfile = async (event: React.FormEvent) => {
        event.preventDefault();
        setSavingProfile(true);
        setProfileErrors({});

        try {
            setUser(await api.updateProfile({ name, email }));
            toast.success('Dados atualizados.');
        } catch (error) {
            const failure = toFailure(error);
            setProfileErrors(failure.errors);

            if (Object.keys(failure.errors).length === 0) toast.error(failure.message);
        } finally {
            setSavingProfile(false);
        }
    };

    const savePassword = async (event: React.FormEvent) => {
        event.preventDefault();
        setSavingPassword(true);
        setPasswordErrors({});

        try {
            await api.updatePassword({
                current_password: currentPassword,
                password,
                password_confirmation: confirmation,
            });

            setCurrentPassword('');
            setPassword('');
            setConfirmation('');
            toast.success('Senha alterada.');
        } catch (error) {
            const failure = toFailure(error);
            setPasswordErrors(failure.errors);

            if (Object.keys(failure.errors).length === 0) toast.error(failure.message);
        } finally {
            setSavingPassword(false);
        }
    };

    const resendVerification = async () => {
        setResending(true);

        try {
            toast.success(await api.resendVerification());
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setResending(false);
        }
    };

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">Conta</h1>
                <p className="mt-1 text-sm text-ink-muted">Seus dados de acesso à plataforma.</p>
            </div>

            <Card>
                <CardHeader
                    title="Dados pessoais"
                    action={user?.email_verified
                        ? <Badge tone="success">E-mail confirmado</Badge>
                        : <Badge tone="warning">E-mail não confirmado</Badge>}
                />
                <CardBody>
                    <form onSubmit={saveProfile} className="space-y-4" noValidate>
                        <Input label="Nome" value={name} required
                               onChange={(e) => setName(e.target.value)} error={fieldError(profileErrors, 'name')} />

                        <Input label="E-mail" type="email" value={email} required
                               hint="Alterar o e-mail exige uma nova confirmação."
                               onChange={(e) => setEmail(e.target.value)} error={fieldError(profileErrors, 'email')} />

                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" loading={savingProfile}>Salvar dados</Button>

                            {!user?.email_verified && (
                                <Button type="button" variant="secondary" loading={resending} onClick={resendVerification}>
                                    Reenviar confirmação
                                </Button>
                            )}
                        </div>
                    </form>
                </CardBody>
            </Card>

            <Card>
                <CardHeader title="Alterar senha" />
                <CardBody>
                    <form onSubmit={savePassword} className="space-y-4" noValidate>
                        <Input label="Senha atual" type="password" value={currentPassword} required
                               autoComplete="current-password"
                               onChange={(e) => setCurrentPassword(e.target.value)}
                               error={fieldError(passwordErrors, 'current_password')} />

                        <Input label="Nova senha" type="password" value={password} required
                               autoComplete="new-password"
                               hint="Mínimo de 8 caracteres, com letras e números."
                               onChange={(e) => setPassword(e.target.value)}
                               error={fieldError(passwordErrors, 'password')} />

                        <Input label="Confirmar nova senha" type="password" value={confirmation} required
                               autoComplete="new-password"
                               onChange={(e) => setConfirmation(e.target.value)} />

                        <Button type="submit" loading={savingPassword}>Alterar senha</Button>
                    </form>
                </CardBody>
            </Card>
        </div>
    );
}
