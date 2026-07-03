import { Link, router, usePage } from '@inertiajs/react';
import { PageProps, SubscriptionInfo, TipoServico } from '@/types';
import { useTheme } from '@/Components/ThemeProvider';
import { useNotificacoes } from '@/hooks/useNotificacoes';
import ToastNovaMensagem from '@/Components/ToastNovaMensagem';

interface NavItem {
    label: string;
    routeName: string;
    path: string;
    icon: string;
    tipos?: TipoServico[];
    adminOnly?: boolean; // só admin do tenant ou superadmin vê
}

const SECTIONS_TENANT: { label: string; items: NavItem[] }[] = [
    {
        label: 'Principal',
        items: [
            { label: 'Dashboard',    routeName: 'tenant.dashboard',          path: '/painel',              icon: 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z M9 22V12h6v10' },
            { label: 'Agenda',       routeName: 'tenant.agenda',             path: '/painel/agenda',       icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
            { label: 'Agendamentos', routeName: 'tenant.agendamentos.index', path: '/painel/agendamentos', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
            { label: 'Conversas',    routeName: 'tenant.conversas.index',    path: '/painel/conversas',    icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z' },
            { label: 'Clientes',     routeName: 'tenant.clientes.index',     path: '/painel/clientes',     icon: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75M9 7a4 4 0 110 8 4 4 0 010-8z' },
            { label: 'Analytics',    routeName: 'tenant.analytics',          path: '/painel/analytics',    icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
            { label: 'Equipe',       routeName: 'tenant.equipe.index',       path: '/painel/equipe',       icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', adminOnly: true },
        ],
    },
    {
        label: 'Configurar',
        items: [
            { label: 'Recursos',      routeName: 'tenant.recursos.index',     path: '/painel/recursos',      icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',      tipos: ['quadra', 'personalizado'] },
            { label: 'Profissionais', routeName: 'tenant.profissionais.index', path: '/painel/profissionais', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', tipos: ['barbeiro', 'estetica', 'clinica', 'studio', 'personalizado'] },
            { label: 'Serviços',      routeName: 'tenant.servicos.index',      path: '/painel/servicos',      icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',      tipos: ['barbeiro', 'estetica', 'clinica', 'studio', 'personalizado'] },
            { label: 'WhatsApp',      routeName: 'tenant.whatsapp',           path: '/painel/whatsapp',      icon: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z' },
            { label: 'Configurações', routeName: 'tenant.configuracoes.index',path: '/painel/configuracoes', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
            { label: 'Triagem',       routeName: 'tenant.triagem.index',      path: '/painel/triagem',       icon: 'M22 12h-4l-3 9L9 3l-3 9H2' },
            { label: 'Regras de agendamento', routeName: 'tenant.regras-agendamento.index', path: '/painel/regras-agendamento', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
        ],
    },
];

const SECTIONS_SUPER_ADMIN: { label: string; items: NavItem[] }[] = [
    {
        label: 'Admin',
        items: [
            { label: 'Dashboard', routeName: 'superadmin.dashboard',     path: '/superadmin',         icon: 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z M9 22V12h6v10' },
            { label: 'Tenants',   routeName: 'superadmin.tenants.index', path: '/superadmin/tenants', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
            { label: 'Financeiro',   routeName: 'superadmin.financeiro',   path: '/superadmin/financeiro',   icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
            { label: 'Logs',         routeName: 'superadmin.logs',         path: '/superadmin/logs',         icon: 'M4 6h16M4 10h16M4 14h16M4 18h7' },
            { label: 'Jobs',         routeName: 'superadmin.jobs',         path: '/superadmin/jobs',         icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
            { label: 'Tokens IA',    routeName: 'superadmin.tokens',       path: '/superadmin/tokens',       icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z M9 11l3-3 3 3' },
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
        currentTenant?: { id: number; nome: string; slug: string; tipo_servico: TipoServico } | null;
        impersonando?: boolean;
        subscription?: SubscriptionInfo | null;
    }>>();

    const { auth, currentTenant, impersonando, tenantPapel } = page.props as typeof page.props & { tenantPapel?: string | null };
    const isSuperAdmin = auth.user.is_super_admin;
    const isAdmin      = isSuperAdmin || tenantPapel === 'admin';
    const currentUrl   = page.url;

    const { conversasNaoLidas, novaMensagem, resetarNovaMensagem } = useNotificacoes(!!currentTenant);

    const tipoAtual = currentTenant?.tipo_servico ?? 'personalizado';

    const filterItems = (items: NavItem[]) =>
        items.filter(item =>
            (!item.tipos || item.tipos.includes(tipoAtual)) &&
            (!item.adminOnly || isAdmin)
        );

    const rawSections = (isSuperAdmin && !currentTenant) ? SECTIONS_SUPER_ADMIN : SECTIONS_TENANT;
    const sections = rawSections.map(section => ({
        ...section,
        items: filterItems(section.items),
    })).filter(section => section.items.length > 0);

    const isNavActive = (item: NavItem): boolean => {
        if (item.path === '/painel' || item.path === '/superadmin') {
            return currentUrl === item.path || currentUrl === item.path + '/';
        }
        return currentUrl === item.path || currentUrl.startsWith(item.path + '/');
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
                    <div className="flex items-center justify-between">
                        <span className="flex items-center gap-2 text-[17px] text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif', letterSpacing: '-0.01em' }}>
                            <span
                                className="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-lg"
                                style={{ background: 'var(--jade)' }}
                            >
                                <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                    <polyline points="9 16 11 18 15 14"/>
                                </svg>
                            </span>
                            AgendaBot
                        </span>
                    </div>
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
                <nav className="scroll-hidden flex-1 overflow-y-auto py-2">
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
                                            className={`nav-item${active ? ' active' : ''}`}
                                        >
                                            <span style={{ flexShrink: 0 }}>
                                                <Icon d={item.icon} />
                                            </span>
                                            <span className="flex-1">{item.label}</span>
                                            {item.routeName === 'tenant.conversas.index' && conversasNaoLidas > 0 && (
                                                <span
                                                    className="ml-auto flex h-4 min-w-[16px] items-center justify-center rounded-full px-1 text-[10px] font-bold text-white"
                                                    style={{ background: '#00a884' }}
                                                >
                                                    {conversasNaoLidas > 99 ? '99+' : conversasNaoLidas}
                                                </span>
                                            )}
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
                            className="mb-3 w-full rounded-md border py-1.5 text-[11px] transition-colors hover:bg-accent/10"
                            style={{ borderColor: 'rgba(99,102,241,0.3)', color: 'var(--accent)' }}
                        >
                            ← Sair da impersonação
                        </button>
                    )}
                    <button
                        onClick={toggle}
                        className="sidebar-btn mb-3 flex items-center gap-2 px-2 py-1.5 text-[11px] w-full"
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

                    <Link
                        href={route('profile.edit')}
                        className="sidebar-btn mb-1 flex items-center gap-1.5 px-2 py-1 text-[11px]"
                    >
                        <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                            <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        {auth.user.name}
                    </Link>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="sidebar-btn flex items-center gap-1.5 px-2 py-1 text-[11px]"
                    >
                        <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                            <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Sair
                    </Link>
                </div>
            </aside>
            <ToastNovaMensagem visivel={novaMensagem} onFechar={resetarNovaMensagem} />
        </>
    );
}
