import { useState } from 'react';
import { api } from '../api';
import { useApp, useEstablishment } from '../AppContext';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Button } from '../../components/ui/Button';
import { Card, CardBody, CardHeader } from '../../components/ui/Card';
import { Input, Select, Textarea, Toggle } from '../../components/ui/Field';
import { useToast } from '../../components/ui/Toast';
import type { BusinessHour } from '../types';

const SEGMENTS = [
    ['lanchonete', 'Lanchonete'], ['pizzaria', 'Pizzaria'], ['acaiteria', 'Açaiteria'],
    ['cafeteria', 'Cafeteria'], ['restaurante', 'Restaurante'], ['bar', 'Bar'],
    ['confeitaria', 'Confeitaria'], ['padaria', 'Padaria'], ['sorveteria', 'Sorveteria'],
    ['outro', 'Outro'],
] as const;

const WEEKDAYS = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

export function SettingsPage() {
    const establishment = useEstablishment();
    const { refreshEstablishments } = useApp();
    const toast = useToast();

    const [form, setForm] = useState({
        name: establishment.name,
        legal_name: establishment.legal_name ?? '',
        slug: establishment.slug,
        segment: establishment.segment,
        description: establishment.description ?? '',
        phone: establishment.phone ?? '',
        whatsapp: establishment.whatsapp,
        instagram: establishment.instagram ?? '',
        address_street: establishment.address.street ?? '',
        address_number: establishment.address.number ?? '',
        address_complement: establishment.address.complement ?? '',
        address_district: establishment.address.district ?? '',
        address_city: establishment.address.city ?? '',
        address_state: establishment.address.state ?? '',
        address_zipcode: establishment.address.zipcode ?? '',
        manual_status: establishment.manual_status ?? '',
    });
    const [isIndexable, setIsIndexable] = useState(establishment.is_indexable);

    const [hours, setHours] = useState<BusinessHour[]>(
        establishment.business_hours ?? WEEKDAYS.map((label, weekday) => ({
            weekday,
            weekday_label: label,
            is_closed: false,
            opens_at: '18:00',
            closes_at: '23:00',
        })),
    );

    const [errors, setErrors] = useState<ApiErrors>({});
    const [savingProfile, setSavingProfile] = useState(false);
    const [savingHours, setSavingHours] = useState(false);

    const set = (key: keyof typeof form) => (event: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) =>
        setForm((current) => ({ ...current, [key]: event.target.value }));

    const saveProfile = async (event: React.FormEvent) => {
        event.preventDefault();
        setSavingProfile(true);
        setErrors({});

        try {
            await api.updateEstablishment(establishment.id, {
                ...form,
                manual_status: form.manual_status || null,
                is_indexable: isIndexable,
            });

            await refreshEstablishments();
            toast.success('Configurações salvas.');
        } catch (error) {
            const failure = toFailure(error);
            setErrors(failure.errors);

            if (Object.keys(failure.errors).length === 0) toast.error(failure.message);
        } finally {
            setSavingProfile(false);
        }
    };

    const saveHours = async () => {
        setSavingHours(true);

        try {
            await api.updateBusinessHours(establishment.id, hours.map((hour) => ({
                weekday: hour.weekday,
                is_closed: hour.is_closed,
                opens_at: hour.is_closed ? null : hour.opens_at,
                closes_at: hour.is_closed ? null : hour.closes_at,
            })));

            await refreshEstablishments();
            toast.success('Horários atualizados.');
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setSavingHours(false);
        }
    };

    const updateHour = (weekday: number, patch: Partial<BusinessHour>) => {
        setHours((current) => current.map((hour) => (hour.weekday === weekday ? { ...hour, ...patch } : hour)));
    };

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">Configurações</h1>
                <p className="mt-1 text-sm text-ink-muted">Dados do estabelecimento, endereço e horário de funcionamento.</p>
            </div>

            <form onSubmit={saveProfile} className="space-y-5" noValidate>
                <Card>
                    <CardHeader title="Dados do estabelecimento" />
                    <CardBody className="space-y-4">
                        <Input label="Nome do estabelecimento" value={form.name} required
                               onChange={set('name')} error={fieldError(errors, 'name')} />

                        <Input label="Razão social (opcional)" value={form.legal_name}
                               onChange={set('legal_name')} error={fieldError(errors, 'legal_name')} />

                        <Input label="Link do cardápio" value={form.slug}
                               hint={`Seu cardápio fica em /cardapio/${form.slug}`}
                               onChange={set('slug')} error={fieldError(errors, 'slug')} />

                        <Select label="Segmento" value={form.segment} required
                                onChange={set('segment')} error={fieldError(errors, 'segment')}>
                            {SEGMENTS.map(([value, label]) => (
                                <option key={value} value={value}>{label}</option>
                            ))}
                        </Select>

                        <Textarea label="Descrição" value={form.description} rows={3} maxLength={1000}
                                  onChange={set('description')} error={fieldError(errors, 'description')} />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Contato" />
                    <CardBody className="space-y-4">
                        <Input label="WhatsApp para pedidos" value={form.whatsapp} required inputMode="tel"
                               hint="É o número que recebe os pedidos do cardápio."
                               onChange={set('whatsapp')} error={fieldError(errors, 'whatsapp')} />

                        <Input label="Telefone fixo (opcional)" value={form.phone} inputMode="tel"
                               onChange={set('phone')} error={fieldError(errors, 'phone')} />

                        <Input label="Instagram (opcional)" value={form.instagram} placeholder="seunegocio"
                               onChange={set('instagram')} error={fieldError(errors, 'instagram')} />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Endereço" />
                    <CardBody className="grid gap-4 sm:grid-cols-2">
                        <Input label="Rua" value={form.address_street} onChange={set('address_street')} />
                        <Input label="Número" value={form.address_number} onChange={set('address_number')} />
                        <Input label="Complemento" value={form.address_complement} onChange={set('address_complement')} />
                        <Input label="Bairro" value={form.address_district} onChange={set('address_district')} />
                        <Input label="Cidade" value={form.address_city} onChange={set('address_city')} />
                        <Input label="Estado (UF)" value={form.address_state} maxLength={2}
                               onChange={set('address_state')} error={fieldError(errors, 'address_state')} />
                        <Input label="CEP" value={form.address_zipcode} inputMode="numeric"
                               onChange={set('address_zipcode')} error={fieldError(errors, 'address_zipcode')} />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="Disponibilidade e busca" />
                    <CardBody className="space-y-4">
                        <Select label="Status do estabelecimento" value={form.manual_status} onChange={set('manual_status')}
                                hint="Automático usa o horário de funcionamento abaixo.">
                            <option value="">Automático (segue o horário)</option>
                            <option value="open">Sempre aberto</option>
                            <option value="closed">Fechado temporariamente</option>
                        </Select>

                        <Toggle
                            label="Permitir que o Google indexe meu cardápio"
                            checked={isIndexable}
                            onChange={setIsIndexable}
                            hint="Desligado, seu cardápio continua acessível pelo link, mas sai dos buscadores."
                        />
                    </CardBody>
                </Card>

                <div className="flex justify-end">
                    <Button type="submit" loading={savingProfile} size="lg">Salvar configurações</Button>
                </div>
            </form>

            <Card>
                <CardHeader title="Horário de funcionamento"
                            description="Define quando o cardápio aceita pedidos automaticamente." />
                <CardBody className="space-y-3">
                    {hours.map((hour) => (
                        <div key={hour.weekday} className="flex flex-wrap items-center gap-3 border-b border-line pb-3 last:border-0 last:pb-0">
                            <span className="w-32 text-sm font-medium text-ink">{WEEKDAYS[hour.weekday]}</span>

                            <label className="tap flex items-center gap-2 text-sm text-ink-muted">
                                <input
                                    type="checkbox"
                                    checked={!hour.is_closed}
                                    onChange={(event) => updateHour(hour.weekday, { is_closed: !event.target.checked })}
                                    className="size-4 accent-[var(--color-brand)]"
                                />
                                Aberto
                            </label>

                            {!hour.is_closed && (
                                <div className="flex items-center gap-2">
                                    <input
                                        type="time" value={hour.opens_at ?? '18:00'}
                                        aria-label={`Abre ${WEEKDAYS[hour.weekday]}`}
                                        onChange={(event) => updateHour(hour.weekday, { opens_at: event.target.value })}
                                        className="field w-32 py-1.5"
                                    />
                                    <span className="text-ink-muted">até</span>
                                    <input
                                        type="time" value={hour.closes_at ?? '23:00'}
                                        aria-label={`Fecha ${WEEKDAYS[hour.weekday]}`}
                                        onChange={(event) => updateHour(hour.weekday, { closes_at: event.target.value })}
                                        className="field w-32 py-1.5"
                                    />
                                </div>
                            )}
                        </div>
                    ))}

                    <div className="flex justify-end pt-2">
                        <Button onClick={saveHours} loading={savingHours}>Salvar horários</Button>
                    </div>
                </CardBody>
            </Card>
        </div>
    );
}
