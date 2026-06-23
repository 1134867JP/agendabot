import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Tenant, PaginatedData } from '@/types';

interface TenantWithCounts extends Tenant {
    agendamentos_count: number;
    recursos_count: number;
    users: { id: number; name: string; email: string }[];
}

interface Props extends PageProps {
    tenants: PaginatedData<TenantWithCounts>;
}

const TIPO_LABEL: Record<string, string> = {
    barbeiro: 'Barbearia', quadra: 'Quadra', estetica: 'Estética', personalizado: 'Personalizado',
};

export default function TenantsIndex({ tenants }: Props) {
    const impersonar = (t: TenantWithCounts) => {
        if (confirm(`Entrar como "${t.nome}"?`)) router.post(route('superadmin.tenants.impersonar', t.id));
    };
    const toggleAtivo = (t: TenantWithCounts) => {
        router.patch(route('superadmin.tenants.toggle-ativo', t.id));
    };
    const excluir = (t: TenantWithCounts) => {
        if (confirm(`Excluir "${t.nome}"? Esta ação não pode ser desfeita.`)) {
            router.delete(route('superadmin.tenants.destroy', t.id));
        }
    };

    return (
        <AppLayout title="Gestão de Tenants">
            <Head title="Tenants" />

            <div className="mb-5 flex items-center justify-between">
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                    {tenants.total} tenant{tenants.total !== 1 ? 's' : ''} no total
                </p>
                <a href={route('superadmin.tenants.create')} className="btn-primary">
                    + Novo tenant
                </a>
            </div>

            <div className="card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
                                {['Tenant', 'Dono', 'Tipo', 'WhatsApp', 'Recursos', 'Agendamentos', 'Status', 'Ações'].map(h => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {tenants.data.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-10 text-center" style={{ color: 'var(--text-3)' }}>Nenhum tenant cadastrado.</td></tr>
                            )}
                            {tenants.data.map(t => {
                                const dono = t.users?.[0];
                                return (
                                    <tr
                                        key={t.id}
                                        style={{ borderBottom: '1px solid var(--border)' }}
                                        onMouseEnter={e => (e.currentTarget.style.background = 'var(--bg-surface-2)')}
                                        onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                    >
                                        <td className="px-4 py-3">
                                            <p className="font-semibold text-primary">{t.nome}</p>
                                            <p className="text-xs" style={{ color: 'var(--text-3)' }}>{t.slug}</p>
                                        </td>
                                        <td className="px-4 py-3">
                                            {dono ? (
                                                <>
                                                    <p style={{ color: 'var(--text-1)' }}>{dono.name}</p>
                                                    <p className="text-xs" style={{ color: 'var(--text-3)' }}>{dono.email}</p>
                                                </>
                                            ) : <span style={{ color: 'var(--text-3)' }}>—</span>}
                                        </td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>{TIPO_LABEL[t.tipo_servico] ?? t.tipo_servico}</td>
                                        <td className="px-4 py-3">
                                            <span className={`badge ${t.whatsapp_conectado ? 'badge-green' : 'badge-gray'}`}>
                                                <span className={`h-1.5 w-1.5 rounded-full ${t.whatsapp_conectado ? 'bg-emerald-500' : 'bg-white/20'}`} />
                                                {t.whatsapp_conectado ? 'Sim' : 'Não'}
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
                                            <div className="flex flex-wrap gap-1.5">
                                                <button
                                                    onClick={() => impersonar(t)}
                                                    className="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                                                    style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
                                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(99,102,241,0.2)')}
                                                    onMouseLeave={e => (e.currentTarget.style.background = 'var(--accent-light)')}
                                                >
                                                    Entrar
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
                                                <button
                                                    onClick={() => toggleAtivo(t)}
                                                    className="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                                                    style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.1)')}
                                                    onMouseLeave={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.06)')}
                                                >
                                                    {t.ativo ? 'Desativar' : 'Ativar'}
                                                </button>
                                                <button
                                                    onClick={() => excluir(t)}
                                                    className="rounded-lg px-2.5 py-1 text-xs font-medium transition-colors"
                                                    style={{ background: 'rgba(239,68,68,0.08)', color: '#f87171', border: '1px solid rgba(239,68,68,0.2)' }}
                                                >
                                                    Excluir
                                                </button>
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
        </AppLayout>
    );
}
