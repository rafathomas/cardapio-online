export function Spinner({ label = 'Carregando' }: { label?: string }) {
    return (
        <div className="flex items-center justify-center gap-3 py-12 text-ink-muted" role="status">
            <span className="size-5 animate-spin rounded-full border-2 border-current border-t-transparent" aria-hidden="true" />
            <span className="text-sm">{label}...</span>
        </div>
    );
}
