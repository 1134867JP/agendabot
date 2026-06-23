import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Tenant, PaginatedData } from '@/types';

interface Stats {
    total_tenants: number;
    tenants_ativos: number;
    tenants_conectados: number;
    agendamentos_hoje: number;
    agendamentos_mes: number;
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

export default function SuperAdminDashboard({ stats, tenants }: Props) {
    const impersonar = (t: TenantWithCounts) => {
        if (confirm(`Entrar como "${t.nome}"?`)) {
            router.post(route('superadmin.tenants.impersonar', t.id));
        }
    };

    const toggleAtivo = (t: TenantWithCounts) => {
        router.patch(route('superadmin.tenants.toggle-ativo', t.id));
    };

    return (
        <AppLayout title="Visão Geral">
            <Head title="Super Admin" />

            <div className="space-y-7">
                {/* Stats */}
                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    {[
                        { label: 'Total de tenants',   value: stats.total_tenants,      accent: false },
                        { label: 'Tenants ativos',      value: stats.tenants_ativos,     accent: false },
                        { label: 'WhatsApp conectados', value: stats.tenants_conectados, accent: false },
                        { label: 'Agendamentos hoje',   value: stats.agendamentos_hoje,  accent: false },
                        { label: 'Agendamentos no mês', value: stats.agendamentos_mes,   accent: true  },
                    ].map(s => (
                        <div
                            key={s.label}
                            className="rounded-xl p-5"
                            style={{
                                background: s.accent ? 'var(--accent)' : 'var(--bg-surface)',
                                border: s.accent ? 'none' : '1px solid var(--border)',
                            }}
                        >
                            <p className="text-[10px] font-medium uppercase tracking-widest" style={{ color: s.accent ? 'rgba(255,255,255,0.6)' : 'var(--text-3)' }}>
                                {s.label}
                            </p>
                            <p
                                className="mt-2 text-3xl font-bold leading-none text-primary"
                                style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}
                            >
                                {s.value}
                            </p>
                        </div>
                    ))}
                </div>

                {/* Tenants table */}
                <div className="card overflow-hidden">
                    <div className="flex items-center justify-between px-6 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                        <h2 className="text-sm font-semibold text-primary">Tenants</h2>
                        <a href={route('superadmin.tenants.create')} className="btn-primary text-xs py-1.5">
                            + Novo tenant
                        </a>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
                                    {['Tenant', 'Tipo', 'WhatsApp', 'Recursos', 'Agendamentos', 'Status', 'Ações'].map(h => (
                                        <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {tenants.data.map(t => (
                                    <tr
                                        key={t.id}
                                        style={{ borderBottom: '1px solid var(--border)' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = 'var(--bg-surface-2)')}
                                        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                    >
                                        <td className="px-4 py-3">
                                            <p className="font-medium text-primary">{t.nome}</p>
                                            <p className="text-xs" style={{ color: 'var(--text-3)' }}>{t.slug}</p>
                                        </td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>{TIPO_LABEL[t.tipo_servico] ?? t.tipo_servico}</td>
                                        <td className="px-4 py-3">
                                            <span className={`badge ${t.whatsapp_conectado ? 'badge-green' : 'badge-gray'}`}>
                                                <span className={`h-1.5 w-1.5 rounded-full ${t.whatsapp_conectado ? 'bg-emerald-500' : 'bg-white/20'}`} />
                                                {t.whatsapp_conectado ? 'Conectado' : 'Desconectado'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>{t.recursos_count}</td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>{t.agendamentos_count}</td>
                                        <td className="px-4 py-3">
                                            <span className={`badge ${t.ativo ? 'badge-green' : 'badge-red'}`}>
                                                {t.ativo ? 'Ativo' : 'Inativo'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex gap-1.5">
                                                <button
                                                    onClick={() => impersonar(t)}
                                                    className="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                                                    style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
                                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(99,102,241,0.2)')}
                                                    onMouseLeave={e => (e.currentTarget.style.background = 'var(--accent-light)')}
                                                >
                                                    Entrar
                                                </button>
                                                <button
                                                    onClick={() => toggleAtivo(t)}
                                                    className="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                                                    style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.1)')}
                                                    onMouseLeave={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.06)')}
                                                >
                                                    {t.ativo ? 'Desativar' : 'Ativar'}
                                                </button>
                                                <a
                                                    href={route('superadmin.tenants.edit', t.id)}
                                                    className="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                                                    style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.1)')}
                                                    onMouseLeave={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.06)')}
                                                >
                                                    Editar
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
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
