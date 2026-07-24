import { Link, usePage } from '@inertiajs/react';
import { PageProps, Tenant } from '@/types';

interface SharedProps extends PageProps {
    currentTenant?: Tenant | null;
}

const items = [
    {
        label: 'Hoje',
        routeName: 'tenant.dashboard',
        path: '/painel',
        icon: 'M3 10.5 12 3l9 7.5M5 9.5V21h14V9.5M9 21v-7h6v7',
    },
    {
        label: 'Agenda',
        routeName: 'tenant.agenda',
        path: '/painel/agenda',
        activePaths: ['/painel/agenda', '/painel/agendamentos'],
        schedulingOnly: true,
        icon: 'M8 3v4m8-4v4M4 10h16M5 5h14a1 1 0 0 1 1 1v14H4V6a1 1 0 0 1 1-1Z',
    },
    {
        label: 'Conversas',
        routeName: 'tenant.conversas.index',
        path: '/painel/conversas',
        icon: 'M21 12a8 8 0 0 1-8 8 9 9 0 0 1-4-.9L3 20l1.2-3.4A8 8 0 1 1 21 12Z',
    },
    {
        label: 'Clientes',
        routeName: 'tenant.clientes.index',
        path: '/painel/clientes',
        icon: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87',
    },
];

export default function BottomNavigation() {
    const page = usePage<SharedProps>();
    const tenant = page.props.currentTenant;
    const currentUrl = page.url;

    if (!tenant) return null;

    const schedulingEnabled = (tenant.modo_bot ?? 'agendamento') === 'agendamento';
    const visibleItems = items.filter(item => !item.schedulingOnly || schedulingEnabled);

    const isActive = (item: typeof items[number]) => {
        if (item.activePaths) return item.activePaths.some(path => currentUrl === path || currentUrl.startsWith(`${path}/`));
        if (item.path === '/painel') return currentUrl === '/painel' || currentUrl === '/painel/';
        return currentUrl === item.path || currentUrl.startsWith(`${item.path}/`);
    };

    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-30 grid min-h-[4.5rem] lg:hidden"
            style={{
                gridTemplateColumns: `repeat(${visibleItems.length}, minmax(0, 1fr))`,
                paddingBottom: 'env(safe-area-inset-bottom)',
                background: 'color-mix(in srgb, var(--bg-sidebar) 94%, transparent)',
                borderTop: '1px solid var(--border-strong)',
                backdropFilter: 'blur(16px)',
            }}
            aria-label="Navegação principal"
        >
            {visibleItems.map(item => {
                const active = isActive(item);
                return (
                    <Link
                        key={item.routeName}
                        href={route(item.routeName)}
                        className="relative flex min-h-[4.5rem] flex-col items-center justify-center gap-1 px-1 text-[11px] font-medium transition-colors"
                        style={{ color: active ? 'var(--accent)' : 'var(--text-3)' }}
                        aria-current={active ? 'page' : undefined}
                    >
                        {active && (
                            <span
                                className="absolute top-0 h-0.5 w-8 rounded-b-full"
                                style={{ background: 'var(--accent)' }}
                                aria-hidden
                            />
                        )}
                        <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={active ? 2 : 1.7} strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                            <path d={item.icon} />
                        </svg>
                        <span>{item.label}</span>
                    </Link>
                );
            })}
        </nav>
    );
}
