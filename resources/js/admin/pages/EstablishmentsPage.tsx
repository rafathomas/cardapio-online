import { useCallback, useEffect, useState } from 'react';
import { adminApi } from '../api';
import { toFailure } from '../../lib/http';
import { Badge } from '../../components/ui/Badge';
import { Card, CardHeader } from '../../components/ui/Card';
import { Spinner } from '../../components/ui/Spinner';
import { Table, Td, Th } from '../../components/ui/Table';
import { useToast } from '../../components/ui/Toast';
import type { Establishment } from '../../dashboard/types';

type Row = Establishment & { owner: { id: number; name: string; email: string } };

export function EstablishmentsPage() {
    const toast = useToast();
    const [rows, setRows] = useState<Row[]>([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(true);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            const response = await adminApi.establishments(search ? { search } : {});
            setRows(response.data);
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

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">Estabelecimentos</h1>
                <p className="mt-1 text-sm text-ink-muted">Todos os cardápios criados na plataforma.</p>
            </div>

            <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar por nome ou link"
                aria-label="Buscar estabelecimento"
                className="field max-w-md"
            />

            <Card>
                <CardHeader title={`${rows.length} estabelecimento(s)`} />

                {loading ? (
                    <Spinner />
                ) : (
                    <Table
                        caption="Estabelecimentos da plataforma"
                        head={<><Th>Nome</Th><Th>Dono</Th><Th>Plano</Th><Th>Produtos</Th><Th>Status</Th><Th /></>}
                    >
                        {rows.map((row) => (
                            <tr key={row.id}>
                                <Td>
                                    <p className="font-medium text-ink">{row.name}</p>
                                    <p className="text-xs text-ink-muted">/{row.slug}</p>
                                </Td>
                                <Td className="text-ink-muted">
                                    <p>{row.owner.name}</p>
                                    <p className="text-xs">{row.owner.email}</p>
                                </Td>
                                <Td>{row.subscription?.plan?.name ?? 'Free'}</Td>
                                <Td>{row.products_count ?? 0}</Td>
                                <Td>
                                    {row.is_published
                                        ? <Badge tone="success">No ar</Badge>
                                        : <Badge tone="warning">Rascunho</Badge>}
                                </Td>
                                <Td>
                                    {row.is_published && (
                                        <a href={row.public_url} target="_blank" rel="noopener"
                                           className="text-sm font-semibold text-brand hover:underline">
                                            Abrir
                                        </a>
                                    )}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                )}
            </Card>
        </div>
    );
}
