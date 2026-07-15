import { usePage, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { PageProps, SubscriptionInfo } from '@/types';
import Sidebar from '@/Components/Layout/Sidebar';
import SubscriptionBanner from '@/Components/Layout/SubscriptionBanner';
import { useNotificacoes } from '@/hooks/useNotificacoes';
import NotificacoesBell from '@/Components/NotificacoesBell';

// Tracks the visual viewport so the fullHeight chat layout stays pinned
// to the area above the iOS virtual keyboard.
//
// Strategy: use position:fixed + height:--app-height + translateY:--app-top.
// When iOS opens the keyboard it scrolls the layout viewport (pageTop > 0)
// to reveal the focused input. A fixed element ignores that scroll, but its
// height shrinks via --app-height (= visualViewport.height). The translateY
// compensates for any residual pageTop offset so nothing goes offscreen.
function useAppHeight() {
    useEffect(() => {
        const set = () => {
            const vv  = window.visualViewport;
            const h   = vv?.height  ?? window.innerHeight;
            const top = vv?.pageTop ?? 0;
            document.documentElement.style.setProperty('--app-height', `${h}px`);
            document.documentElement.style.setProperty('--app-top',    `${top}px`);
        };
        set();
        window.visualViewport?.addEventListener('resize', set);
        window.visualViewport?.addEventListener('scroll', set);
        return () => {
            window.visualViewport?.removeEventListener('resize', set);
            window.visualViewport?.removeEventListener('scroll', set);
        };
    }, []);
}

interface Props {
    children: React.ReactNode;
    title?: string;
    subtitle?: string;
    fullHeight?: boolean;
}

const MenuIcon = () => (
    <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <path d="M4 6h16M4 12h16M4 18h16" />
    </svg>
);

export default function AppLayout({ children, title, subtitle, fullHeight }: Props) {
    useAppHeight();

    const page = usePage<PageProps<{
        currentTenant?: { id: number; nome: string; slug: string } | null;
        flash?: { success?: string; erro?: string };
        subscription?: SubscriptionInfo | null;
    }>>();

    const { currentTenant, flash, subscription } = page.props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const { conversasNaoLidas, conversasPreview } = useNotificacoes(!!currentTenant);

    return (
        <div
            className={
                fullHeight
                    ? 'flex overflow-hidden'
                    : 'min-h-[100dvh] lg:flex lg:h-[100dvh] lg:overflow-hidden'
            }
            style={fullHeight ? {
                // Fixed positioning keeps the chat anchored to the visual
                // viewport regardless of iOS scrolling the layout viewport.
                position: 'fixed',
                top: 0,
                left: 0,
                right: 0,
                height: 'var(--app-height, 100dvh)',
                transform: 'translateY(var(--app-top, 0px))',
                background: 'var(--bg-app)',
                color: 'var(--text-1)',
            } : {
                background: 'var(--bg-app)',
                color: 'var(--text-1)',
            }}
        >
            {/* Skip link */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
                style={{ background: 'var(--accent)' }}
            >
                Pular para o conteúdo
            </a>

            {/* Sidebar */}
            <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />

            {/* Coluna principal */}
            <div className={`flex min-w-0 flex-1 flex-col ${fullHeight ? 'overflow-hidden' : 'lg:overflow-hidden'}`}>

                {/* Barra mobile — sticky no scroll da janela, oculta no desktop */}
                <header
                    className="sticky top-0 z-10 flex min-h-14 items-center gap-2 px-3 py-2.5 sm:gap-3 sm:px-4 sm:py-3 lg:hidden"
                    style={{ background: 'var(--bg-sidebar)', borderBottom: '1px solid var(--border)' }}
                >
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-colors"
                        style={{ color: 'var(--text-2)' }}
                        aria-label="Abrir menu"
                    >
                        <MenuIcon />
                    </button>
                    <span className="text-base text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                        Agendou
                    </span>
                    {currentTenant && (
                        <span className="min-w-0 flex-1 truncate text-sm" style={{ color: 'var(--text-3)' }}>{currentTenant.nome}</span>
                    )}
                    {currentTenant && (
                        <div className="ml-auto">
                            <NotificacoesBell
                                count={conversasNaoLidas}
                                preview={conversasPreview}
                                size="md"
                                align="right"
                            />
                        </div>
                    )}
                </header>

                {/* Subscription banner */}
                {subscription && <SubscriptionBanner subscription={subscription} />}

                {/* Flash messages */}
                {flash?.success && (
                    <div
                        className="flash-msg mx-4 mt-3 flex items-start gap-2.5 rounded-lg px-3.5 py-3 text-sm sm:mx-5 sm:mt-4 sm:items-center sm:px-4"
                        style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)', color: '#6ee7b7' }}
                    >
                        <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        {flash.success}
                    </div>
                )}
                {flash?.erro && (
                    <div
                        className="flash-msg mx-4 mt-3 flex items-start gap-2.5 rounded-lg px-3.5 py-3 text-sm sm:mx-5 sm:mt-4 sm:items-center sm:px-4"
                        style={{ background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)', color: '#f87171' }}
                    >
                        <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        {flash.erro}
                    </div>
                )}

                {/* Conteúdo */}
                <main
                    id="main-content"
                    className={
                        fullHeight
                            ? 'flex flex-1 flex-col overflow-hidden min-h-0'
                            : 'p-4 sm:p-6 lg:flex-1 lg:min-h-0 lg:overflow-y-auto'
                    }
                >
                    {!fullHeight && title && (
                        <div className="mb-5">
                            <h1
                                className="text-xl font-semibold tracking-tight"
                                style={{ color: 'var(--text-1)' }}
                            >
                                {title}
                            </h1>
                            {subtitle && (
                                <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>{subtitle}</p>
                            )}
                            <div className="mt-4 h-px" style={{ background: 'var(--border)' }} />
                        </div>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
