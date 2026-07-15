import { Link, usePage } from '@inertiajs/react';
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

    return (
        <div className="mb-6 -mt-2 overflow-x-auto scroll-hidden">
            <div className="flex min-w-max gap-1" style={{ borderBottom: '1px solid var(--border)' }}>
                {tabs.map(tab => {
                    const active = isActive(tab.path);
                    return (
                        <Link
                            key={tab.routeName}
                            href={route(tab.routeName)}
                            className="whitespace-nowrap px-3.5 py-2.5 text-sm font-medium transition-colors"
                            style={{
                                color: active ? 'var(--accent)' : 'var(--text-3)',
                                borderBottom: active ? '2px solid var(--accent)' : '2px solid transparent',
                                marginBottom: '-1px',
                            }}
                        >
                            {tab.label}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}
