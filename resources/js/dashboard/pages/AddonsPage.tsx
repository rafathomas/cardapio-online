import { useCallback, useEffect, useState } from 'react';
import { api } from '../api';
import { useEstablishment } from '../AppContext';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card, CardHeader } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { EmptyState } from '../../components/ui/EmptyState';
import { Input, Toggle } from '../../components/ui/Field';
import { Icon } from '../../components/ui/Icon';
import { Modal } from '../../components/ui/Modal';
import { Spinner } from '../../components/ui/Spinner';
import { useToast } from '../../components/ui/Toast';
import type { AddonGroup } from '../types';

interface DraftAddon {
    name: string;
    price: string;
}

export function AddonsPage() {
    const establishment = useEstablishment();
    const toast = useToast();

    const [groups, setGroups] = useState<AddonGroup[]>([]);
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState<AddonGroup | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<AddonGroup | null>(null);
    const [busy, setBusy] = useState(false);

    const isPro = !(establishment.subscription?.plan?.is_free ?? true);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            setGroups(await api.addonGroups(establishment.id));
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setLoading(false);
        }
    }, [establishment.id, toast]);

    useEffect(() => {
        load();
    }, [load]);

    const remove = async () => {
        if (!deleting) return;

        setBusy(true);

        try {
            await api.deleteAddonGroup(establishment.id, deleting.id);
            toast.success('Grupo removido.');
            setDeleting(null);
            load();
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="font-display text-2xl text-ink">Adicionais</h1>
                    <p className="mt-1 text-sm text-ink-muted">
                        Queijo extra, borda recheada, ponto da carne. Vincule um grupo ao produto na tela de Produtos.
                    </p>
                </div>
                <Button icon="plus" onClick={() => setCreating(true)} disabled={!isPro}>
                    Novo grupo
                </Button>
            </div>

            {!isPro && (
                <div className="card flex flex-wrap items-center justify-between gap-3 border-warning/30 bg-warning-soft p-4">
                    <p className="text-sm font-medium text-warning">
                        Adicionais fazem parte do plano Pro.
                    </p>
                    <a href="/app/assinatura" className="btn-primary py-2 text-sm">Conhecer o Pro</a>
                </div>
            )}

            <Card>
                <CardHeader title={`${groups.length} grupo(s)`} />

                {loading ? (
                    <Spinner />
                ) : groups.length === 0 ? (
                    <EmptyState
                        icon="sparkles"
                        title="Nenhum grupo de adicionais"
                        description="Crie um grupo com as opções que o cliente pode escolher ao pedir um produto."
                        action={<Button icon="plus" onClick={() => setCreating(true)} disabled={!isPro}>Criar grupo</Button>}
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {groups.map((group) => (
                            <li key={group.id} className="p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="flex flex-wrap items-center gap-2 font-medium text-ink">
                                            {group.name}
                                            {group.is_required
                                                ? <Badge tone="brand">Obrigatório</Badge>
                                                : <Badge>Opcional</Badge>}
                                            {!group.is_active && <Badge tone="warning">Inativo</Badge>}
                                        </p>
                                        <p className="text-sm text-ink-muted">
                                            Escolha de {group.min_options} a {group.max_options} opção(ões)
                                        </p>
                                    </div>

                                    <div className="flex gap-1">
                                        <Button variant="ghost" size="sm" icon="edit" onClick={() => setEditing(group)}>
                                            <span className="sr-only sm:not-sr-only">Editar</span>
                                        </Button>
                                        <Button variant="ghost" size="sm" icon="trash" onClick={() => setDeleting(group)}>
                                            <span className="sr-only">Excluir {group.name}</span>
                                        </Button>
                                    </div>
                                </div>

                                {(group.addons?.length ?? 0) > 0 && (
                                    <ul className="mt-3 flex flex-wrap gap-2">
                                        {group.addons!.map((addon) => (
                                            <li key={addon.id}
                                                className="rounded-full border border-line px-3 py-1 text-sm text-ink-muted">
                                                {addon.name}
                                                {addon.price_cents > 0 && (
                                                    <span className="font-semibold"> +{addon.price_formatted}</span>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <AddonGroupForm
                open={creating || editing !== null}
                group={editing}
                establishmentId={establishment.id}
                onClose={() => { setCreating(false); setEditing(null); }}
                onSaved={() => { setCreating(false); setEditing(null); load(); }}
            />

            <ConfirmDialog
                open={deleting !== null}
                title="Excluir grupo de adicionais"
                message={`"${deleting?.name}" e suas opções serão removidos dos produtos vinculados.`}
                confirmLabel="Excluir"
                destructive
                loading={busy}
                onConfirm={remove}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}

function AddonGroupForm({ open, group, establishmentId, onClose, onSaved }: {
    open: boolean;
    group: AddonGroup | null;
    establishmentId: number;
    onClose: () => void;
    onSaved: () => void;
}) {
    const toast = useToast();
    const [name, setName] = useState('');
    const [isRequired, setIsRequired] = useState(false);
    const [minOptions, setMinOptions] = useState('0');
    const [maxOptions, setMaxOptions] = useState('1');
    const [isActive, setIsActive] = useState(true);
    const [addons, setAddons] = useState<DraftAddon[]>([{ name: '', price: '0' }]);
    const [errors, setErrors] = useState<ApiErrors>({});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) return;

        setName(group?.name ?? '');
        setIsRequired(group?.is_required ?? false);
        setMinOptions(String(group?.min_options ?? 0));
        setMaxOptions(String(group?.max_options ?? 1));
        setIsActive(group?.is_active ?? true);
        setAddons(
            group?.addons?.length
                ? group.addons.map((addon) => ({
                    name: addon.name,
                    price: (addon.price_cents / 100).toFixed(2).replace('.', ','),
                }))
                : [{ name: '', price: '0' }],
        );
        setErrors({});
    }, [open, group]);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});

        const payload = {
            name,
            is_required: isRequired,
            min_options: Number(minOptions),
            max_options: Number(maxOptions),
            is_active: isActive,
            addons: addons
                .filter((addon) => addon.name.trim() !== '')
                .map((addon) => ({
                    name: addon.name.trim(),
                    price: addon.price.replace(',', '.') || '0',
                })),
        };

        try {
            if (group) {
                await api.updateAddonGroup(establishmentId, group.id, payload);
                toast.success('Grupo atualizado.');
            } else {
                await api.createAddonGroup(establishmentId, payload);
                toast.success('Grupo criado.');
            }

            onSaved();
        } catch (error) {
            const failure = toFailure(error);
            setErrors(failure.errors);

            if (Object.keys(failure.errors).length === 0) toast.error(failure.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal
            open={open}
            title={group ? 'Editar grupo' : 'Novo grupo de adicionais'}
            onClose={onClose}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button form="addon-form" type="submit" loading={loading}>Salvar</Button>
                </>
            }
        >
            <form id="addon-form" onSubmit={submit} className="space-y-4" noValidate>
                <Input id="addon-nome" label="Nome do grupo" value={name} required autoFocus
                       placeholder="Ex.: Adicionais, Ponto da carne"
                       onChange={(e) => setName(e.target.value)} error={fieldError(errors, 'name')} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <Input label="Mínimo de escolhas" value={minOptions} inputMode="numeric"
                           onChange={(e) => setMinOptions(e.target.value)} error={fieldError(errors, 'min_options')} />
                    <Input label="Máximo de escolhas" value={maxOptions} inputMode="numeric"
                           onChange={(e) => setMaxOptions(e.target.value)} error={fieldError(errors, 'max_options')} />
                </div>

                <Toggle label="Escolha obrigatória" checked={isRequired} onChange={setIsRequired}
                        hint="O cliente não consegue adicionar o produto sem escolher." />

                <div>
                    <span className="label">Opções</span>
                    <div className="space-y-2">
                        {addons.map((addon, index) => (
                            <div key={index} className="flex gap-2">
                                <input
                                    value={addon.name}
                                    placeholder="Nome (ex.: Bacon)"
                                    aria-label={`Nome da opção ${index + 1}`}
                                    onChange={(event) => setAddons((current) =>
                                        current.map((item, i) => (i === index ? { ...item, name: event.target.value } : item)))}
                                    className="field flex-1"
                                />
                                <input
                                    value={addon.price}
                                    inputMode="decimal"
                                    placeholder="0,00"
                                    aria-label={`Preço da opção ${index + 1}`}
                                    onChange={(event) => setAddons((current) =>
                                        current.map((item, i) => (i === index ? { ...item, price: event.target.value } : item)))}
                                    className="field w-28"
                                />
                                <button
                                    type="button"
                                    onClick={() => setAddons((current) => current.filter((_, i) => i !== index))}
                                    disabled={addons.length === 1}
                                    aria-label={`Remover opção ${index + 1}`}
                                    className="tap px-2 text-ink-muted hover:text-danger disabled:opacity-30"
                                >
                                    <Icon name="trash" className="size-4" />
                                </button>
                            </div>
                        ))}
                    </div>

                    <Button type="button" variant="secondary" icon="plus" size="sm" className="mt-2"
                            onClick={() => setAddons((current) => [...current, { name: '', price: '0' }])}>
                        Adicionar opção
                    </Button>
                </div>

                <Toggle label="Grupo ativo" checked={isActive} onChange={setIsActive} />
            </form>
        </Modal>
    );
}
