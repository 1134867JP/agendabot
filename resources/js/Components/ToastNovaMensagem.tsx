import { useEffect } from 'react';
import { Link } from '@inertiajs/react';

interface Props {
    visivel: boolean;
    onFechar: () => void;
}

export default function ToastNovaMensagem({ visivel, onFechar }: Props) {
    useEffect(() => {
        if (!visivel) return;
        const t = setTimeout(onFechar, 5000);
        return () => clearTimeout(t);
    }, [visivel, onFechar]);

    if (!visivel) return null;

    return (
        <div
            className="fixed bottom-[calc(5.5rem+env(safe-area-inset-bottom))] left-4 right-4 z-50 flex items-center gap-3 rounded-xl px-4 py-3 shadow-xl sm:left-auto sm:min-w-60 lg:bottom-5 lg:right-5"
            style={{
                background: 'var(--bg-surface)',
                border: '1px solid var(--border)',
                animation: 'slideUp 0.25s ease',
            }}
            role="status"
            aria-live="polite"
        >
            {/* Ícone */}
            <div
                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl"
                style={{ background: 'rgba(0,168,132,0.15)' }}
            >
                <svg width={15} height={15} viewBox="0 0 24 24" fill="none" stroke="#00a884" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                </svg>
            </div>

            {/* Texto */}
            <div className="flex-1">
                <p className="text-xs font-semibold" style={{ color: 'var(--text-1)' }}>Nova mensagem</p>
                <Link
                    href={route('tenant.conversas.index')}
                    className="text-[11px] hover:underline"
                    style={{ color: 'var(--accent)' }}
                    onClick={onFechar}
                >
                    Ver conversas →
                </Link>
            </div>

            {/* Fechar */}
            <button
                type="button"
                onClick={onFechar}
                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg transition-colors hover:bg-white/10"
                style={{ color: 'var(--text-3)' }}
                aria-label="Fechar notificação"
            >
                <svg width={10} height={10} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round">
                    <path d="M18 6L6 18M6 6l12 12" />
                </svg>
            </button>

            <style>{`
                @keyframes slideUp {
                    from { transform: translateY(12px); opacity: 0; }
                    to   { transform: translateY(0);    opacity: 1; }
                }
            `}</style>
        </div>
    );
}
