import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { PageProps, SubscriptionInfo } from '@/types';
import Sidebar from '@/Components/Layout/Sidebar';
import SubscriptionBanner from '@/Components/Layout/SubscriptionBanner';

interface Props {
    children: React.ReactNode;
    title?: string;
    fullHeight?: boolean;
}

const MenuIcon = () => (
    <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <path d="M4 6h16M4 12h16M4 18h16" />
    </svg>
);

export default function AppLayout({ children, title, fullHeight }: Props) {
    const page = usePage<PageProps<{
        currentTenant?: { id: number; nome: string; slug: string } | null;
        flash?: { success?: string; erro?: string };
        subscription?: SubscriptionInfo | null;
    }>>();

    const { currentTenant, flash, subscription } = page.props;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    return (
        <div className="flex h-[100dvh] overflow-hidden" style={{ background: 'var(--bg-app)', color: 'var(--text-1)' }}>
            {/* Skip link para navegação por teclado */}
            <a
                href="#main-content"
                className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
                style={{ background: 'var(--accent)' }}
            >
                Pular para o conteúdo
            </a>

            {/* Sidebar */}
            <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />

            {/* Main */}
            <div className="flex min-w-0 flex-1 flex-col overflow-hidden">

                {/* Mobile top bar */}
                <header
                    className="flex items-center gap-3 px-4 py-3 lg:hidden"
                    style={{ background: 'var(--bg-sidebar)', borderBottom: '1px solid var(--border)' }}
                >
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="rounded-md p-1 transition-colors"
                        style={{ color: 'var(--text-2)' }}
                    >
                        <MenuIcon />
                    </button>
                    <span className="text-base text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                        AgendaBot
                    </span>
                    {currentTenant && (
                        <span className="truncate text-sm" style={{ color: 'var(--text-3)' }}>{currentTenant.nome}</span>
                    )}
                </header>

                {/* Subscription banner */}
                {subscription && <SubscriptionBanner subscription={subscription} />}

                {/* Flash messages */}
                {flash?.success && (
                    <div
                        className="mx-5 mt-4 flex items-center gap-2.5 rounded-lg px-4 py-3 text-sm"
                        style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)', color: '#6ee7b7' }}
                    >
                        <span>✓</span>
                        {flash.success}
                    </div>
                )}
                {flash?.erro && (
                    <div
                        className="mx-5 mt-4 flex items-center gap-2.5 rounded-lg px-4 py-3 text-sm"
                        style={{ background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)', color: '#f87171' }}
                    >
                        <span>⚠</span>
                        {flash.erro}
                    </div>
                )}

                {/* Page content */}
                <main id="main-content" className={`flex-1 min-h-0 ${fullHeight ? 'overflow-hidden flex flex-col' : 'overflow-y-auto p-6'}`}>
                    {!fullHeight && title && (
                        <h1 className="mb-6 text-2xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>
                            {title}
                        </h1>
                    )}
                    {children}
                </main>
            </div>
        </div>
    );
}
