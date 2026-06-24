import { Link, router, usePage } from '@inertiajs/react';
import { PageProps, SubscriptionInfo } from '@/types';
import { useTheme } from '@/Components/ThemeProvider';

interface NavItem {
    label: string;
    routeName: string;
    path: string;
    icon: string;
}

const SECTIONS_TENANT: { label: string; items: NavItem[] }[] = [
    {
        label: 'Principal',
        items: [
            { label: 'Dashboard',    routeName: 'tenant.dashboard',          path: '/painel',              icon: 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z M9 22V12h6v10' },
            { label: 'Agenda',       routeName: 'tenant.agenda',             path: '/painel/agenda',       icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
            { label: 'Agendamentos', routeName: 'tenant.agendamentos.index', path: '/painel/agendamentos', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
            { label: 'Conversas',    routeName: 'tenant.conversas.index',    path: '/painel/conversas',    icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
        ],
    },
    {
        label: 'Configurar',
        items: [
            { label: 'Recursos',      routeName: 'tenant.recursos.index',     path: '/painel/recursos',      icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
            { label: 'Profissionais', routeName: 'tenant.profissionais.index', path: '/painel/profissionais', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
            { label: 'Serviços',      routeName: 'tenant.servicos.index',      path: '/painel/servicos',      icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
            { label: 'WhatsApp',      routeName: 'tenant.whatsapp',           path: '/painel/whatsapp',      icon: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z' },
            { label: 'Configurações', routeName: 'tenant.configuracoes.index',path: '/painel/configuracoes', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
        ],
    },
];

const SECTIONS_SUPER_ADMIN: { label: string; items: NavItem[] }[] = [
    {
        label: 'Admin',
        items: [
            { label: 'Dashboard', routeName: 'superadmin.dashboard',     path: '/superadmin',         icon: 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z M9 22V12h6v10' },
            { label: 'Tenants',   routeName: 'superadmin.tenants.index', path: '/superadmin/tenants', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
            { label: 'Agendamentos', routeName: 'superadmin.agendamentos', path: '/superadmin/agendamentos', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
            { label: 'Logs',         routeName: 'superadmin.logs',         path: '/superadmin/logs',         icon: 'M4 6h16M4 10h16M4 14h16M4 18h7' },
        ],
    },
];

const Icon = ({ d }: { d: string }) => (
    <svg width={15} height={15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <path d={d} />
    </svg>
);

interface SidebarProps {
    open: boolean;
    onClose: () => void;
}

export default function Sidebar({ open, onClose }: SidebarProps) {
    const { theme, toggle } = useTheme();
    const page = usePage<PageProps<{
        currentTenant?: { id: number; nome: string; slug: string } | null;
        impersonando?: boolean;
        subscription?: SubscriptionInfo | null;
    }>>();

    const { auth, currentTenant, impersonando } = page.props;
    const isSuperAdmin = auth.user.is_super_admin;
    const currentUrl   = page.url;

    const sections = (isSuperAdmin && !currentTenant) ? SECTIONS_SUPER_ADMIN : SECTIONS_TENANT;

    const isNavActive = (item: NavItem): boolean => {
        if (item.path === '/painel') {
            return currentUrl === '/painel' || currentUrl === '/painel/';
        }
        return currentUrl.startsWith(item.path);
    };

    const pararImpersonar = () => router.delete(route('superadmin.impersonar.parar'));

    return (
        <>
            {/* Mobile overlay */}
            {open && (
                <div
                    className="fixed inset-0 z-20 bg-black/60 backdrop-blur-sm lg:hidden"
                    onClick={onClose}
                />
            )}

            <aside
                className={`
                    fixed inset-y-0 left-0 z-30 flex w-52 flex-col
                    transition-transform duration-300 ease-in-out lg:static lg:translate-x-0 lg:z-auto
                    ${open ? 'translate-x-0' : '-translate-x-full'}
                `}
                style={{ background: 'var(--bg-sidebar)', borderRight: '1px solid var(--border)' }}
            >
                {/* Logo */}
                <div className="px-4 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                    <span className="flex items-center gap-2 text-[17px] text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif', letterSpacing: '-0.01em' }}>
                        <span
                            className="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[9px] font-bold text-white"
                            style={{ background: 'var(--jade)' }}
                        >
                            A
                        </span>
                        AgendaBot
                    </span>
                    {currentTenant && (
                        <span className="mt-1.5 block truncate text-[11px]" style={{ color: 'var(--text-3)' }}>
                            {currentTenant.nome}
                        </span>
                    )}
                    {isSuperAdmin && !currentTenant && (
                        <span className="mt-1 block text-[11px]" style={{ color: 'var(--accent)' }}>Super Admin</span>
                    )}
                </div>

                {/* Impersonation notice */}
                {impersonando && currentTenant && (
                    <div className="px-4 py-2 text-center text-[11px]" style={{ background: 'rgba(99,102,241,0.12)', borderBottom: '1px solid var(--border)' }}>
                        <span style={{ color: 'var(--accent)' }}>Modo suporte: </span>
                        <span className="text-primary">{currentTenant.nome}</span>
                    </div>
                )}

                {/* Nav */}
                <nav className="flex-1 overflow-y-auto py-2">
                    {sections.map(section => (
                        <div key={section.label} className="mb-3">
                            <span
                                className="mb-1 block px-4 text-[9px] font-semibold uppercase tracking-[0.12em]"
                                style={{ color: 'var(--text-3)' }}
                            >
                                {section.label}
                            </span>
                            <div className="space-y-0.5 px-2">
                                {section.items.map(item => {
                                    const active = isNavActive(item);
                                    return (
                                        <Link
                                            key={item.routeName}
                                            href={route(item.routeName)}
                                            onClick={onClose}
                                            className="flex items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] transition-all"
                                            style={{
                                                color:      active ? 'var(--jade)' : 'var(--text-2)',
                                                background: active ? 'var(--jade-light)' : 'transparent',
                                                fontWeight: active ? '500' : '400',
                                            }}
                                            onMouseEnter={e => { if (!active) { (e.currentTarget as HTMLElement).style.color = 'var(--text-1)'; (e.currentTarget as HTMLElement).style.background = 'var(--bg-surface-2)'; } }}
                                            onMouseLeave={e => { if (!active) { (e.currentTarget as HTMLElement).style.color = 'var(--text-2)'; (e.currentTarget as HTMLElement).style.background = 'transparent'; } }}
                                        >
                                            <span style={{ color: active ? 'var(--jade)' : 'var(--text-3)', flexShrink: 0 }}>
                                                <Icon d={item.icon} />
                                            </span>
                                            {item.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </nav>

                {/* Footer */}
                <div className="px-5 py-4" style={{ borderTop: '1px solid var(--border)' }}>
                    {impersonando && (
                        <button
                            onClick={pararImpersonar}
                            className="mb-3 w-full rounded-md border py-1.5 text-[11px] transition-colors"
                            style={{ borderColor: 'rgba(99,102,241,0.3)', color: 'var(--accent)' }}
                            onMouseEnter={e => (e.currentTarget.style.background = 'var(--accent-light)')}
                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                        >
                            ← Sair da impersonação
                        </button>
                    )}
                    <button
                        onClick={toggle}
                        className="mb-3 flex items-center gap-2 rounded-md px-2 py-1.5 text-[11px] transition-colors w-full"
                        style={{ color: 'var(--text-3)', background: 'transparent' }}
                        onMouseEnter={e => { (e.currentTarget as HTMLElement).style.background = 'var(--bg-surface-2)'; (e.currentTarget as HTMLElement).style.color = 'var(--text-2)'; }}
                        onMouseLeave={e => { (e.currentTarget as HTMLElement).style.background = 'transparent'; (e.currentTarget as HTMLElement).style.color = 'var(--text-3)'; }}
                        title={theme === 'dark' ? 'Mudar para modo claro' : 'Mudar para modo escuro'}
                    >
                        {theme === 'dark' ? (
                            <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                                <circle cx="12" cy="12" r="5" />
                                <line x1="12" y1="1" x2="12" y2="3" />
                                <line x1="12" y1="21" x2="12" y2="23" />
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
                                <line x1="1" y1="12" x2="3" y2="12" />
                                <line x1="21" y1="12" x2="23" y2="12" />
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
                            </svg>
                        ) : (
                            <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                                <path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z" />
                            </svg>
                        )}
                        {theme === 'dark' ? 'Modo claro' : 'Modo escuro'}
                    </button>

                    <p className="mb-1.5 truncate text-[12px]" style={{ color: 'var(--text-2)' }}>{auth.user.name}</p>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="flex items-center gap-1.5 text-[11px] transition-colors"
                        style={{ color: 'var(--text-3)' }}
                        onMouseEnter={e => (e.currentTarget.style.color = 'var(--text-2)')}
                        onMouseLeave={e => (e.currentTarget.style.color = 'var(--text-3)')}
                    >
                        <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                            <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Sair
                    </Link>
                </div>
            </aside>
        </>
    );
}
