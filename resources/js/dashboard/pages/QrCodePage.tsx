import { useEffect, useState } from 'react';
import { api } from '../api';
import { useEstablishment } from '../AppContext';
import { toFailure } from '../../lib/http';
import { Button } from '../../components/ui/Button';
import { Card, CardBody, CardHeader } from '../../components/ui/Card';
import { ConfirmDialog } from '../../components/ui/ConfirmDialog';
import { Icon } from '../../components/ui/Icon';
import { Spinner } from '../../components/ui/Spinner';
import { useToast } from '../../components/ui/Toast';
import type { QrCode } from '../types';

export function QrCodePage() {
    const establishment = useEstablishment();
    const toast = useToast();

    const [qrCode, setQrCode] = useState<QrCode | null>(null);
    const [loading, setLoading] = useState(true);
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        let active = true;
        setLoading(true);

        api.qrCode(establishment.id)
            .then((result) => active && setQrCode(result))
            .catch((error) => active && toast.error(toFailure(error).message))
            .finally(() => active && setLoading(false));

        return () => { active = false; };
    }, [establishment.id, toast]);

    const regenerate = async () => {
        setBusy(true);

        try {
            setQrCode(await api.regenerateQrCode(establishment.id));
            toast.success('QR Code gerado novamente.');
            setConfirming(false);
        } catch (error) {
            toast.error(toFailure(error).message);
        } finally {
            setBusy(false);
        }
    };

    const downloadUrl = (format: 'png' | 'svg') =>
        `/api/establishments/${establishment.id}/qrcode/download?format=${format}`;

    return (
        <div className="space-y-5">
            <div>
                <h1 className="font-display text-2xl text-ink">QR Code</h1>
                <p className="mt-1 text-sm text-ink-muted">
                    Imprima e coloque nas mesas, no balcão ou na vitrine. Ele aponta sempre para o seu cardápio.
                </p>
            </div>

            <Card>
                <CardHeader title="Seu QR Code" description={qrCode ? `Versão ${qrCode.version}` : undefined} />

                {loading ? (
                    <Spinner />
                ) : (
                    <CardBody className="space-y-5">
                        <div className="flex justify-center">
                            {qrCode?.image_url ? (
                                <img
                                    src={qrCode.image_url}
                                    alt={`QR Code do cardápio de ${establishment.name}`}
                                    className="size-56 rounded-2xl border border-line bg-white p-3"
                                    width="224" height="224"
                                />
                            ) : (
                                <div className="flex size-56 items-center justify-center rounded-2xl border border-line text-ink-muted">
                                    <Icon name="qr" className="size-10" />
                                </div>
                            )}
                        </div>

                        <p className="text-center text-sm break-all text-ink-muted">{qrCode?.target_url}</p>

                        <div className="grid gap-2 sm:grid-cols-3">
                            <a href={downloadUrl('png')} className="btn-secondary" download>
                                <Icon name="download" className="size-4" />
                                Baixar PNG
                            </a>
                            <a href={downloadUrl('svg')} className="btn-secondary" download>
                                <Icon name="download" className="size-4" />
                                Baixar SVG
                            </a>
                            <Button variant="secondary" icon="external" onClick={() => window.print()}>
                                Imprimir
                            </Button>
                        </div>

                        <div className="border-t border-line pt-4">
                            <Button variant="ghost" icon="refresh" onClick={() => setConfirming(true)}>
                                Gerar novamente
                            </Button>
                            <p className="hint">
                                Use se o arquivo se perdeu ou se você mudou o link do cardápio. Materiais já impressos
                                com o link antigo deixam de funcionar.
                            </p>
                        </div>
                    </CardBody>
                )}
            </Card>

            <ConfirmDialog
                open={confirming}
                title="Gerar novo QR Code"
                message="O arquivo atual é substituído. Se você já imprimiu materiais com o link antigo, eles precisarão ser reimpressos."
                confirmLabel="Gerar novamente"
                loading={busy}
                onConfirm={regenerate}
                onCancel={() => setConfirming(false)}
            />
        </div>
    );
}
