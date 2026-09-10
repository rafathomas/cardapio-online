import { useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { useApp } from '../AppContext';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Button } from '../../components/ui/Button';
import { Input, Select, Textarea } from '../../components/ui/Field';
import { Icon } from '../../components/ui/Icon';
import { useToast } from '../../components/ui/Toast';
import type { Establishment } from '../types';

const SEGMENTS = [
    ['lanchonete', 'Lanchonete'], ['pizzaria', 'Pizzaria'], ['acaiteria', 'Açaiteria'],
    ['cafeteria', 'Cafeteria'], ['restaurante', 'Restaurante'], ['bar', 'Bar'],
    ['confeitaria', 'Confeitaria'], ['padaria', 'Padaria'], ['sorveteria', 'Sorveteria'],
    ['outro', 'Outro'],
] as const;

const SUGGESTED: Record<string, string[]> = {
    lanchonete: ['Hambúrgueres', 'Porções', 'Combos', 'Bebidas'],
    pizzaria: ['Pizzas Salgadas', 'Pizzas Doces', 'Bordas', 'Bebidas'],
    acaiteria: ['Açaí', 'Complementos', 'Vitaminas', 'Bebidas'],
    cafeteria: ['Cafés', 'Doces', 'Salgados', 'Bebidas Geladas'],
    restaurante: ['Entradas', 'Pratos Principais', 'Sobremesas', 'Bebidas'],
    bar: ['Drinks', 'Cervejas', 'Porções', 'Doses'],
    confeitaria: ['Bolos', 'Tortas', 'Doces', 'Bebidas'],
    padaria: ['Pães', 'Salgados', 'Doces', 'Bebidas'],
    sorveteria: ['Sorvetes', 'Milkshakes', 'Açaí', 'Coberturas'],
    outro: ['Destaques', 'Bebidas'],
};

const STEPS = ['Seu negócio', 'Categorias', 'Produtos', 'Personalização', 'Publicar'];

interface DraftProduct {
    name: string;
    price: string;
    categoryName: string;
}

