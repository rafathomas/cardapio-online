import { Button } from './Button';
import { Modal } from './Modal';

interface Props {
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    destructive?: boolean;
    loading?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

export function ConfirmDialog({
    open, title, message, confirmLabel = 'Confirmar',
    destructive = false, loading = false, onConfirm, onCancel,
}: Props) {
    return (
        <Modal
            open={open}
            title={title}
            onClose={onCancel}
            size="sm"
            footer={
                <>
                    <Button variant="secondary" onClick={onCancel} disabled={loading}>Cancelar</Button>
                    <Button variant={destructive ? 'danger' : 'primary'} onClick={onConfirm} loading={loading}>
                        {confirmLabel}
                    </Button>
                </>
            }
        >
            <p className="text-ink-muted">{message}</p>
        </Modal>
    );
}
