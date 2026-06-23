import { router } from '@inertiajs/react';
import { Agendamento, PaginatedData } from '@/types';

const STATUS_COLORS: Record<string, string> = {
    confirmado: 'bg-blue-100 text-blue-700',
    cancelado:  'bg-red-100 text-red-600',
    concluido:  'bg-green-100 text-green-700',
};

interface Props {
    agendamentos: PaginatedData<Agendamento>;
    filtros: { data?: string; status?: string };
}

export default function AgendamentosTable({ agendamentos, filtros }: Props) {
    const fmt = (iso: string) => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    const fmtVal = (v: number | null) => v != null ? `R$ ${v.toFixed(2)}` : '—';

    const cancelar = (ag: Agendamento) => {
        if (!confirm(`Cancelar agendamento de ${ag.cliente_nome}?`)) return;
        router.patch(route('agendamentos.cancelar', ag.id));
    };

    const filtrar = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        router.get(route('agendamentos.index'), Object.fromEntries(fd) as Record<string, string>, { preserveState: true });
    };

    return (
        <div className="space-y-4">
            <form onSubmit={filtrar} className="flex flex-wrap gap-3">
                <input type="date" name="data" defaultValue={filtros.data ?? ''}
                    className="rounded-md border-gray-300 text-sm shadow-sm" />
                <select name="status" defaultValue={filtros.status ?? ''}
                    className="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">Todos os status</option>
                    <option value="confirmado">Confirmado</option>
                    <option value="cancelado">Cancelado</option>
                    <option value="concluido">Concluído</option>
                </select>
                <button type="submit" className="rounded-md bg-gray-700 px-3 py-1.5 text-sm font-medium text-primary hover:bg-gray-800">
                    Filtrar
                </button>
            </form>

            <div className="overflow-x-auto rounded-lg border">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            {['Cliente', 'Recurso', 'Início', 'Fim', 'Status', 'Valor', 'Ação'].map(h => (
                                <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">{h}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 bg-white">
                        {agendamentos.data.length === 0 && (
                            <tr><td colSpan={7} className="px-4 py-6 text-center text-gray-400">Nenhum agendamento encontrado.</td></tr>
                        )}
                        {agendamentos.data.map(ag => (
                            <tr key={ag.id}>
                                <td className="px-4 py-3 font-medium text-gray-900">
                                    <div>{ag.cliente_nome}</div>
                                    <div className="text-xs text-gray-400">{ag.cliente_telefone}</div>
                                </td>
                                <td className="px-4 py-3 text-gray-600">{ag.recurso.nome}</td>
                                <td className="px-4 py-3 text-gray-600 whitespace-nowrap">{fmt(ag.inicio)}</td>
                                <td className="px-4 py-3 text-gray-600 whitespace-nowrap">{fmt(ag.fim)}</td>
                                <td className="px-4 py-3">
                                    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${STATUS_COLORS[ag.status]}`}>
                                        {ag.status}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-gray-600">{fmtVal(ag.valor_total)}</td>
                                <td className="px-4 py-3">
                                    {ag.status === 'confirmado' && (
                                        <button onClick={() => cancelar(ag)}
                                            className="text-xs text-red-600 hover:underline">
                                            Cancelar
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {agendamentos.last_page > 1 && (
                <div className="flex gap-1">
                    {agendamentos.links.map((link, i) => (
                        <button key={i} disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, filtros, { preserveState: true })}
                            className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-primary' : 'border text-gray-600 hover:bg-gray-50 disabled:opacity-40'}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
