import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { PageProps, TipoServico } from '@/types';
import { buildConfigTabs } from '@/lib/configTabs';

export default function ConfiguracoesTabs() {
    const page = usePage<PageProps<{
        currentTenant?: { tipo_servico: TipoServico } | null;
        tenantPapel?: string | null;
    }>>();
    const containerRef = useRef<HTMLDivElement>(null);

    const { auth, currentTenant, tenantPapel } = page.props as typeof page.props & { tenantPapel?: string | null };
    const isAdmin = auth.user.is_super_admin || tenantPapel === 'admin';
    const tipo    = currentTenant?.tipo_servico ?? 'personalizado';
    const url     = page.url;

    const tabs = buildConfigTabs(tipo, isAdmin);
    const isActive = (path: string) => url === path || url.startsWith(path + '/');

    useEffect(() => {
        const container = containerRef.current;
        const activeTab = container?.querySelector<HTMLElement>('[aria-current="page"]');
        if (!container || !activeTab) return;

        const left = activeTab.offsetLeft - (container.clientWidth - activeTab.offsetWidth) / 2;
        container.scrollTo({ left: Math.max(0, left), behavior: 'auto' });
    }, [url]);

    return (
        <div ref={containerRef} className="mb-6 -mt-2 overflow-x-auto scroll-hidden">
            <div className="flex min-w-max gap-0.5" style={{ borderBottom: '1px solid var(--border)' }}>
                {tabs.map(tab => {
                    const active = isActive(tab.path);
                    return (
                        <Link
                            key={tab.routeName}
                            href={route(tab.routeName)}
                            preserveScroll
                            aria-current={active ? 'page' : undefined}
                            className={`config-tab${active ? ' config-tab--active' : ''}`}
                        >
                            <svg width={15} height={15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                                <path d={tab.icon} />
                            </svg>
                            {tab.label}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
