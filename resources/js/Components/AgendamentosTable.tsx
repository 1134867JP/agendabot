import { router } from '@inertiajs/react';
import { Agendamento, PaginatedData } from '@/types';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import StatusBadge from '@/Components/UI/StatusBadge';
import Toolbar from '@/Components/UI/Toolbar';

const statusConfig = {
    confirmado: { label: 'Confirmado', tone: 'info' as const },
    cancelado: { label: 'Cancelado', tone: 'danger' as const },
    concluido: { label: 'Concluído', tone: 'success' as const },
};

interface Props {
    agendamentos: PaginatedData<Agendamento>;
    filtros: { data?: string; status?: string };
}

export default function AgendamentosTable({ agendamentos, filtros }: Props) {
    const fmt = (iso: string) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    const fmtVal = (value: number | null) => value != null
        ? value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
        : '—';

    const cancelar = (agendamento: Agendamento) => {
        if (!confirm(`Cancelar agendamento de ${agendamento.cliente_nome}?`)) return;
        router.patch(route('agendamentos.cancelar', agendamento.id));
    };

    const filtrar = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const formData = new FormData(event.currentTarget);
        router.get(route('agendamentos.index'), Object.fromEntries(formData) as Record<string, string>, { preserveState: true });
    };

    const badge = (status: string) => {
        const config = statusConfig[status as keyof typeof statusConfig] ?? { label: status, tone: 'neutral' as const };
        return <StatusBadge tone={config.tone} dot>{config.label}</StatusBadge>;
    };

    return (
        <div className="space-y-4">
            <Toolbar>
                <form onSubmit={filtrar} className="contents">
                    <FormField label="Data" htmlFor="filtro-data" className="min-w-0 flex-1 sm:max-w-[190px]">
                        <input id="filtro-data" type="date" name="data" defaultValue={filtros.data ?? ''} className="input" />
                    </FormField>
                    <FormField label="Status" htmlFor="filtro-status" className="min-w-0 flex-1 sm:max-w-[210px]">
                        <select id="filtro-status" name="status" defaultValue={filtros.status ?? ''} className="input">
                            <option value="">Todos os status</option>
                            <option value="confirmado">Confirmado</option>
                            <option value="cancelado">Cancelado</option>
                            <option value="concluido">Concluído</option>
                        </select>
                    </FormField>
                    <button type="submit" className="btn-primary w-full sm:w-auto">Aplicar filtros</button>
                </form>
            </Toolbar>

            {agendamentos.data.length === 0 ? (
                <Card padding="none">
                    <EmptyState
                        title="Nenhum agendamento encontrado"
                        description="Altere os filtros ou crie um novo agendamento para começar."
                    />
                </Card>
            ) : (
                <>
                    <Card padding="none" className="hidden overflow-hidden md:block">
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead style={{ background: 'var(--bg-surface-2)', borderBottom: '1px solid var(--border)' }}>
                                    <tr>
                                        {['Cliente', 'Recurso', 'Início', 'Fim', 'Status', 'Valor', 'Ação'].map(header => (
                                            <th key={header} className="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-[0.06em] text-muted">
                                                {header}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {agendamentos.data.map(agendamento => (
                                        <tr key={agendamento.id} className="table-row-hover" style={{ borderBottom: '1px solid var(--border)' }}>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-primary">{agendamento.cliente_nome}</div>
                                                <div className="mt-0.5 text-xs text-muted">{agendamento.cliente_telefone}</div>
                                            </td>
                                            <td className="px-4 py-3 text-secondary">{agendamento.recurso?.nome ?? '—'}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-secondary">{fmt(agendamento.inicio)}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-secondary">{fmt(agendamento.fim)}</td>
                                            <td className="px-4 py-3">{badge(agendamento.status)}</td>
                                            <td className="whitespace-nowrap px-4 py-3 text-secondary">{fmtVal(agendamento.valor_total)}</td>
                                            <td className="px-4 py-3">
                                                {agendamento.status === 'confirmado' && (
                                                    <button onClick={() => cancelar(agendamento)} className="text-xs font-medium" style={{ color: 'var(--danger-text)' }}>
                                                        Cancelar
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    <div className="space-y-3 md:hidden">
                        {agendamentos.data.map(agendamento => (
                            <Card key={agendamento.id} padding="md">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h3 className="truncate text-sm font-semibold text-primary">{agendamento.cliente_nome}</h3>
                                        <p className="mt-0.5 truncate text-xs text-muted">{agendamento.cliente_telefone}</p>
                                    </div>
                                    {badge(agendamento.status)}
                                </div>
                                <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3">
                                    <div className="col-span-2">
                                        <dt className="label">Recurso</dt>
                                        <dd className="mt-1 text-sm text-secondary">{agendamento.recurso?.nome ?? '—'}</dd>
                                    </div>
                                    <div>
                                        <dt className="label">Início</dt>
                                        <dd className="mt-1 text-sm text-secondary">{fmt(agendamento.inicio)}</dd>
                                    </div>
                                    <div>
                                        <dt className="label">Fim</dt>
                                        <dd className="mt-1 text-sm text-secondary">{fmt(agendamento.fim)}</dd>
                                    </div>
                                    <div>
                                        <dt className="label">Valor</dt>
                                        <dd className="mt-1 text-sm text-secondary">{fmtVal(agendamento.valor_total)}</dd>
                                    </div>
                                </dl>
                                {agendamento.status === 'confirmado' && (
                                    <button onClick={() => cancelar(agendamento)} className="btn-danger mt-4 w-full">Cancelar agendamento</button>
                                )}
                            </Card>
                        ))}
                    </div>
                </>
            )}

            {agendamentos.last_page > 1 && (
                <nav className="flex flex-wrap justify-center gap-1.5 sm:justify-end" aria-label="Paginação">
                    {agendamentos.links.map((link, index) => (
                        <button
                            key={index}
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, filtros, { preserveState: true })}
                            className="min-h-10 min-w-10 rounded-lg px-3 text-sm transition-colors disabled:opacity-40"
                            style={{
                                background: link.active ? 'var(--accent)' : 'var(--bg-surface)',
                                border: `1px solid ${link.active ? 'var(--accent)' : 'var(--border)'}`,
                                color: link.active ? 'white' : 'var(--text-2)',
                            }}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </div>
    );
}
