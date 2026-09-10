import { useRef, useState } from 'react';
import { api } from '../api';
import { useApp, useEstablishment } from '../AppContext';
import { fieldError, toFailure, type ApiErrors } from '../../lib/http';
import { Button } from '../../components/ui/Button';
import { Card, CardBody, CardHeader } from '../../components/ui/Card';
import { Icon } from '../../components/ui/Icon';
import { useToast } from '../../components/ui/Toast';

const PRESETS = ['#DC2626', '#EA580C', '#D97706', '#16A34A', '#0891B2', '#2563EB', '#7C3AED', '#DB2777', '#0F172A'];

export function CustomizationPage() {
    const establishment = useEstablishment();
    const { refreshEstablishments } = useApp();
    const toast = useToast();

    const logoInput = useRef<HTMLInputElement>(null);
    const coverInput = useRef<HTMLInputElement>(null);

    const [primaryColor, setPrimaryColor] = useState(establishment.primary_color);
    const [secondaryColor, setSecondaryColor] = useState(establishment.secondary_color);
    const [logoPreview, setLogoPreview] = useState(establishment.logo_url);
    const [coverPreview, setCoverPreview] = useState(establishment.cover_url);
    const [errors, setErrors] = useState<ApiErrors>({});
    const [loading, setLoading] = useState(false);

    const save = async (event: React.FormEvent) => {
        event.preventDefault();
        setLoading(true);
        setErrors({});

        const payload = {
            name: establishment.name,
            segment: establishment.segment,
            whatsapp: establishment.whatsapp,
            primary_color: primaryColor,
            secondary_color: secondaryColor,
            logo: logoInput.current?.files?.[0] ?? null,
            cover: coverInput.current?.files?.[0] ?? null,
        };

        try {
            await api.updateEstablishment(establishment.id, payload);
            await refreshEstablishments();
            toast.success('Aparência atualizada.');
        } catch (error) {
            const failure = toFailure(error);
            setErrors(failure.errors);

            if (failure.errorCode === 'plan_feature_unavailable') {
                toast.error(`${failure.message} Faça upgrade para o Pro.`);
            } else if (Object.keys(failure.errors).length === 0) {
                toast.error(failure.message);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={save} className="space-y-5" noValidate>
            <div>
                <h1 className="font-display text-2xl text-ink">Personalização</h1>
                <p className="mt-1 text-sm text-ink-muted">
                    Logo, capa e cores do seu cardápio público. Cores e capa fazem parte do plano Pro.
                </p>
            </div>

            <Card>
                <CardHeader title="Identidade visual" />
                <CardBody className="space-y-6">
                    <div>
                        <span className="label">Logo</span>
                        <div className="flex items-center gap-4">
                            {logoPreview ? (
                                <img src={logoPreview} alt="Logo atual" className="size-20 rounded-xl object-cover ring-1 ring-line" />
                            ) : (
                                <div className="flex size-20 items-center justify-center rounded-xl bg-surface-muted text-ink-muted">
                                    <Icon name="store" />
                                </div>
                            )}

                            <input
                                ref={logoInput} type="file" accept="image/jpeg,image/png,image/webp"
                                aria-label="Escolher logo"
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    setLogoPreview(file ? URL.createObjectURL(file) : establishment.logo_url);
                                }}
                                className="text-sm file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-surface-muted file:px-3 file:py-2 file:text-sm file:font-semibold"
                            />
                        </div>
                        {fieldError(errors, 'logo') && <p className="error">{fieldError(errors, 'logo')}</p>}
                        <p className="hint">Quadrada fica melhor. Redimensionamos para 512px.</p>
                    </div>

                    <div>
                        <span className="label">Foto de capa</span>
                        <div className="space-y-3">
                            {coverPreview ? (
                                <img src={coverPreview} alt="Capa atual" className="h-28 w-full rounded-xl object-cover" />
                            ) : (
                                <div className="flex h-28 items-center justify-center rounded-xl bg-surface-muted text-ink-muted">
                                    <Icon name="package" />
                                </div>
                            )}

                            <input
                                ref={coverInput} type="file" accept="image/jpeg,image/png,image/webp"
                                aria-label="Escolher foto de capa"
                                onChange={(event) => {
                                    const file = event.target.files?.[0];
                                    setCoverPreview(file ? URL.createObjectURL(file) : establishment.cover_url);
                                }}
                                className="text-sm file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-surface-muted file:px-3 file:py-2 file:text-sm file:font-semibold"
                            />
                        </div>
                        {fieldError(errors, 'cover') && <p className="error">{fieldError(errors, 'cover')}</p>}
                    </div>
                </CardBody>
            </Card>

            <Card>
                <CardHeader title="Cores" description="A cor principal é usada nos botões e destaques do cardápio." />
                <CardBody className="space-y-5">
                    <div>
                        <span className="label">Cor principal</span>
                        <div className="flex flex-wrap gap-2">
                            {PRESETS.map((color) => (
                                <button
                                    key={color} type="button" onClick={() => setPrimaryColor(color)}
                                    aria-label={`Cor ${color}`} aria-pressed={primaryColor === color}
                                    style={{ backgroundColor: color }}
                                    className={`tap size-11 rounded-xl ring-offset-2 ${primaryColor === color ? 'ring-2 ring-ink' : ''}`}
                                />
                            ))}
                            <label className="tap flex size-11 items-center justify-center rounded-xl border border-line">
                                <input type="color" value={primaryColor} aria-label="Escolher cor personalizada"
                                       onChange={(event) => setPrimaryColor(event.target.value)}
                                       className="size-8 cursor-pointer border-0 bg-transparent p-0" />
                            </label>
                        </div>
                        {fieldError(errors, 'primary_color') && <p className="error">{fieldError(errors, 'primary_color')}</p>}
                    </div>

                    <div>
                        <span className="label">Cor do texto</span>
                        <label className="tap inline-flex items-center gap-3 rounded-xl border border-line p-2">
                            <input type="color" value={secondaryColor} aria-label="Cor do texto"
                                   onChange={(event) => setSecondaryColor(event.target.value)}
                                   className="size-9 cursor-pointer border-0 bg-transparent p-0" />
                            <span className="pr-2 font-mono text-sm">{secondaryColor}</span>
                        </label>
                    </div>

                    {/* Prévia ao vivo das cores escolhidas. */}
                    <div className="rounded-xl border border-line p-4" style={{ color: secondaryColor }}>
                        <p className="text-xs font-semibold tracking-wide uppercase opacity-60">Prévia</p>
                        <p className="mt-2 font-display text-lg">{establishment.name}</p>
                        <p className="mt-3 flex items-center justify-between rounded-lg border border-line p-2.5">
                            <span className="text-sm">X-Bacon</span>
                            <span className="rounded-lg px-3 py-1.5 text-sm font-semibold text-white"
                                  style={{ backgroundColor: primaryColor }}>
                                Adicionar
                            </span>
                        </p>
                    </div>
                </CardBody>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" loading={loading} size="lg">Salvar aparência</Button>
            </div>
        </form>
    );
}
