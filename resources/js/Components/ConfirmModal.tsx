import { useEffect, useId, useRef } from 'react';

interface Props {
    open: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'danger' | 'warning' | 'default';
    onConfirm: () => void;
    onCancel: () => void;
}

export default function ConfirmModal({
    open, title, message, confirmLabel = 'Confirmar', cancelLabel = 'Cancelar',
    variant = 'default', onConfirm, onCancel,
}: Props) {
    const confirmRef = useRef<HTMLButtonElement>(null);
    const titleId = useId();
    const descriptionId = useId();

    useEffect(() => {
        if (!open) return;
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        confirmRef.current?.focus();
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onCancel();
            if (e.key === 'Enter') onConfirm();
        };
        document.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKey);
        };
    }, [open, onCancel, onConfirm]);

    if (!open) return null;

    const iconColor = variant === 'danger' ? '#f87171' : variant === 'warning' ? '#fbbf24' : 'var(--accent)';
    const btnBg    = variant === 'danger' ? 'rgba(239,68,68,0.15)' : variant === 'warning' ? 'rgba(251,191,36,0.15)' : 'var(--accent)';
    const btnColor = variant === 'danger' ? '#f87171' : variant === 'warning' ? '#fbbf24' : '#fff';
    const btnBorder = variant === 'danger' ? '1px solid rgba(239,68,68,0.3)' : variant === 'warning' ? '1px solid rgba(251,191,36,0.3)' : 'none';

    return (
        <div
            className="fixed inset-0 z-[9999] flex items-end justify-center sm:items-center sm:px-4"
            style={{ background: 'rgba(0,0,0,0.45)', backdropFilter: 'blur(4px)' }}
            onClick={e => { if (e.target === e.currentTarget) onCancel(); }}
            role="dialog"
            aria-modal="true"
            aria-labelledby={titleId}
            aria-describedby={descriptionId}
        >
            <div
                className="max-h-[92dvh] w-full max-w-sm overflow-y-auto overscroll-contain rounded-t-2xl p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-2xl sm:rounded-2xl sm:p-6"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}
            >
                {/* Ícone */}
                <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-full"
                    style={{ background: `${iconColor}18` }}>
                    {variant === 'danger' ? (
                        <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke={iconColor} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                        </svg>
                    ) : variant === 'warning' ? (
                        <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke={iconColor} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    ) : (
                        <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke={iconColor} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    )}
                </div>

                <h3 id={titleId} className="mb-1 text-base font-semibold text-primary">{title}</h3>
                <p id={descriptionId} className="mb-6 text-sm" style={{ color: 'var(--text-2)' }}>{message}</p>

                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="min-h-11 w-full rounded-lg px-4 py-2 text-sm font-medium transition-colors sm:w-auto"
                        style={{ background: 'var(--bg-surface-2)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                    >
                        {cancelLabel}
                    </button>
                    <button
                        ref={confirmRef}
                        type="button"
                        onClick={onConfirm}
                        className="min-h-11 w-full rounded-lg px-4 py-2 text-sm font-medium transition-colors hover:brightness-110 sm:w-auto"
                        style={{ background: btnBg, color: btnColor, border: btnBorder }}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}
