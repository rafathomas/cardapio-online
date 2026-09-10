import { useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../api';
import { useApp, useEstablishment } from '../AppContext';
import { toFailure } from '../../lib/http';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card, CardBody, CardHeader } from '../../components/ui/Card';
import { Icon } from '../../components/ui/Icon';
import { useToast } from '../../components/ui/Toast';

/** Visão geral do cardápio: link público, status e ações de publicação. */
export function MenuPage() {
    const establishment = useEstablishment();
    const { refreshEstablishments } = useApp();
    const toast = useToast();
    const [busy, setBusy] = useState(false);

    const publish = async () => {
        setBusy(true);

        try {
            const result = await api.publish(establishment.id);
            await refreshEstablishments();
            toast.success(result.message);
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setBusy(false);
        }
    };

    const unpublish = async () => {
        setBusy(true);

        try {
            await api.unpublish(establishment.id);
            await refreshEstablishments();
            toast.info('Cardápio despublicado. O link ficará indisponível.');
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setBusy(false);
        }
    };

    const share = async () => {
        const url = establishment.public_url;

        if (navigator.share) {
            try {
                await navigator.share({ title: establishment.name, url });

                return;
            } catch {
                // Usuário cancelou o compartilhamento nativo: cai para copiar.
            }
        }

        await navigator.clipboard.writeText(url);
        toast.success('Link copiado.');
    };

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">Cardápio</h1>
                <p className="mt-1 text-sm text-ink-muted">O endereço que você compartilha com seus clientes.</p>
            </div>

            <Card>
                <CardHeader
                    title="Status"
                    action={establishment.is_published ? <Badge tone="success">No ar</Badge> : <Badge tone="warning">Rascunho</Badge>}
                />
                <CardBody className="space-y-4">
                    <div className="rounded-xl border border-line bg-surface-muted p-4">
                        <p className="text-xs font-semibold tracking-wide text-ink-muted uppercase">Link público</p>
                        <p className="mt-1 break-all text-sm font-medium text-brand">{establishment.public_url}</p>
                    </div>

                    <div className="grid gap-2 sm:grid-cols-3">
                        <Button variant="secondary" icon="link" onClick={share}>Compartilhar</Button>

                        <a href={establishment.public_url} target="_blank" rel="noopener" className="btn-secondary">
                            <Icon name="eye" className="size-4" />
                            Visualizar
                        </a>

                        <Link to="/app/qrcode" className="btn-secondary">
                            <Icon name="qr" className="size-4" />
                            QR Code
                        </Link>
                    </div>

                    {establishment.is_published ? (
                        <Button variant="ghost" loading={busy} onClick={unpublish}>
                            Despublicar cardápio
                        </Button>
                    ) : (
                        <Button loading={busy} onClick={publish} className="w-full">
                            Publicar cardápio
                        </Button>
                    )}
                </CardBody>
            </Card>

            <div className="grid gap-4 sm:grid-cols-2">
                <Card>
                    <CardBody>
                        <div className="flex items-center gap-2 text-ink-muted">
                            <Icon name="grid" className="size-4" />
                            <span className="text-sm font-medium">Categorias</span>
                        </div>
                        <p className="mt-2 font-display text-2xl text-ink">{establishment.categories_count ?? 0}</p>
                        <Link to="/app/categorias" className="btn-ghost mt-2 px-0 text-sm text-brand">
                            Gerenciar categorias
                            <Icon name="chevron-right" className="size-4" />
                        </Link>
                    </CardBody>
                </Card>

                <Card>
                    <CardBody>
                        <div className="flex items-center gap-2 text-ink-muted">
                            <Icon name="package" className="size-4" />
                            <span className="text-sm font-medium">Produtos</span>
                        </div>
                        <p className="mt-2 font-display text-2xl text-ink">{establishment.products_count ?? 0}</p>
                        <Link to="/app/produtos" className="btn-ghost mt-2 px-0 text-sm text-brand">
                            Gerenciar produtos
                            <Icon name="chevron-right" className="size-4" />
                        </Link>
                    </CardBody>
                </Card>
            </div>
        </div>
    );
}
