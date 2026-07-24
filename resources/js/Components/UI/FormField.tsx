import { ReactNode } from 'react';

interface FormFieldProps {
    label: string;
    htmlFor?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    children: ReactNode;
    className?: string;
}

export default function FormField({ label, htmlFor, hint, error, required, children, className = '' }: FormFieldProps) {
    return (
        <div className={className}>
            <label htmlFor={htmlFor} className="label mb-1.5">
                {label}
                {required && <span className="ml-1" style={{ color: 'var(--danger-text)' }} aria-hidden>*</span>}
            </label>
            {children}
            {error ? (
                <p className="mt-1.5 text-xs" style={{ color: 'var(--danger-text)' }}>{error}</p>
            ) : hint ? (
                <p className="mt-1.5 text-xs leading-5 text-muted">{hint}</p>
            ) : null}
        </div>
    );
}
