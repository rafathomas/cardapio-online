import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';
import { useId } from 'react';
import { Icon } from './Icon';

interface BaseProps {
    label: string;
    hint?: string;
    error?: string;
    required?: boolean;
    children?: ReactNode;
}

/** Envelope com rótulo visível, dica e erro junto ao campo. */
export function Field({ label, hint, error, required, children, id }: BaseProps & { id: string }) {
    return (
        <div>
            <label className="label" htmlFor={id}>
                {label}
                {required && <span className="ml-0.5 text-danger" aria-hidden="true">*</span>}
            </label>
            {children}
            {hint && !error && <p className="hint">{hint}</p>}
            {error && (
                <p className="error" role="alert">
                    <Icon name="alert" className="mt-0.5 size-3.5 shrink-0" />
                    {error}
                </p>
            )}
        </div>
    );
}

type InputProps = BaseProps & InputHTMLAttributes<HTMLInputElement>;

export function Input({ label, hint, error, required, className = '', ...props }: InputProps) {
    const generated = useId();
    const id = props.id ?? generated;

    return (
        <Field id={id} label={label} hint={hint} error={error} required={required}>
            <input
                {...props}
                id={id}
                required={required}
                aria-invalid={error ? true : undefined}
                className={`field ${error ? 'border-danger focus:border-danger focus:ring-danger/20' : ''} ${className}`}
            />
        </Field>
    );
}

type TextareaProps = BaseProps & TextareaHTMLAttributes<HTMLTextAreaElement>;

export function Textarea({ label, hint, error, required, className = '', ...props }: TextareaProps) {
    const generated = useId();
    const id = props.id ?? generated;

    return (
        <Field id={id} label={label} hint={hint} error={error} required={required}>
            <textarea
                {...props}
                id={id}
                required={required}
                aria-invalid={error ? true : undefined}
                className={`field resize-none ${error ? 'border-danger' : ''} ${className}`}
            />
        </Field>
    );
}

type SelectProps = BaseProps & SelectHTMLAttributes<HTMLSelectElement>;

export function Select({ label, hint, error, required, children, className = '', ...props }: SelectProps) {
    const generated = useId();
    const id = props.id ?? generated;

    return (
        <Field id={id} label={label} hint={hint} error={error} required={required}>
            <select
                {...props}
                id={id}
                required={required}
                aria-invalid={error ? true : undefined}
                className={`field ${error ? 'border-danger' : ''} ${className}`}
            >
                {children}
            </select>
        </Field>
    );
}

/** Interruptor acessível para preferências booleanas. */
export function Toggle({
    label, hint, checked, onChange, disabled,
}: {
    label: string;
    hint?: string;
    checked: boolean;
    onChange: (value: boolean) => void;
    disabled?: boolean;
}) {
    const id = useId();

    return (
        <div className="flex items-start justify-between gap-4">
            <div>
                <label htmlFor={id} className="cursor-pointer text-sm font-semibold text-ink">{label}</label>
                {hint && <p className="hint">{hint}</p>}
            </div>
            <button
                type="button"
                id={id}
                role="switch"
                aria-checked={checked}
                disabled={disabled}
                onClick={() => onChange(!checked)}
                className={`relative h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors duration-200 disabled:cursor-not-allowed disabled:opacity-50 ${
                    checked ? 'bg-brand' : 'bg-slate-300'
                }`}
            >
                <span
                    className={`absolute top-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200 ${
                        checked ? 'translate-x-5.5' : 'translate-x-0.5'
                    }`}
                />
            </button>
        </div>
    );
}
