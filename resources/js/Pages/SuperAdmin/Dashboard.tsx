import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { PageProps, Tenant, PaginatedData } from '@/types';

interface Stats {
    total_tenants: number;
    tenants_ativos: number;
    tenants_conectados: number;
    failed_jobs: number;
    erros_24h: number;
    tenants_sem_config: number;
}

interface TenantWithCounts extends Tenant {
    recursos_count: number;
}

interface AiProvider {
    provider: string;
    label: string;
    model: string;
    configurado: boolean;
}

interface AiStatus {
    padrao: AiProvider;
    fallbacks: AiProvider[];
    ultima_chamada: {
        provider: string;
        label: string;
        model: string;
        em: string;
    } | null;
}

interface Props extends PageProps {
    stats: Stats;
    ia: AiStatus;
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

export default function SuperAdminDashboard({ stats, ia, tenants }: Props) {
    const { confirm, modal: confirmModal } = useConfirm();

    const impersonar = async (t: TenantWithCounts) => {
        if (await confirm({
            title: 'Entrar no tenant',
            message: `Você passará a visualizar o sistema como "${t.nome}".`,
            confirmLabel: 'Entrar no tenant',
        })) {
            router.post(route('superadmin.tenants.impersonar', t.id));
        }
    };

    const toggleAtivo = async (t: TenantWithCounts) => {
        if (await confirm({
            title: t.ativo ? 'Desativar tenant' : 'Ativar tenant',
            message: t.ativo
                ? `"${t.nome}" perderá o acesso até ser ativado novamente.`
                : `"${t.nome}" voltará a ter acesso ao sistema.`,
            confirmLabel: t.ativo ? 'Desativar' : 'Ativar',
            variant: t.ativo ? 'warning' : 'default',
        })) router.patch(route('superadmin.tenants.toggle-ativo', t.id), {}, { preserveScroll: true });
    };

    const temProblemas = stats.failed_jobs > 0 || stats.erros_24h > 0;

    return (
        <AppLayout title="Visão Geral" subtitle="Monitoramento de todos os tenants e da plataforma">
            <Head title="Super Admin" />
            {confirmModal}

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
                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="Jobs falhados"  value={stats.failed_jobs} danger={stats.failed_jobs > 0}   href={route('superadmin.jobs')} sub={stats.failed_jobs > 0 ? 'Clique para ver' : 'Tudo ok'} />
                    <StatCard label="Erros (24h)"    value={stats.erros_24h}   danger={stats.erros_24h > 5}     warning={stats.erros_24h > 0 && stats.erros_24h <= 5} href={route('superadmin.logs')} sub="No log de erros" />
                    <StatCard label="Sem WhatsApp"   value={stats.tenants_sem_config} warning={stats.tenants_sem_config > 0} sub="Tenants ativos desconectados" />
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <StatCard label="Total de tenants"    value={stats.total_tenants} />
                    <StatCard label="Tenants ativos"      value={stats.tenants_ativos} />
                    <StatCard label="WhatsApp conectados" value={stats.tenants_conectados} />
                </div>

                <Link
                    href={route('superadmin.tokens')}
                    className="block rounded-xl p-5 transition-all hover:brightness-110"
                    style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
                >
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div className="flex items-center gap-2">
                                <span className={`h-2 w-2 rounded-full ${ia.padrao.configurado ? 'bg-emerald-400' : 'bg-red-400'}`} />
                                <p className="text-[10px] font-semibold uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>
                                    IA padrão
                                </p>
                            </div>
                            <p className="mt-2 text-xl font-semibold text-primary">
                                {ia.padrao.label}
                                <span className="ml-2 text-sm font-normal" style={{ color: 'var(--text-3)' }}>
                                    {ia.padrao.model}
                                </span>
                            </p>
                            <p className="mt-1 text-xs" style={{ color: ia.padrao.configurado ? '#34d399' : '#f87171' }}>
                                {ia.padrao.configurado ? 'Configurada e disponível' : 'Chave não configurada'}
                            </p>
                        </div>
                        <div className="sm:text-right">
                            <p className="text-[10px] font-semibold uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>
                                Última IA usada
                            </p>
                            <p className="mt-2 text-sm font-medium text-primary">
                                {ia.ultima_chamada
                                    ? `${ia.ultima_chamada.label} · ${ia.ultima_chamada.model}`
                                    : 'Nenhuma chamada registrada'}
                            </p>
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                Ver consumo e fallbacks →
                            </p>
                        </div>
                    </div>
                </Link>

                {/* Tenants table */}
                <div className="card overflow-hidden">
                    <div className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6" style={{ borderBottom: '1px solid var(--border)' }}>
                        <h2 className="text-sm font-semibold text-primary">Tenants</h2>
                        <Link href={route('superadmin.tenants.create')} className="btn-primary w-full justify-center py-2 text-xs sm:w-auto">
                            + Novo tenant
                        </Link>
                    </div>

                    <div className="hidden overflow-x-auto lg:block">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
                                    {['Tenant', 'Tipo', 'WhatsApp', 'Recursos', 'Status', 'Ações'].map(h => (
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
                                            className="table-row-hover"
                                            style={{ borderBottom: '1px solid var(--border)' }}
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
                                            <td className="px-4 py-3">
                                                <span className={`badge ${t.ativo ? 'badge-green' : 'badge-red'}`}>
                                                    {t.ativo ? 'Ativo' : 'Inativo'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex gap-1.5">
                                                    <button
                                                        onClick={() => impersonar(t)}
                                                        className="rounded-lg px-2.5 py-1 text-[11px] font-medium transition-all hover:brightness-125"
                                                        style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
                                                    >
                                                        Entrar
                                                    </button>
                                                    <button
                                                        onClick={() => toggleAtivo(t)}
                                                        className="rounded-lg px-2.5 py-1 text-[11px] font-medium transition-all hover:brightness-125"
                                                        style={{ background: 'rgba(255,255,255,0.05)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                                    >
                                                        {t.ativo ? 'Desativar' : 'Ativar'}
                                                    </button>
                                                    <Link
                                                        href={route('superadmin.tenants.edit', t.id)}
                                                        className="rounded-lg px-2.5 py-1 text-[11px] font-medium transition-all hover:brightness-125"
                                                        style={{ background: 'rgba(255,255,255,0.05)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                                    >
                                                        Editar
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="divide-y lg:hidden" style={{ borderColor: 'var(--border)' }}>
                        {tenants.data.map(t => (
                            <article key={t.id} className="space-y-4 p-4 sm:p-5">
                                <div className="flex min-w-0 items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="truncate font-semibold text-primary">{t.nome}</h3>
                                        <p className="break-all text-xs" style={{ color: 'var(--text-3)' }}>{t.slug}</p>
                                    </div>
                                    <span className={`badge shrink-0 ${t.ativo ? 'badge-green' : 'badge-red'}`}>
                                        {t.ativo ? 'Ativo' : 'Inativo'}
                                    </span>
                                </div>

                                <div className="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p className="text-[10px] font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>Tipo</p>
                                        <p className="mt-1" style={{ color: 'var(--text-2)' }}>{TIPO_LABEL[t.tipo_servico] ?? t.tipo_servico}</p>
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>Recursos</p>
                                        <p className="mt-1" style={{ color: 'var(--text-2)' }}>{t.recursos_count}</p>
                                    </div>
                                    <div className="col-span-2">
                                        <p className="text-[10px] font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>WhatsApp</p>
                                        <span className={`badge mt-1 ${t.whatsapp_conectado ? 'badge-green' : 'badge-amber'}`}>
                                            {t.whatsapp_conectado ? 'Conectado' : 'Desconectado'}
                                        </span>
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => impersonar(t)}
                                        className="min-h-11 rounded-lg px-3 py-2 text-xs font-medium"
                                        style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
                                    >
                                        Entrar
                                    </button>
                                    <Link
                                        href={route('superadmin.tenants.edit', t.id)}
                                        className="inline-flex min-h-11 items-center justify-center rounded-lg px-3 py-2 text-xs font-medium"
                                        style={{ background: 'rgba(255,255,255,0.05)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        type="button"
                                        onClick={() => toggleAtivo(t)}
                                        className="col-span-2 min-h-11 rounded-lg px-3 py-2 text-xs font-medium"
                                        style={{ background: 'rgba(255,255,255,0.05)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                    >
                                        {t.ativo ? 'Desativar tenant' : 'Ativar tenant'}
                                    </button>
                                </div>
                            </article>
                        ))}
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
