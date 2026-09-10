import { useCallback, useEffect, useState } from 'react';
import { api } from '../api';
import { useEstablishment } from '../AppContext';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card, CardHeader } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { EmptyState } from '../../components/ui/EmptyState';
import { Input, Textarea, Toggle } from '../../components/ui/Field';
import { Icon } from '../../components/ui/Icon';
import { Modal } from '../../components/ui/Modal';
import { Spinner } from '../../components/ui/Spinner';
import { useToast } from '../../components/ui/Toast';
import type { Category } from '../types';

export function CategoriesPage() {
    const establishment = useEstablishment();
    const toast = useToast();

    const [categories, setCategories] = useState<Category[]>([]);
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState<Category | null>(null);
    const [creating, setCreating] = useState(false);
    const [deleting, setDeleting] = useState<Category | null>(null);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            setCategories(await api.categories(establishment.id));
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setLoading(false);
        }
    }, [establishment.id, toast]);

    useEffect(() => {
        load();
    }, [load]);

    const move = async (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= categories.length) return;

        const reordered = [...categories];
        const [moved] = reordered.splice(index, 1);
        reordered.splice(target, 0, moved!);
        setCategories(reordered);

        try {
            await api.reorderCategories(establishment.id, reordered.map((item) => item.id));
        } catch (error) {
            toast.error(toFailure(error).message);
            load();
        }
    };

    const remove = async () => {
        if (!deleting) return;

        setBusy(true);

        try {
            await api.deleteCategory(establishment.id, deleting.id);
            toast.success('Categoria removida.');
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
                    <h1 className="font-display text-2xl text-ink">Categorias</h1>
                    <p className="mt-1 text-sm text-ink-muted">A ordem daqui é a ordem que o cliente vê no cardápio.</p>
                </div>
                <Button icon="plus" onClick={() => setCreating(true)}>Nova categoria</Button>
            </div>

            <Card>
                <CardHeader title={`${categories.length} categoria(s)`} />

                {loading ? (
                    <Spinner />
                ) : categories.length === 0 ? (
                    <EmptyState
                        icon="grid"
                        title="Nenhuma categoria ainda"
                        description="Categorias organizam o cardápio: Hambúrgueres, Bebidas, Sobremesas..."
                        action={<Button icon="plus" onClick={() => setCreating(true)}>Criar primeira categoria</Button>}
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {categories.map((category, index) => (
                            <li key={category.id} className="flex items-center gap-3 p-4">
                                <div className="flex flex-col">
                                    <button type="button" onClick={() => move(index, -1)} disabled={index === 0}
                                            aria-label={`Mover ${category.name} para cima`}
                                            className="cursor-pointer px-1 text-ink-muted disabled:opacity-25">
                                        <Icon name="chevron-down" className="size-4 rotate-180" />
                                    </button>
                                    <button type="button" onClick={() => move(index, 1)} disabled={index === categories.length - 1}
                                            aria-label={`Mover ${category.name} para baixo`}
                                            className="cursor-pointer px-1 text-ink-muted disabled:opacity-25">
                                        <Icon name="chevron-down" className="size-4" />
                                    </button>
                                </div>

                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-2 font-medium text-ink">
                                        {category.name}
                                        {!category.is_active && <Badge>Oculta</Badge>}
                                    </p>
                                    <p className="text-sm text-ink-muted">
                                        {category.products_count ?? 0} produto(s)
                                    </p>
                                </div>

                                <div className="flex gap-1">
                                    <Button variant="ghost" size="sm" icon="edit" onClick={() => setEditing(category)}>
                                        <span className="sr-only sm:not-sr-only">Editar</span>
                                    </Button>
                                    <Button variant="ghost" size="sm" icon="trash" onClick={() => setDeleting(category)}>
                                        <span className="sr-only">Excluir {category.name}</span>
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <CategoryForm
                open={creating || editing !== null}
                category={editing}
                establishmentId={establishment.id}
                onClose={() => { setCreating(false); setEditing(null); }}
                onSaved={() => { setCreating(false); setEditing(null); load(); }}
            />

            <ConfirmDialog
                open={deleting !== null}
                title="Excluir categoria"
                message={`"${deleting?.name}" será removida. Os produtos dela continuam cadastrados, mas ficam sem categoria.`}
                confirmLabel="Excluir"
                destructive
                loading={busy}
                onConfirm={remove}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}

function CategoryForm({ open, category, establishmentId, onClose, onSaved }: {
    open: boolean;
    category: Category | null;
    establishmentId: number;
    onClose: () => void;
    onSaved: () => void;
}) {
    const toast = useToast();
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [errors, setErrors] = useState<ApiErrors>({});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) return;

        setName(category?.name ?? '');
        setDescription(category?.description ?? '');
        setIsActive(category?.is_active ?? true);
        setErrors({});
    }, [open, category]);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});

        const payload = { name, description: description || null, is_active: isActive };

        try {
            if (category) {
                await api.updateCategory(establishmentId, category.id, payload);
                toast.success('Categoria atualizada.');
            } else {
                await api.createCategory(establishmentId, payload);
                toast.success('Categoria criada.');
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
            title={category ? 'Editar categoria' : 'Nova categoria'}
            onClose={onClose}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button form="category-form" type="submit" loading={loading}>Salvar</Button>
                </>
            }
        >
            <form id="category-form" onSubmit={submit} className="space-y-4" noValidate>
                <Input id="categoria-nome" label="Nome" value={name} required autoFocus
                       onChange={(e) => setName(e.target.value)} error={fieldError(errors, 'name')} />

                <Textarea label="Descrição (opcional)" value={description} rows={2} maxLength={255}
                          onChange={(e) => setDescription(e.target.value)} error={fieldError(errors, 'description')} />

                <Toggle label="Visível no cardápio" checked={isActive} onChange={setIsActive}
                        hint="Categorias ocultas não aparecem para o cliente." />
            </form>
        </Modal>
    );
}
