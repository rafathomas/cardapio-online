import { useCallback, useEffect, useState } from 'react';
import { adminApi, type AdminUser } from '../api';
import { toFailure } from '../../lib/http';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card, CardHeader } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Spinner } from '../../components/ui/Spinner';
import { Table, Td, Th } from '../../components/ui/Table';
import { useToast } from '../../components/ui/Toast';

export function UsersPage() {
    const toast = useToast();
    const [users, setUsers] = useState<AdminUser[]>([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);
    const [blocking, setBlocking] = useState<AdminUser | null>(null);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            const response = await adminApi.users(search ? { search } : {});
            setUsers(response.data);
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setLoading(false);
        }
    }, [search, toast]);

    useEffect(() => {
        const timer = window.setTimeout(load, search ? 300 : 0);

        return () => window.clearTimeout(timer);
    }, [load, search]);

    const toggleBlock = async (user: AdminUser) => {
        setBusy(true);

        try {
            if (user.blocked) {
                await adminApi.unblockUser(user.id);
                toast.success('Usuário desbloqueado.');
            } else {
                await adminApi.blockUser(user.id);
                toast.success('Usuário bloqueado.');
            }

            setBlocking(null);
            load();
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">Usuários</h1>
                <p className="mt-1 text-sm text-ink-muted">Contas cadastradas na plataforma.</p>
            </div>

            <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar por nome ou e-mail"
                aria-label="Buscar usuário"
                className="field max-w-md"
            />

            <Card>
                <CardHeader title={`${users.length} usuário(s)`} />

                {loading ? (
                    <Spinner />
                ) : (
                    <Table
                        caption="Usuários cadastrados"
                        head={<><Th>Nome</Th><Th>E-mail</Th><Th>Estabelecimentos</Th><Th>Status</Th><Th /></>}
                    >
                        {users.map((user) => (
                            <tr key={user.id}>
                                <Td className="font-medium">{user.name}</Td>
                                <Td className="text-ink-muted">{user.email}</Td>
                                <Td>{user.establishments_count ?? 0}</Td>
                                <Td>
                                    {user.is_admin ? (
                                        <Badge tone="info">Administrador</Badge>
                                    ) : user.blocked ? (
                                        <Badge tone="danger">Bloqueado</Badge>
                                    ) : (
                                        <Badge tone="success">Ativo</Badge>
                                    )}
                                </Td>
                                <Td>
                                    {!user.is_admin && (
                                        <Button
                                            variant={user.blocked ? 'secondary' : 'ghost'}
                                            size="sm"
                                            onClick={() => setBlocking(user)}
                                        >
                                            {user.blocked ? 'Desbloquear' : 'Bloquear'}
                                        </Button>
                                    )}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>

            <ConfirmDialog
                open={blocking !== null}
                title={blocking?.blocked ? 'Desbloquear usuário' : 'Bloquear usuário'}
                message={blocking?.blocked
                    ? `${blocking.name} volta a acessar o painel.`
                    : `${blocking?.name} perde o acesso ao painel imediatamente. Os cardápios publicados continuam no ar.`}
                confirmLabel={blocking?.blocked ? 'Desbloquear' : 'Bloquear'}
                destructive={!blocking?.blocked}
                loading={busy}
                onConfirm={() => blocking && toggleBlock(blocking)}
                onCancel={() => setBlocking(null)}
            />
        </div>
    );
}
