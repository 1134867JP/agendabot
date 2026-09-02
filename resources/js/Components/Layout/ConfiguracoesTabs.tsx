import { Link, usePage } from '@inertiajs/react';
import { PageProps, TipoServico } from '@/types';
import { buildConfigTabs } from '@/lib/configTabs';

export default function ConfiguracoesTabs() {
    const page = usePage<PageProps<{
        currentTenant?: { tipo_servico: TipoServico; agenda?: { modo?: string; recursos?: string; profissionais?: string } } | null;
        tenantPapel?: string | null;
    }>>();
    const { auth, currentTenant, tenantPapel } = page.props as typeof page.props & { tenantPapel?: string | null };
    const isAdmin = auth.user.is_super_admin || tenantPapel === 'admin';
    const tipo    = currentTenant?.tipo_servico ?? 'personalizado';
    const url     = page.url;

    const tabs = buildConfigTabs(tipo, isAdmin, currentTenant?.agenda);
    const isActive = (path: string) => url === path || url.startsWith(path + '/');

    const mobileLabel = (routeName: string, label: string) => ({
        'tenant.configuracoes.index': 'Empresa',
        'tenant.profissionais.index': 'Atendentes',
        'tenant.opcoes-extras.index': 'Extras',
        'tenant.regras-agendamento.index': 'Regras',
        'tenant.equipe.index': 'Acessos',
    }[routeName] ?? label);

    return (
        <div className="mb-6 -mt-2">
            <nav className="sm:hidden" aria-label="Configurações">
                <div
                    className="grid grid-cols-4 gap-px overflow-hidden rounded-xl border"
                    style={{ background: 'var(--border)', borderColor: 'var(--border)' }}
                >
                    {tabs.map(tab => {
                        const active = isActive(tab.path);
                        return (
                            <Link
                                key={tab.routeName}
                                href={route(tab.routeName)}
                                aria-current={active ? 'page' : undefined}
                                aria-label={tab.label}
                                className="flex min-h-16 min-w-0 flex-col items-center justify-center gap-1 px-1 py-2 text-center transition-colors focus-visible:relative focus-visible:z-10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[var(--accent)]"
                                style={{
                                    background: active ? 'var(--accent-light)' : 'var(--bg-surface)',
                                    color: active ? 'var(--accent)' : 'var(--text-3)',
                                }}
                            >
                                <svg width={19} height={19} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                                    <path d={tab.icon} />
                                </svg>
                                <span className="w-full truncate text-[11px] font-medium leading-tight">
                                    {mobileLabel(tab.routeName, tab.label)}
                                </span>
                            </Link>
                        );
                    })}
                </div>
            </nav>

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