export function OnboardingPage() {
    const { refreshEstablishments, selectEstablishment, user } = useApp();
    const toast = useToast();
    const navigate = useNavigate();

    const [step, setStep] = useState(0);
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<ApiErrors>({});

    // Passo 1
    const [name, setName] = useState('');
    const [segment, setSegment] = useState<string>('lanchonete');
    const [whatsapp, setWhatsapp] = useState('');
    const [description, setDescription] = useState('');

    // Passo 2/3
    const [categories, setCategories] = useState<string[]>([]);
    const [products, setProducts] = useState<DraftProduct[]>([]);
    const [productName, setProductName] = useState('');
    const [productPrice, setProductPrice] = useState('');
    const [productCategory, setProductCategory] = useState('');

    // Passo 4
    const [primaryColor, setPrimaryColor] = useState('#DC2626');

    // Resultado
    const [establishment, setEstablishment] = useState<Establishment | null>(null);
    const [publicUrl, setPublicUrl] = useState<string | null>(null);

    const suggestions = useMemo(() => SUGGESTED[segment] ?? [], [segment]);

    const toggleCategory = (value: string) => {
        setCategories((current) =>
            current.includes(value) ? current.filter((item) => item !== value) : [...current, value],
        );
    };

    /** Cria o estabelecimento de fato ao sair do primeiro passo. */
    const createEstablishment = async () => {
        setLoading(true);
        setErrors({});

        try {
            const created = await api.createEstablishment({
                name,
                segment,
                whatsapp,
                description: description || null,
            });

            setEstablishment(created);
            selectEstablishment(created.id);
            await refreshEstablishments();
            setCategories(SUGGESTED[segment] ?? []);
            setStep(1);
        } catch (error) {
            const failure = toFailure(error);
            setErrors(failure.errors);

            if (Object.keys(failure.errors).length === 0) toast.error(failure.message);
        } finally {
            setLoading(false);
        }
    };

    const saveCategories = async () => {
        if (!establishment) return;

        setLoading(true);

        try {
            for (const categoryName of categories) {
                await api.createCategory(establishment.id, { name: categoryName });
            }

            setProductCategory(categories[0] ?? '');
            setStep(2);
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setLoading(false);
        }
    };

    const saveProducts = async () => {
        if (!establishment) return;

        setLoading(true);

        try {
            const saved = await api.categories(establishment.id);

            for (const product of products) {
                const category = saved.find((item) => item.name === product.categoryName);

                await api.createProduct(establishment.id, {
                    name: product.name,
                    price: product.price,
                    category_id: category?.id ?? null,
                });
            }

            setStep(3);
        } catch (error) {
            const failure = toFailure(error);
            toast.error(failure.message);
        } finally {
            setLoading(false);
        }
    };

    const saveCustomization = async () => {
        if (!establishment) return;

        setLoading(true);

        try {
            await api.updateEstablishment(establishment.id, {
                name: establishment.name,
                segment: establishment.segment,
                whatsapp: establishment.whatsapp,
                description: establishment.description ?? '',
                primary_color: primaryColor,
            });
        } catch (error) {
            const failure = toFailure(error);

            // Personalizar cor é recurso do plano Pro: seguir sem bloquear o onboarding.
            if (failure.errorCode !== 'plan_feature_unavailable') {
                toast.error(failure.message);
            }
        } finally {
            setLoading(false);
            setStep(4);
        }
    };

    const publish = async () => {
        if (!establishment) return;

        setLoading(true);

        try {
            const result = await api.publish(establishment.id);
            setPublicUrl(result.public_url);
            await refreshEstablishments();
            toast.success('Seu cardápio está no ar!');
        } catch (error) {
            const failure = toFailure(error);
            toast.error(failure.message);
        } finally {
            setLoading(false);
        }
    };

    if (publicUrl) {
        return (
            <div className="mx-auto flex min-h-full max-w-lg flex-col items-center justify-center p-4 text-center">
                <span className="flex size-16 items-center justify-center rounded-2xl bg-success-soft text-success">
                    <Icon name="check" className="size-8" />
                </span>

                <h1 className="mt-5 font-display text-3xl text-ink">Seu cardápio está no ar 🎉</h1>
                <p className="mt-2 text-ink-muted">Compartilhe o link e comece a receber pedidos.</p>

                <div className="card mt-6 w-full p-4">
                    <p className="text-xs font-semibold tracking-wide text-ink-muted uppercase">Seu link</p>
                    <p className="mt-1 break-all text-sm font-medium text-brand">{publicUrl}</p>

                    <div className="mt-4 grid gap-2 sm:grid-cols-2">
                        <Button
                            variant="secondary"
                            icon="copy"
                            onClick={() => {
                                navigator.clipboard.writeText(publicUrl);
                                toast.success('Link copiado.');
                            }}
                        >
                            Copiar link
                        </Button>

                        <a href={publicUrl} target="_blank" rel="noopener" className="btn-secondary">
                            <Icon name="eye" className="size-4" />
                            Visualizar
                        </a>
                    </div>
                </div>

                <div className="mt-4 grid w-full gap-2 sm:grid-cols-2">
                    <Button variant="secondary" icon="qr" onClick={() => navigate('/app/qrcode')}>
                        Ver QR Code
                    </Button>
                    <Button icon="grid" onClick={() => navigate('/app')}>Ir para o painel</Button>
                </div>
            </div>
        );
    }

    return (
        <div className="mx-auto min-h-full max-w-2xl p-4 sm:p-6">
            <header className="mb-6">
                <h1 className="font-display text-2xl text-ink">
                    {step === 0 ? `Vamos criar seu cardápio, ${user?.name.split(' ')[0]}.` : STEPS[step]}
                </h1>
                <p className="mt-1 text-sm text-ink-muted">Passo {step + 1} de {STEPS.length}</p>

                <ol className="mt-4 flex gap-1.5" aria-label="Progresso do onboarding">
                    {STEPS.map((label, index) => (
                        <li key={label} className="flex-1">
                            <span
                                aria-current={index === step ? 'step' : undefined}
                                className={`block h-1.5 rounded-full ${index <= step ? 'bg-brand' : 'bg-line'}`}
                            />
                            <span className="sr-only">{label}</span>
                        </li>
                    ))}
                </ol>
            </header>

            <div className="card p-5 sm:p-6">
                {step === 0 && (
                    <form
                        className="space-y-4"
                        noValidate
                        onSubmit={(event) => {
                            event.preventDefault();
                            createEstablishment();
                        }}
                    >
                        <Input id="onboarding-nome" label="Nome do estabelecimento" value={name} required autoFocus
                               placeholder="Ex.: Sabor & Ponto"
                               onChange={(e) => setName(e.target.value)} error={fieldError(errors, 'name')} />

                        <Select id="onboarding-segmento" label="Segmento" value={segment} required
                                onChange={(e) => setSegment(e.target.value)} error={fieldError(errors, 'segment')}>
                            {SEGMENTS.map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </Select>

                        <Input id="onboarding-whatsapp" label="WhatsApp para receber pedidos" value={whatsapp} required
                               inputMode="tel" placeholder="(11) 98888-7777"
                               hint="É para este número que os pedidos vão chegar."
                               onChange={(e) => setWhatsapp(e.target.value)} error={fieldError(errors, 'whatsapp')} />

                        <Textarea label="Descrição (opcional)" value={description} rows={2} maxLength={1000}
                                  placeholder="Uma frase sobre o seu negócio."
                                  onChange={(e) => setDescription(e.target.value)} />

                        <Button type="submit" loading={loading} size="lg" className="w-full">Continuar</Button>
                    </form>
                )}

                {step === 1 && (
                    <div>
                        <p className="text-sm text-ink-muted">
                            Escolha as categorias do seu cardápio. Dá para mudar tudo depois.
                        </p>

                        <div className="mt-4 flex flex-wrap gap-2">
                            {[...new Set([...suggestions, ...categories])].map((suggestion) => {
                                const selected = categories.includes(suggestion);

                                return (
                                    <button
                                        key={suggestion}
                                        type="button"
                                        onClick={() => toggleCategory(suggestion)}
                                        aria-pressed={selected}
                                        className={`tap rounded-full border px-4 py-2 text-sm font-semibold ${
                                            selected ? 'border-brand bg-brand-soft text-brand-strong' : 'border-line text-ink-muted'
                                        }`}
                                    >
                                        {suggestion}
                                    </button>
                                );
                            })}
                        </div>

                        <AddCategoryInline onAdd={(value) => setCategories((c) => [...c, value])} existing={categories} />

                        <div className="mt-6 flex gap-2">
                            <Button variant="secondary" onClick={() => setStep(2)}>Pular</Button>
                            <Button onClick={saveCategories} loading={loading} disabled={categories.length === 0} className="flex-1">
                                Continuar com {categories.length} categoria{categories.length === 1 ? '' : 's'}
                            </Button>
                        </div>
                    </div>
                )}

                {step === 2 && (
                    <div>
                        <p className="text-sm text-ink-muted">
                            Cadastre alguns produtos para começar. Fotos e detalhes você adiciona depois.
                        </p>

                        <form
                            className="mt-4 space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (!productName.trim() || !productPrice.trim()) return;

                                setProducts((current) => [...current, {
                                    name: productName.trim(),
                                    price: productPrice.trim(),
                                    categoryName: productCategory,
                                }]);
                                setProductName('');
                                setProductPrice('');
                            }}
                        >
                            <Input id="onboarding-produto-nome" label="Nome do produto" value={productName}
                                   placeholder="Ex.: X-Bacon" onChange={(e) => setProductName(e.target.value)} />

                            <div className="grid gap-3 sm:grid-cols-2">
                                <Input id="onboarding-produto-preco" label="Preço" value={productPrice} inputMode="decimal" placeholder="29,90"
                                       onChange={(e) => setProductPrice(e.target.value)} />

                                <Select label="Categoria" value={productCategory}
                                        onChange={(e) => setProductCategory(e.target.value)}>
                                    <option value="">Sem categoria</option>
                                    {categories.map((category) => (
                                        <option key={category} value={category}>{category}</option>
                                    ))}
                                </Select>
                            </div>

                            <Button type="submit" variant="secondary" icon="plus" className="w-full">
                                Adicionar à lista
                            </Button>
                        </form>

                        {products.length > 0 && (
                            <ul className="mt-4 divide-y divide-line rounded-xl border border-line">
                                {products.map((product, index) => (
                                    <li key={`${product.name}-${index}`} className="flex items-center justify-between gap-3 p-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium text-ink">{product.name}</p>
                                            <p className="text-sm text-ink-muted">
                                                R$ {product.price}{product.categoryName && ` · ${product.categoryName}`}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => setProducts((c) => c.filter((_, i) => i !== index))}
                                            className="tap px-2 text-ink-muted hover:text-danger"
                                            aria-label={`Remover ${product.name}`}
                                        >
                                            <Icon name="trash" className="size-4" />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <div className="mt-6 flex gap-2">
                            <Button variant="secondary" onClick={() => setStep(3)}>Pular</Button>
                            <Button onClick={saveProducts} loading={loading} disabled={products.length === 0} className="flex-1">
                                Salvar {products.length} produto{products.length === 1 ? '' : 's'}
                            </Button>
                        </div>
                    </div>
                )}

                {step === 3 && (
                    <div>
                        <p className="text-sm text-ink-muted">
                            Escolha a cor principal do seu cardápio. Logo e foto de capa você envia na aba Personalização.
                        </p>

                        <div className="mt-4 flex flex-wrap gap-2">
                            {['#DC2626', '#EA580C', '#16A34A', '#0891B2', '#7C3AED', '#DB2777', '#0F172A'].map((color) => (
                                <button
                                    key={color}
                                    type="button"
                                    onClick={() => setPrimaryColor(color)}
                                    aria-label={`Usar a cor ${color}`}
                                    aria-pressed={primaryColor === color}
                                    style={{ backgroundColor: color }}
                                    className={`tap size-11 rounded-xl ring-offset-2 ${primaryColor === color ? 'ring-2 ring-ink' : ''}`}
                                />
                            ))}
                        </div>

                        <p className="hint mt-3">
                            A personalização de cores faz parte do plano Pro — se você estiver no Free, seguimos com a cor padrão.
                        </p>

                        <div className="mt-6 flex gap-2">
                            <Button variant="secondary" onClick={() => setStep(4)}>Pular</Button>
                            <Button onClick={saveCustomization} loading={loading} className="flex-1">Continuar</Button>
                        </div>
                    </div>
                )}

                {step === 4 && (
                    <div className="text-center">
                        <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                            <Icon name="sparkles" className="size-6" />
                        </span>

                        <h2 className="mt-4 font-display text-xl text-ink">Tudo pronto para publicar</h2>
                        <p className="mt-2 text-sm text-ink-muted">
                            Ao publicar, seu cardápio ganha um link público e um QR Code.
                        </p>

                        <Button onClick={publish} loading={loading} size="lg" className="mt-6 w-full">
                            Publicar meu cardápio
                        </Button>

                        <button type="button" onClick={() => navigate('/app')}
                                className="tap mt-3 w-full text-sm text-ink-muted underline">
                            Publicar depois
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}

function AddCategoryInline({ onAdd, existing }: { onAdd: (value: string) => void; existing: string[] }) {
    const [value, setValue] = useState('');

    return (
        <form
            className="mt-4 flex gap-2"
            onSubmit={(event) => {
                event.preventDefault();
                const trimmed = value.trim();

                if (trimmed.length < 2 || existing.includes(trimmed)) return;

                onAdd(trimmed);
                setValue('');
            }}
        >
            <input
                value={value}
                onChange={(event) => setValue(event.target.value)}
                placeholder="Outra categoria"
                aria-label="Nome da nova categoria"
                className="field"
            />
            <Button type="submit" variant="secondary" icon="plus">Adicionar</Button>
        </form>
    );
}
