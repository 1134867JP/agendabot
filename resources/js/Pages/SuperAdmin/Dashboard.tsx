import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Tenant, PaginatedData } from '@/types';

interface Stats {
    total_tenants: number;
    tenants_ativos: number;
    tenants_conectados: number;
    agendamentos_hoje: number;
    agendamentos_mes: number;
    failed_jobs: number;
    erros_24h: number;
    tenants_sem_config: number;
}

interface TenantWithCounts extends Tenant {
    agendamentos_count: number;
    recursos_count: number;
}

interface Props extends PageProps {
    stats: Stats;
    tenants: PaginatedData<TenantWithCounts>;
}

const TIPO_LABEL: Record<string, string> = {
    barbeiro: 'Barbearia', quadra: 'Quadra', estetica: 'Estética', personalizado: 'Personalizado',
};

function StatCard({
    label, value, sub, danger = false, warning = false, accent = false, href,
}: {
    label: string; value: number | string; sub?: string;
    danger?: boolean; warning?: boolean; accent?: boolean; href?: string;
}) {
    const bg    = danger ? 'rgba(239,68,68,0.08)'   : warning ? 'rgba(245,158,11,0.08)' : accent ? 'var(--accent)' : 'var(--bg-surface)';
    const bdr   = danger ? 'rgba(239,68,68,0.25)'   : warning ? 'rgba(245,158,11,0.25)' : accent ? 'transparent' : 'var(--border)';
    const color = danger ? '#f87171'                 : warning ? '#fbbf24'               : accent ? 'rgba(255,255,255,0.6)' : 'var(--text-3)';
    const val   = danger ? '#f87171'                 : warning ? '#fbbf24'               : accent ? 'white' : 'var(--text-1)';

    const inner = (
        <div className="rounded-xl p-5" style={{ background: bg, border: `1px solid ${bdr}` }}>
            <div className="flex items-start justify-between">
                <p className="text-[10px] font-semibold uppercase tracking-widest" style={{ color }}>{label}</p>
                {(danger || warning) && (
                    <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ color }}>
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                )}
            </div>
            <p className="mt-2 text-3xl font-bold leading-none" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: val }}>
                {value}
            </p>
            {sub && <p className="mt-1 text-xs" style={{ color }}>{sub}</p>}
        </div>
    );

    return href ? <Link href={href}>{inner}</Link> : inner;
}

