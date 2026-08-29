import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { PageProps, Tenant, PaginatedData } from '@/types';

interface TenantWithCounts extends Tenant {
    recursos_count: number;
    isento_cobranca: boolean;
    users: { id: number; name: string; email: string }[];
}

interface Props extends PageProps {
    tenants: PaginatedData<TenantWithCounts>;
}

const TIPO_LABEL: Record<string, string> = {
    barbeiro: 'Barbearia', quadra: 'Quadra', estetica: 'Estética', personalizado: 'Personalizado',
};

export default function TenantsIndex({ tenants }: Props) {
    const { confirm, modal: confirmModal } = useConfirm();

    const impersonar = async (t: TenantWithCounts) => {
        if (await confirm({
            title: 'Entrar no tenant',
            message: `Você passará a visualizar o sistema como "${t.nome}".`,
            confirmLabel: 'Entrar no tenant',
        })) router.post(route('superadmin.tenants.impersonar', t.id));
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
    const toggleIsento = async (t: TenantWithCounts) => {
        const acao = t.isento_cobranca ? 'Remover isenção de' : 'Marcar como isento de cobrança:';
        if (await confirm({
            title: t.isento_cobranca ? 'Remover isenção' : 'Isentar cobrança',
            message: `${acao} "${t.nome}"?`,
            confirmLabel: t.isento_cobranca ? 'Remover isenção' : 'Isentar',
            variant: 'warning',
        })) router.patch(route('superadmin.tenants.toggle-isento', t.id), {}, { preserveScroll: true });
    };
    const excluir = async (t: TenantWithCounts) => {
        if (await confirm({
            title: 'Excluir tenant',
            message: `Excluir "${t.nome}"? Todos os dados serão removidos e esta ação não pode ser desfeita.`,
            confirmLabel: 'Excluir tenant',
            variant: 'danger',
        })) {
            router.delete(route('superadmin.tenants.destroy', t.id));
        }
    };

    const actions = (t: TenantWithCounts, mobile = false) => (
        <div className={mobile ? 'grid grid-cols-2 gap-2' : 'flex flex-wrap gap-1.5'}>
            <button
                type="button"
                onClick={() => impersonar(t)}
                className={`${mobile ? 'min-h-11' : ''} rounded-lg px-3 py-2 text-xs font-medium transition-colors hover:brightness-125`}
                style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
            >
                Entrar
            </button>
            <Link
                href={route('superadmin.tenants.edit', t.id)}
                className={`${mobile ? 'min-h-11' : ''} inline-flex items-center justify-center rounded-lg px-3 py-2 text-xs font-medium transition-colors hover:brightness-125`}
                style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
            >
                Editar
            </Link>
            <button
                type="button"
                onClick={() => toggleAtivo(t)}
                className={`${mobile ? 'min-h-11' : ''} rounded-lg px-3 py-2 text-xs font-medium transition-colors hover:brightness-125`}
                style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
            >
                {t.ativo ? 'Desativar' : 'Ativar'}
            </button>
            <button
                type="button"
                onClick={() => toggleIsento(t)}
                className={`${mobile ? 'min-h-11' : ''} rounded-lg px-3 py-2 text-xs font-medium transition-colors hover:brightness-125`}
                style={t.isento_cobranca
                    ? { background: 'rgba(234,179,8,0.12)', color: '#ca8a04', border: '1px solid rgba(234,179,8,0.3)' }
                    : { background: 'rgba(255,255,255,0.06)', color: 'var(--text-2)', border: '1px solid var(--border)' }
                }
            >
                {t.isento_cobranca ? 'Remover isenção' : 'Isentar'}
            </button>
            <button
                type="button"
                onClick={() => excluir(t)}
                className={`${mobile ? 'col-span-2 min-h-11' : ''} rounded-lg px-3 py-2 text-xs font-medium transition-colors`}
                style={{ background: 'rgba(239,68,68,0.08)', color: '#f87171', border: '1px solid rgba(239,68,68,0.2)' }}
            >
                Excluir tenant
            </button>
        </div>
    );

    return (
        <AppLayout title="Gestão de Tenants">
            <Head title="Tenants" />
            {confirmModal}

            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                    {tenants.total} tenant{tenants.total !== 1 ? 's' : ''} no total
                </p>
                <Link href={route('superadmin.tenants.create')} className="btn-primary w-full justify-center sm:w-auto">
                    + Novo tenant
                </Link>
            </div>

            <div className="card overflow-hidden">
                <div className="hidden overflow-x-auto lg:block">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
                                {['Tenant', 'Dono', 'Tipo', 'WhatsApp', 'Recursos', 'Status', 'Cobrança', 'Ações'].map(h => (
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
                                        className="table-row-hover"
                                        style={{ borderBottom: '1px solid var(--border)' }}
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
                                        <td className="px-4 py-3">
                                            <span className={`badge ${t.ativo ? 'badge-green' : 'badge-red'}`}>
                                                {t.ativo ? 'Ativo' : 'Inativo'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.isento_cobranca
                                                ? <span className="badge badge-yellow">Isento</span>
                                                : <span className="text-xs" style={{ color: 'var(--text-3)' }}>Normal</span>
                                            }
                                        </td>
                                        <td className="px-4 py-3">
                                            {actions(t)}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>

                <div className="divide-y lg:hidden" style={{ borderColor: 'var(--border)' }}>
                    {tenants.data.length === 0 && (
                        <div className="px-4 py-10 text-center text-sm" style={{ color: 'var(--text-3)' }}>
                            Nenhum tenant cadastrado.
                        </div>
                    )}
                    {tenants.data.map(t => {
                        const dono = t.users?.[0];
                        return (
                            <article key={t.id} className="space-y-4 p-4 sm:p-5">
                                <div className="flex min-w-0 items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h2 className="truncate font-semibold text-primary">{t.nome}</h2>
                                        <p className="break-all text-xs" style={{ color: 'var(--text-3)' }}>{t.slug}</p>
                                    </div>
                                    <span className={`badge shrink-0 ${t.ativo ? 'badge-green' : 'badge-red'}`}>
                                        {t.ativo ? 'Ativo' : 'Inativo'}
                                    </span>
                                </div>

                                <dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                                    <div className="col-span-2 min-w-0">
                                        <dt className="text-[10px] font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>Responsável</dt>
                                        <dd className="mt-1 min-w-0" style={{ color: 'var(--text-1)' }}>
                                            {dono ? (
                                                <>
                                                    <p className="truncate">{dono.name}</p>
                                                    <p className="break-all text-xs" style={{ color: 'var(--text-3)' }}>{dono.email}</p>
                                                </>
                                            ) : '—'}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-[10px] font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>Tipo</dt>
                                        <dd className="mt-1" style={{ color: 'var(--text-2)' }}>{TIPO_LABEL[t.tipo_servico] ?? t.tipo_servico}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-[10px] font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>Recursos</dt>
                                        <dd className="mt-1" style={{ color: 'var(--text-2)' }}>{t.recursos_count}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-[10px] font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>WhatsApp</dt>
                                        <dd className="mt-1">
                                            <span className={`badge ${t.whatsapp_conectado ? 'badge-green' : 'badge-gray'}`}>
                                                {t.whatsapp_conectado ? 'Conectado' : 'Desconectado'}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-[10px] font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>Cobrança</dt>
                                        <dd className="mt-1">
                                            {t.isento_cobranca
                                                ? <span className="badge badge-yellow">Isento</span>
                                                : <span className="text-xs" style={{ color: 'var(--text-3)' }}>Normal</span>}
                                        </dd>
                                    </div>
                                </dl>

                                {actions(t, true)}
                            </article>
                        );
                    })}
                </div>
                {tenants.last_page > 1 && (
                    <div className="flex max-w-full gap-1 overflow-x-auto px-4 py-3" style={{ borderTop: '1px solid var(--border)' }}>
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
