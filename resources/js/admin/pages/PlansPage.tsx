import { useEffect, useState } from 'react';
import { adminApi } from '../api';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Badge } from '../../components/ui/Badge';
import { Button } from '../../components/ui/Button';
import { Card, CardBody, CardHeader } from '../../components/ui/Card';
import { Input, Toggle } from '../../components/ui/Field';
import { Modal } from '../../components/ui/Modal';
import { Spinner } from '../../components/ui/Spinner';
import { useToast } from '../../components/ui/Toast';
import type { Plan } from '../../dashboard/types';

const FEATURES = [
    ['remove_branding', 'Remover marca da plataforma'],
    ['statistics', 'Estatísticas'],
    ['customization', 'Personalização do cardápio'],
    ['addons', 'Adicionais'],
    ['cover_image', 'Foto de capa'],
] as const;

export function PlansPage() {
    const toast = useToast();
    const [plans, setPlans] = useState<Plan[]>([]);
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState<Plan | null>(null);

    const load = async () => {
        setLoading(true);

        try {
            setPlans(await adminApi.plans());
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    if (loading) return <Spinner />;

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">Planos</h1>
                <p className="mt-1 text-sm text-ink-muted">
                    Preço, limites e recursos de cada plano. Alterações valem para novas cobranças.
                </p>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                {plans.map((plan) => (
                    <Card key={plan.id}>
                        <CardHeader
                            title={plan.name}
                            action={plan.is_active ? <Badge tone="success">Ativo</Badge> : <Badge>Inativo</Badge>}
                        />
                        <CardBody className="space-y-3">
                            <p className="font-display text-2xl text-ink">
                                {plan.price_formatted}
                                {!plan.is_free && <span className="text-base text-ink-muted">/mês</span>}
                            </p>

                            <ul className="space-y-1 text-sm text-ink-muted">
                                <li>Produtos: {plan.max_products ?? 'ilimitado'}</li>
                                <li>Categorias: {plan.max_categories ?? 'ilimitado'}</li>
                                <li>Estabelecimentos: {plan.max_establishments ?? 'ilimitado'}</li>
                                <li>Recursos: {plan.features.length > 0 ? plan.features.length : 'nenhum'}</li>
                            </ul>

                            <Button variant="secondary" icon="edit" onClick={() => setEditing(plan)}>
                                Editar plano
                            </Button>
                        </CardBody>
                    </Card>
                ))}
            </div>

            <PlanForm
                plan={editing}
                onClose={() => setEditing(null)}
                onSaved={() => { setEditing(null); load(); }}
            />
        </div>
    );
}

function PlanForm({ plan, onClose, onSaved }: {
    plan: Plan | null;
    onClose: () => void;
    onSaved: () => void;
}) {
    const toast = useToast();
    const [name, setName] = useState('');
    const [price, setPrice] = useState('');
    const [maxProducts, setMaxProducts] = useState('');
    const [maxCategories, setMaxCategories] = useState('');
    const [maxEstablishments, setMaxEstablishments] = useState('');
    const [features, setFeatures] = useState<string[]>([]);
    const [isActive, setIsActive] = useState(true);
    const [errors, setErrors] = useState<ApiErrors>({});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!plan) return;

        setName(plan.name);
        setPrice((plan.price_cents / 100).toFixed(2));
        setMaxProducts(plan.max_products === null ? '' : String(plan.max_products));
        setMaxCategories(plan.max_categories === null ? '' : String(plan.max_categories));
        setMaxEstablishments(plan.max_establishments === null ? '' : String(plan.max_establishments));
        setFeatures(plan.features);
        setIsActive(plan.is_active ?? true);
        setErrors({});
    }, [plan]);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!plan) return;

        setLoading(true);
        setErrors({});

        try {
            await adminApi.updatePlan(plan.id, {
                name,
                price: Number(price),
                // Campo vazio significa ilimitado.
                max_products: maxProducts === '' ? null : Number(maxProducts),
                max_categories: maxCategories === '' ? null : Number(maxCategories),
                max_establishments: maxEstablishments === '' ? null : Number(maxEstablishments),
                features,
                is_active: isActive,
            });

            toast.success('Plano atualizado.');
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
            open={plan !== null}
            title={`Editar plano ${plan?.name ?? ''}`}
            onClose={onClose}
            footer={
                <>
                    <Button variant="secondary" onClick={onClose}>Cancelar</Button>
                    <Button form="plan-form" type="submit" loading={loading}>Salvar</Button>
                </>
            }
        >
            <form id="plan-form" onSubmit={submit} className="space-y-4" noValidate>
                <Input label="Nome" value={name} required
                       onChange={(e) => setName(e.target.value)} error={fieldError(errors, 'name')} />

                <Input label="Preço mensal (R$)" value={price} inputMode="decimal" required
                       onChange={(e) => setPrice(e.target.value)} error={fieldError(errors, 'price')} />

                <div className="grid gap-4 sm:grid-cols-3">
                    <Input label="Máx. produtos" value={maxProducts} inputMode="numeric" placeholder="ilimitado"
                           onChange={(e) => setMaxProducts(e.target.value)} error={fieldError(errors, 'max_products')} />
                    <Input label="Máx. categorias" value={maxCategories} inputMode="numeric" placeholder="ilimitado"
                           onChange={(e) => setMaxCategories(e.target.value)} error={fieldError(errors, 'max_categories')} />
                    <Input label="Máx. estabelec." value={maxEstablishments} inputMode="numeric" placeholder="ilimitado"
                           onChange={(e) => setMaxEstablishments(e.target.value)} />
                </div>

                <fieldset>
                    <legend className="label">Recursos incluídos</legend>
                    <div className="space-y-1.5">
                        {FEATURES.map(([value, label]) => (
                            <label key={value} className="tap flex items-center gap-2.5 rounded-lg px-1 text-sm">
                                <input
                                    type="checkbox"
                                    checked={features.includes(value)}
                                    onChange={(event) =>
                                        setFeatures((current) =>
                                            event.target.checked
                                                ? [...current, value]
                                                : current.filter((item) => item !== value),
                                        )
                                    }
                                    className="size-4 accent-[var(--color-brand)]"
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                </fieldset>

                <Toggle label="Plano disponível para contratação" checked={isActive} onChange={setIsActive} />
            </form>
        </Modal>
    );
}
