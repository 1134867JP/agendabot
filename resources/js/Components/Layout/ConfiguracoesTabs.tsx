import { Link, router, usePage } from '@inertiajs/react';
import { PageProps, TipoServico } from '@/types';
import { buildConfigTabs } from '@/lib/configTabs';

export default function ConfiguracoesTabs() {
    const page = usePage<PageProps<{
        currentTenant?: { tipo_servico: TipoServico } | null;
        tenantPapel?: string | null;
    }>>();
    const { auth, currentTenant, tenantPapel } = page.props as typeof page.props & { tenantPapel?: string | null };
    const isAdmin = auth.user.is_super_admin || tenantPapel === 'admin';
    const tipo    = currentTenant?.tipo_servico ?? 'personalizado';
    const url     = page.url;

    const tabs = buildConfigTabs(tipo, isAdmin);
    const isActive = (path: string) => url === path || url.startsWith(path + '/');

    const activeTab = tabs.find(tab => isActive(tab.path)) ?? tabs[0];

    return (
        <div className="mb-6 -mt-2">
            <div className="sm:hidden">
                <label htmlFor="config-section" className="mb-1.5 block text-xs font-medium text-muted">
                    Seção de configurações
                </label>
                <div className="relative">
                    <select
                        id="config-section"
                        value={activeTab.routeName}
                        onChange={(event) => router.visit(route(event.target.value))}
                        className="input w-full appearance-none pr-10 font-medium"
                    >
                        {tabs.map(tab => (
                            <option key={tab.routeName} value={tab.routeName}>{tab.label}</option>
                        ))}
                    </select>
                    <svg
                        className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-muted"
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <path d="m6 9 6 6 6-6" />
                    </svg>
                </div>
            </div>

            <nav className="hidden overflow-x-auto scroll-hidden sm:block" aria-label="Configurações">
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
            </nav>
        </div>
    );
}
