import { useCallback, useEffect, useRef, useState } from 'react';
import { api } from '../api';
import { useEstablishment } from '../AppContext';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card, CardHeader } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Dropdown } from '../../components/ui/Dropdown';
import { EmptyState } from '../../components/ui/EmptyState';
import { Input, Select, Textarea, Toggle } from '../../components/ui/Field';
import { Icon } from '../../components/ui/Icon';
import { Modal } from '../../components/ui/Modal';
import { Spinner } from '../../components/ui/Spinner';
import { useToast } from '../../components/ui/Toast';
import type { AddonGroup, Category, PlanSummary, Product } from '../types';

export function ProductsPage() {
    const establishment = useEstablishment();
    const toast = useToast();

    const [products, setProducts] = useState<Product[]>([]);
    const [categories, setCategories] = useState<Category[]>([]);
    const [addonGroups, setAddonGroups] = useState<AddonGroup[]>([]);
    const [plan, setPlan] = useState<PlanSummary | null>(null);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('');
    const [editing, setEditing] = useState<Product | null>(null);
    const [creating, setCreating] = useState(false);
    const [importing, setImporting] = useState(false);
    const [deleting, setDeleting] = useState<Product | null>(null);
    const [busy, setBusy] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);

        try {
            const params: Record<string, string | number> = { per_page: 100 };
            if (search) params.search = search;
            if (categoryFilter) params.category_id = categoryFilter;

            const [productsResponse, categoriesList, groups] = await Promise.all([
                api.products(establishment.id, params),
                api.categories(establishment.id),
                api.addonGroups(establishment.id),
            ]);

            setProducts(productsResponse.data);
            setPlan(productsResponse.plan);
            setCategories(categoriesList);
            setAddonGroups(groups);
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setLoading(false);
        }
    }, [establishment.id, search, categoryFilter, toast]);

    useEffect(() => {
        const timer = window.setTimeout(load, search ? 300 : 0);

        return () => window.clearTimeout(timer);
    }, [load, search]);

    const limitReached = plan?.limits.products_remaining === 0;

    const toggle = async (product: Product) => {
        try {
            const updated = await api.toggleProduct(establishment.id, product.id);
            setProducts((current) => current.map((item) => (item.id === updated.id ? updated : item)));
            toast.success(updated.is_active ? 'Produto ativado.' : 'Produto desativado.');
        } catch (error) {
            toast.error(toFailure(error).message);
        }
    };

    const duplicate = async (product: Product) => {
        try {
            await api.duplicateProduct(establishment.id, product.id);
            toast.success('Produto duplicado como rascunho.');
            load();
        } catch (error) {
            toast.error(toFailure(error).message);
        }
    };

    const remove = async () => {
        if (!deleting) return;

        setBusy(true);

        try {
            await api.deleteProduct(establishment.id, deleting.id);
            toast.success('Produto removido.');
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
                    <h1 className="font-display text-2xl text-ink">Produtos</h1>
                    {plan && (
                        <p className="mt-1 text-sm text-ink-muted">
                            {plan.limits.max_products === null
                                ? `${plan.limits.products_used} produtos · plano ${plan.plan.name}`
                                : `${plan.limits.products_used} de ${plan.limits.max_products} produtos usados`}
                        </p>
                    )}
                </div>
                <div className="flex gap-2">
                    <Button variant="secondary" icon="upload" onClick={() => setImporting(true)} disabled={limitReached}>
                        Importar planilha
                    </Button>
                    <Button icon="plus" onClick={() => setCreating(true)} disabled={limitReached}>
                        Novo produto
                    </Button>
                </div>
            </div>

            {limitReached && (
                <div className="card flex flex-wrap items-center justify-between gap-3 border-warning/30 bg-warning-soft p-4">
                    <p className="text-sm font-medium text-warning">
                        Você atingiu o limite de produtos do plano {plan?.plan.name}.
                    </p>
                    <a href="/app/assinatura" className="btn-primary py-2 text-sm">Fazer upgrade</a>
                </div>
            )}

            <div className="flex flex-wrap gap-3">
                <div className="relative min-w-52 flex-1">
                    <Icon name="search" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-muted" />
                    <input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Buscar produto"
                        aria-label="Buscar produto"
                        className="field pl-9"
                    />
                </div>

                <select
                    value={categoryFilter}
                    onChange={(event) => setCategoryFilter(event.target.value)}
                    aria-label="Filtrar por categoria"
                    className="field max-w-52"
                >
                    <option value="">Todas as categorias</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>{category.name}</option>
                    ))}
                </select>
            </div>

            <Card>
                <CardHeader title={`${products.length} produto(s)`} />

                {loading ? (
                    <Spinner />
                ) : products.length === 0 ? (
                    <EmptyState
                        icon="package"
                        title={search || categoryFilter ? 'Nenhum produto encontrado' : 'Nenhum produto ainda'}
                        description={search || categoryFilter
                            ? 'Tente outro termo ou limpe os filtros.'
                            : 'Cadastre seu primeiro item para o cardápio começar a funcionar.'}
                        action={!search && !categoryFilter
                            ? <Button icon="plus" onClick={() => setCreating(true)} disabled={limitReached}>Criar produto</Button>
                            : undefined}
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {products.map((product) => (
                            <li key={product.id} className="flex items-center gap-3 p-4">
                                {product.image_url ? (
                                    <img src={product.image_url} alt="" loading="lazy"
                                         className="size-14 shrink-0 rounded-xl object-cover" width="56" height="56" />
                                ) : (
                                    <div className="flex size-14 shrink-0 items-center justify-center rounded-xl bg-surface-muted text-ink-muted">
                                        <Icon name="package" className="size-5" />
                                    </div>
                                )}

                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 font-medium text-ink">
                                        {product.name}
                                        {!product.is_active && <Badge>Inativo</Badge>}
                                        {product.is_featured && <Badge tone="warning">Destaque</Badge>}
                                    </p>

                                    <p className="mt-0.5 flex flex-wrap items-baseline gap-2 text-sm">
                                        <span className="font-semibold text-brand">{product.effective_price_formatted}</span>
                                        {product.has_discount && (
                                            <span className="text-slate-400 line-through">{product.price_formatted}</span>
                                        )}
                                        <span className="text-ink-muted">
                                            {product.category?.name ?? 'Sem categoria'}
                                        </span>
                                    </p>
                                </div>

                                <Dropdown
                                    label={<span className="sr-only">Ações para {product.name}</span>}
                                    items={[
                                        { label: 'Editar', icon: 'edit', onSelect: () => setEditing(product) },
                                        { label: product.is_active ? 'Desativar' : 'Ativar', icon: 'eye', onSelect: () => toggle(product) },
                                        { label: 'Duplicar', icon: 'copy', onSelect: () => duplicate(product) },
                                        { label: 'Excluir', icon: 'trash', destructive: true, onSelect: () => setDeleting(product) },
                                    ]}
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <ProductForm
                open={creating || editing !== null}
                product={editing}
                categories={categories}
                addonGroups={addonGroups}
                establishmentId={establishment.id}
                onClose={() => { setCreating(false); setEditing(null); }}
                onSaved={() => { setCreating(false); setEditing(null); load(); }}
            />

            <ImportProductsModal
                open={importing}
                establishmentId={establishment.id}
                onClose={() => setImporting(false)}
                onImported={() => load()}
            />

            <ConfirmDialog
                open={deleting !== null}
                title="Excluir produto"
                message={`"${deleting?.name}" sai do cardápio. Pedidos antigos continuam com o registro do item.`}
                confirmLabel="Excluir"
                destructive
                loading={busy}
                onConfirm={remove}
                onCancel={() => setDeleting(null)}
            />
        </div>
    );
}

function ProductForm({ open, product, categories, addonGroups, establishmentId, onClose, onSaved }: {
    open: boolean;
    product: Product | null;
    categories: Category[];
    addonGroups: AddonGroup[];
    establishmentId: number;
    onClose: () => void;
    onSaved: () => void;
}) {
    const toast = useToast();
    const fileInput = useRef<HTMLInputElement>(null);

    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [price, setPrice] = useState('');
    const [promoPrice, setPromoPrice] = useState('');
    const [categoryId, setCategoryId] = useState('');
    const [isActive, setIsActive] = useState(true);
    const [isFeatured, setIsFeatured] = useState(false);
    const [preview, setPreview] = useState<string | null>(null);
    const [selectedGroups, setSelectedGroups] = useState<number[]>([]);
    const [errors, setErrors] = useState<ApiErrors>({});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) return;

        setName(product?.name ?? '');
        setDescription(product?.description ?? '');
        setPrice(product ? (product.price_cents / 100).toFixed(2).replace('.', ',') : '');
        setPromoPrice(product?.promo_price_cents ? (product.promo_price_cents / 100).toFixed(2).replace('.', ',') : '');
        setCategoryId(product?.category_id ? String(product.category_id) : '');
        setIsActive(product?.is_active ?? true);
        setIsFeatured(product?.is_featured ?? false);
        setPreview(product?.image_url ?? null);
        setSelectedGroups(product?.addon_groups?.map((group) => group.id) ?? []);
        setErrors({});

        if (fileInput.current) fileInput.current.value = '';
    }, [open, product]);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});

        const payload = {
            name,
            price,
            is_active: isActive,
            is_featured: isFeatured,
            description: description || null,
            promo_price: promoPrice || null,
            category_id: categoryId || null,
            image: fileInput.current?.files?.[0] ?? null,
            addon_group_ids: selectedGroups,
        };

        try {
            if (product) {
                await api.updateProduct(establishmentId, product.id, payload);
                toast.success('Produto atualizado.');
            } else {
                await api.createProduct(establishmentId, payload);
                toast.success('Produto criado.');
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
            title={product ? 'Editar produto' : 'Novo produto'}
            onClose={onClose}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button form="product-form" type="submit" loading={loading}>Salvar produto</Button>
                </>
            }
        >
            <form id="product-form" onSubmit={submit} className="space-y-4" noValidate>
                <Input id="produto-nome" label="Nome" value={name} required autoFocus
                       onChange={(e) => setName(e.target.value)} error={fieldError(errors, 'name')} />

                <Textarea label="Descrição" value={description} rows={3} maxLength={1000}
                          placeholder="Ingredientes, tamanho da porção, acompanhamentos..."
                          onChange={(e) => setDescription(e.target.value)} error={fieldError(errors, 'description')} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <Input id="produto-preco" label="Preço" value={price} required inputMode="decimal" placeholder="29,90"
                           onChange={(e) => setPrice(e.target.value)} error={fieldError(errors, 'price')} />

                    <Input label="Preço promocional" value={promoPrice} inputMode="decimal" placeholder="19,90"
                           hint="Deixe vazio se não houver promoção."
                           onChange={(e) => setPromoPrice(e.target.value)} error={fieldError(errors, 'promo_price')} />
                </div>

                <Select label="Categoria" value={categoryId}
                        onChange={(e) => setCategoryId(e.target.value)} error={fieldError(errors, 'category_id')}>
                    <option value="">Sem categoria</option>
                    {categories.map((category) => (
                        <option key={category.id} value={category.id}>{category.name}</option>
                    ))}
                </Select>

                <div>
                    <span className="label">Foto</span>
                    <div className="flex items-center gap-4">
                        {preview ? (
                            <img src={preview} alt="Pré-visualização" className="size-20 rounded-xl object-cover" />
                        ) : (
                            <div className="flex size-20 items-center justify-center rounded-xl bg-surface-muted text-ink-muted">
                                <Icon name="package" />
                            </div>
                        )}

                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            aria-label="Escolher foto do produto"
                            onChange={(event) => {
                                const file = event.target.files?.[0];
                                setPreview(file ? URL.createObjectURL(file) : product?.image_url ?? null);
                            }}
                            className="text-sm file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-surface-muted file:px-3 file:py-2 file:text-sm file:font-semibold"
                        />
                    </div>
                    {fieldError(errors, 'image') && <p className="error">{fieldError(errors, 'image')}</p>}
                    <p className="hint">JPG, PNG ou WebP. Reduzimos e convertemos automaticamente.</p>
                </div>

                {addonGroups.length > 0 && (
                    <fieldset className="border-t border-line pt-4">
                        <legend className="label">Grupos de adicionais</legend>
                        <div className="space-y-1.5">
                            {addonGroups.map((group) => (
                                <label key={group.id} className="tap flex items-center gap-2.5 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={selectedGroups.includes(group.id)}
                                        onChange={(event) =>
                                            setSelectedGroups((current) =>
                                                event.target.checked
                                                    ? [...current, group.id]
                                                    : current.filter((id) => id !== group.id),
                                            )
                                        }
                                        className="size-4 accent-[var(--color-brand)]"
                                    />
                                    {group.name}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                )}

                <div className="space-y-3 border-t border-line pt-4">
                    <Toggle label="Disponível no cardápio" checked={isActive} onChange={setIsActive} />
                    <Toggle label="Marcar como destaque" checked={isFeatured} onChange={setIsFeatured}
                            hint="Destaques ganham um selo na listagem." />
                </div>
            </form>
        </Modal>
    );
}

const IMPORT_TEMPLATE_CSV = 'nome,descricao,preco,preco_promocional,categoria,ativo\n'
    + 'X-Burguer,"Pão, carne 180g e queijo",29.90,24.90,Lanches,sim\n'
    + 'Refrigerante Lata,,6.00,,Bebidas,sim\n';

function downloadImportTemplate() {
    const blob = new Blob([IMPORT_TEMPLATE_CSV], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'modelo-produtos.csv';
    link.click();
    URL.revokeObjectURL(url);
}

function ImportProductsModal({ open, establishmentId, onClose, onImported }: {
    open: boolean;
    establishmentId: number;
    onClose: () => void;
    onImported: () => void;
}) {
    const toast = useToast();
    const fileInput = useRef<HTMLInputElement>(null);

    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState<{ created: number; errors: { row: number; message: string }[] } | null>(null);

    useEffect(() => {
        if (!open) return;

        setResult(null);
        if (fileInput.current) fileInput.current.value = '';
    }, [open]);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();

        const file = fileInput.current?.files?.[0];
        if (!file) return;

        setLoading(true);
        setResult(null);

        try {
            const response = await api.importProducts(establishmentId, file);
            setResult({ created: response.created, errors: response.errors });

            if (response.created > 0) {
                toast.success(response.message);
                onImported();
            }
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal
            open={open}
            title="Importar produtos por planilha"
            onClose={onClose}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Fechar</Button>
                    <Button form="import-products-form" type="submit" loading={loading}>Importar</Button>
                </>
            }
        >
            <form id="import-products-form" onSubmit={submit} className="space-y-4" noValidate>
                <p className="text-sm text-ink-muted">
                    Envie um arquivo CSV ou Excel (.xlsx) com uma linha por produto. Colunas aceitas:{' '}
                    <strong>nome</strong> e <strong>preco</strong> (obrigatórias), <strong>descricao</strong>,{' '}
                    <strong>preco_promocional</strong>, <strong>categoria</strong> e <strong>ativo</strong> (sim/não).
                </p>

                <button type="button" onClick={downloadImportTemplate}
                        className="flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">
                    <Icon name="download" className="size-4" />
                    Baixar modelo de planilha (CSV)
                </button>

                <div>
                    <span className="label">Arquivo</span>
                    <input
                        ref={fileInput}
                        type="file"
                        accept=".csv,.txt,.xlsx,.xls,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                        aria-label="Selecionar planilha de produtos"
                        required
                        className="text-sm file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-surface-muted file:px-3 file:py-2 file:text-sm file:font-semibold"
                    />
                </div>

                {result && (
                    <div className="space-y-2 rounded-xl bg-surface-muted p-3 text-sm">
                        <p className="font-medium text-ink">
                            {result.created} produto(s) importado(s) com sucesso.
                        </p>

                        {result.errors.length > 0 && (
                            <ul className="max-h-40 space-y-1 overflow-y-auto text-danger">
                                {result.errors.map((error, index) => (
                                    <li key={index}>Linha {error.row}: {error.message}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
            </form>
        </Modal>
    );
}
