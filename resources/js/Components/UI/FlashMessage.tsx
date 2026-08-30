import { useEffect, useState } from 'react';

type FlashTone = 'success' | 'error';

interface Props {
    message: string;
    tone: FlashTone;
    duration?: number;
}

export default function FlashMessage({ message, tone, duration = 6000 }: Props) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        setVisible(true);
        const timeout = window.setTimeout(() => setVisible(false), duration);
        return () => window.clearTimeout(timeout);
    }, [message, duration]);

    if (!visible) return null;

    const success = tone === 'success';

    return (
        <div
            className="flash-msg mx-4 mt-3 flex items-start gap-2.5 rounded-lg px-3.5 py-3 text-sm sm:mx-5 sm:mt-4 sm:items-center sm:px-4"
            style={success
                ? { background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)', color: '#6ee7b7' }
                : { background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)', color: '#f87171' }}
            role={success ? 'status' : 'alert'}
            aria-live={success ? 'polite' : 'assertive'}
        >
            {success ? (
                <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 shrink-0 sm:mt-0" aria-hidden>
                    <polyline points="20 6 9 17 4 12" />
                </svg>
            ) : (
                <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 shrink-0 sm:mt-0" aria-hidden>
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
            )}

            <span className="min-w-0 flex-1">{message}</span>

            <button
                type="button"
                onClick={() => setVisible(false)}
                className="-mr-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-md transition-colors hover:bg-black/10"
                aria-label="Fechar aviso"
            >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
    );
}
