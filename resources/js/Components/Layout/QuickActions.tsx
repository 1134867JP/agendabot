import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { PageProps, Tenant } from '@/types';

interface SharedProps extends PageProps {
    currentTenant?: Tenant | null;
}

const PlusIcon = () => (
    <svg aria-hidden="true" focusable="false" width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
        <path d="M12 5v14M5 12h14" />
    </svg>
);

export default function QuickActions() {
    const page = usePage<SharedProps>();
    const { currentTenant } = page.props;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const buttonRef = useRef<HTMLButtonElement>(null);
    const modoAgendamento = (currentTenant?.modo_bot ?? 'agendamento') === 'agendamento';

    useEffect(() => {
        if (!open) return;

        const closeOnOutsideClick = (event: PointerEvent) => {
            if (ref.current && !ref.current.contains(event.target as Node)) setOpen(false);
        };

        const closeOnEscape = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') return;

            setOpen(false);
            buttonRef.current?.focus();
        };

        document.addEventListener('pointerdown', closeOnOutsideClick);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('pointerdown', closeOnOutsideClick);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open]);

    if (!currentTenant || page.url.startsWith('/painel/agendamentos')) return null;

    const actions = [
        ...(modoAgendamento ? [
            {
                href: route('tenant.agendamentos.index', { novo: 1 }),
                label: 'Novo agendamento',
                description: 'Reserve um horário manualmente',
                icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            },
            {
                href: route('tenant.agenda'),
                label: 'Abrir agenda',
                description: 'Encontre horários disponíveis',
                icon: 'M3 12h18M12 3v18',
            },
        ] : []),
        {
            href: route('tenant.conversas.index', { nova: 1 }),
            label: 'Nova conversa',
            description: 'Inicie um atendimento no WhatsApp',
            icon: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z',
        },
        {
            href: route('tenant.clientes.index'),
            label: 'Localizar cliente',
            description: 'Consulte histórico e contatos',
            icon: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z',
        },
    ];

    return (
        <div ref={ref} className="fixed bottom-[calc(5.5rem+env(safe-area-inset-bottom))] right-4 z-40 flex max-w-[calc(100vw-2rem)] flex-col items-end lg:bottom-6 lg:right-6">
            {open && (
                <nav
                    id="quick-actions-menu"
                    aria-labelledby="quick-actions-title"
                    className="mb-3 max-h-[calc(100dvh-11rem)] w-[min(21rem,calc(100vw-2rem))] overflow-x-hidden overflow-y-auto overscroll-contain rounded-2xl shadow-2xl"
                    style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}
                >
                    <div className="px-4 py-3" style={{ borderBottom: '1px solid var(--border)' }}>
                        <p id="quick-actions-title" className="text-sm font-semibold text-primary">Ação rápida</p>
                        <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                            {modoAgendamento ? 'Agende ou atenda sem procurar no menu.' : 'Atenda clientes sem sair do seu fluxo.'}
                        </p>
                    </div>
                    <div className="p-2">
                        {actions.map(action => (
                            <Link
                                key={action.label}
                                href={action.href}
                                onClick={() => setOpen(false)}
                                className="flex min-h-14 items-center gap-3 rounded-xl px-3 py-2.5 transition-colors hover:bg-[var(--bg-surface-2)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-[var(--accent)] active:bg-[var(--bg-surface-2)] motion-reduce:transition-none"
                            >
                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}>
                                    <svg aria-hidden="true" focusable="false" width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                                        <path d={action.icon} />
                                    </svg>
                                </span>
                                <span className="min-w-0">
                                    <span className="block text-sm font-medium text-primary">{action.label}</span>
                                    <span className="block text-xs" style={{ color: 'var(--text-3)' }}>{action.description}</span>
                                </span>
                            </Link>
                        ))}
                    </div>
                </nav>
            )}

            <button
                ref={buttonRef}
                type="button"
                onClick={() => setOpen(value => !value)}
                aria-controls="quick-actions-menu"
                aria-expanded={open}
                aria-label={open ? 'Fechar ações rápidas' : 'Abrir ações rápidas'}
                className="flex h-14 min-w-14 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold text-white shadow-lg transition-all hover:-translate-y-0.5 hover:brightness-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--accent)] active:translate-y-0 active:scale-[0.98] motion-reduce:transform-none motion-reduce:transition-none"
                style={{ background: 'var(--jade)', boxShadow: '0 12px 30px rgba(0,168,132,.24)' }}
            >
                <span aria-hidden="true" className={'transition-transform motion-reduce:transition-none ' + (open ? 'rotate-45' : '')}><PlusIcon /></span>
                <span>{open ? 'Fechar' : 'Novo'}</span>
            </button>
        </div>
    );
}