export default function SuperAdminDashboard({ stats, tenants }: Props) {
    const impersonar = (t: TenantWithCounts) => {
        if (confirm(`Entrar como "${t.nome}"?`)) {
            router.post(route('superadmin.tenants.impersonar', t.id));
        }
    };

    const toggleAtivo = (t: TenantWithCounts) => {
        router.patch(route('superadmin.tenants.toggle-ativo', t.id));
    };

    const temProblemas = stats.failed_jobs > 0 || stats.erros_24h > 0;

    return (
        <AppLayout title="Visão Geral">
            <Head title="Super Admin" />

            <div className="space-y-7">
                {/* Alerta de problemas */}
                {temProblemas && (
                    <div
                        className="flex items-center gap-3 rounded-xl px-5 py-4 text-sm"
                        style={{ background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.25)' }}
                    >
                        <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="#f87171" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <span style={{ color: '#f87171' }}>
                            {stats.failed_jobs > 0 && `${stats.failed_jobs} job(s) falhado(s) na fila.`}
                            {stats.failed_jobs > 0 && stats.erros_24h > 0 && ' · '}
                            {stats.erros_24h > 0 && `${stats.erros_24h} erro(s) nas últimas 24h.`}
                        </span>
                        <Link
                            href={route('superadmin.jobs')}
                            className="ml-auto shrink-0 rounded-lg px-3 py-1.5 text-xs font-medium transition-all hover:brightness-110"
                            style={{ background: 'rgba(239,68,68,0.15)', color: '#f87171' }}
                        >
                            Ver jobs →
                        </Link>
                    </div>
                )}

                {/* Stats */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4">
                    <StatCard label="Jobs falhados"  value={stats.failed_jobs} danger={stats.failed_jobs > 0}   href={route('superadmin.jobs')} sub={stats.failed_jobs > 0 ? 'Clique para ver' : 'Tudo ok'} />
                    <StatCard label="Erros (24h)"    value={stats.erros_24h}   danger={stats.erros_24h > 5}     warning={stats.erros_24h > 0 && stats.erros_24h <= 5} href={route('superadmin.logs')} sub="No log de erros" />
                    <StatCard label="Sem WhatsApp"   value={stats.tenants_sem_config} warning={stats.tenants_sem_config > 0} sub="Tenants ativos desconectados" />
                    <StatCard label="Agendamentos hoje" value={stats.agendamentos_hoje} accent sub="em todos os tenants" />
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="Total de tenants"    value={stats.total_tenants} />
                    <StatCard label="Tenants ativos"      value={stats.tenants_ativos} />
                    <StatCard label="WhatsApp conectados" value={stats.tenants_conectados} />
                </div>

                {/* Tenants table */}
                <div className="card overflow-hidden">
                    <div className="flex items-center justify-between px-6 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                        <h2 className="text-sm font-semibold text-primary">Tenants</h2>
                        <a href={route('superadmin.tenants.create')} className="btn-primary py-1.5 text-xs">
                            + Novo tenant
                        </a>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
                                    {['Tenant', 'Tipo', 'WhatsApp', 'Recursos', 'Agendamentos', 'Status', 'Ações'].map(h => (
                                        <th key={h} className="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {tenants.data.map(t => {
                                    const semConfig = t.ativo && !t.whatsapp_conectado;
                                    return (
                                        <tr
                                            key={t.id}
                                            style={{ borderBottom: '1px solid var(--border)' }}
                                            onMouseEnter={e => (e.currentTarget.style.background = 'var(--bg-surface-2)')}
                                            onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    {semConfig && (
                                                        <span title="WhatsApp desconectado">
                                                            <svg width={12} height={12} viewBox="0 0 24 24" fill="none" stroke="#fbbf24" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                                            </svg>
                                                        </span>
                                                    )}
                                                    <div>
                                                        <p className="font-medium text-primary">{t.nome}</p>
                                                        <p className="text-[10px]" style={{ color: 'var(--text-3)' }}>{t.slug}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-2)' }}>
                                                {TIPO_LABEL[t.tipo_servico] ?? t.tipo_servico}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className={`badge ${t.whatsapp_conectado ? 'badge-green' : 'badge-amber'}`}>
                                                    <span className={`h-1.5 w-1.5 rounded-full ${t.whatsapp_conectado ? 'bg-emerald-500 animate-pulse' : 'bg-amber-400'}`} />
                                                    {t.whatsapp_conectado ? 'Conectado' : 'Desconectado'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-sm" style={{ color: 'var(--text-2)' }}>
                                                {t.recursos_count}
                                            </td>
                                            <td className="px-4 py-3 text-sm" style={{ color: 'var(--text-2)' }}>
                                                {t.agendamentos_count}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className={`badge ${t.ativo ? 'badge-green' : 'badge-red'}`}>
                                                    {t.ativo ? 'Ativo' : 'Inativo'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-1.5">
                                                    <button
                                                        onClick={() => impersonar(t)}
                                                        className="rounded-lg px-2.5 py-1 text-[11px] font-medium transition-all"
                                                        style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
                                                        onMouseEnter={e => (e.currentTarget.style.background = 'rgba(99,102,241,0.22)')}
                                                        onMouseLeave={e => (e.currentTarget.style.background = 'var(--accent-light)')}
                                                    >
                                                        Entrar
                                                    </button>
                                                    <button
                                                        onClick={() => toggleAtivo(t)}
                                                        className="rounded-lg px-2.5 py-1 text-[11px] font-medium transition-all"
                                                        style={{ background: 'rgba(255,255,255,0.05)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                                        onMouseEnter={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.09)')}
                                                        onMouseLeave={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.05)')}
                                                    >
                                                        {t.ativo ? 'Desativar' : 'Ativar'}
                                                    </button>
                                                    <a
                                                        href={route('superadmin.tenants.edit', t.id)}
                                                        className="rounded-lg px-2.5 py-1 text-[11px] font-medium transition-all"
                                                        style={{ background: 'rgba(255,255,255,0.05)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                                        onMouseEnter={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.09)')}
                                                        onMouseLeave={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.05)')}
                                                    >
                                                        Editar
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {tenants.last_page > 1 && (
                        <div className="flex gap-1 px-4 py-3" style={{ borderTop: '1px solid var(--border)' }}>
                            {tenants.links.map((link, i) => (
                                <button
                                    key={i}
                                    disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url)}
                                    className="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors disabled:opacity-40"
                                    style={link.active
                                        ? { background: 'var(--accent)', borderColor: 'var(--accent)', color: 'white' }
                                        : { borderColor: 'var(--border-strong)', color: 'var(--text-2)' }
                                    }
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
