import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Agendamento, Recurso, PaginatedData } from '@/types';

interface Props extends PageProps {
    agendamentos: PaginatedData<Agendamento>;
    recursos: Recurso[];
    filtros: { data?: string; recurso_id?: string; status?: string; busca?: string };
}

const STATUS_BADGE: Record<string, string> = {
    confirmado: 'badge-green',
    cancelado:  'badge-red',
    concluido:  'badge-gray',
};
const STATUS_LABEL: Record<string, string> = {
    confirmado: 'Confirmado',
    cancelado:  'Cancelado',
    concluido:  'Concluído',
};

function fmtDt(iso: string) {
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}
function duracaoMin(a: string, b: string) {
    return Math.round((new Date(b).getTime() - new Date(a).getTime()) / 60000);
}

// ─── Modal de nova reserva ────────────────────────────────────────────────────

function NovaReservaModal({ recursos, onClose }: { recursos: Recurso[]; onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        recurso_id:        recursos[0]?.id ?? '' as number | string,
        cliente_nome:      '',
        cliente_telefone:  '',
        inicio:            '',
        fim:               '',
        observacoes:       '',
        notificar_cliente: false as boolean,
    });

    const [slots, setSlots] = useState<string[]>([]);

    const buscarSlots = async (recursoId: number | string, data: string) => {
        if (!recursoId || !data) return;
        try {
            const res = await fetch(
                route('tenant.agenda.disponibilidade') + `?recurso_id=${recursoId}&data_inicio=${data}&data_fim=${data}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
            );
            const ags: { start: string }[] = await res.json();
            const ocupadas = new Set(ags.map(a => new Date(a.start).getHours()));
            setSlots(
                Array.from({ length: 15 }, (_, i) => i + 7)
                    .filter(h => !ocupadas.has(h))
                    .map(h => `${String(h).padStart(2, '0')}:00`),
            );
        } catch { /* ignore */ }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.agendamentos.store'), { onSuccess: onClose });
    };

    const datePart = (iso: string) => iso?.split('T')[0] ?? '';
    const timePart = (iso: string) => iso?.split('T')[1]?.slice(0, 5) ?? '';

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm sm:items-center px-4">
            <div className="w-full max-w-md rounded-2xl p-7 shadow-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                <div className="mb-5 flex items-start justify-between">
                    <h3 style={{ fontFamily: 'Instrument Serif, Georgia, serif' }} className="text-xl font-semibold text-primary">
                        Nova reserva manual
                    </h3>
                    <button onClick={onClose} style={{ color: 'var(--text-3)' }} className="hover:text-primary transition-colors">✕</button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="label mb-1">Recurso</label>
                        <select
                            value={data.recurso_id}
                            onChange={e => {
                                setData('recurso_id', Number(e.target.value));
                                buscarSlots(e.target.value, datePart(data.inicio));
                            }}
                            className="input"
                            required
                        >
                            {recursos.map(r => <option key={r.id} value={r.id}>{r.nome}</option>)}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="label mb-1">Nome do cliente</label>
                            <input
                                value={data.cliente_nome}
                                onChange={e => setData('cliente_nome', e.target.value)}
                                className="input"
                                placeholder="João Silva"
                                required
                            />
                            {errors.cliente_nome && <p className="mt-1 text-xs text-red-400">{errors.cliente_nome}</p>}
                        </div>
                        <div>
                            <label className="label mb-1">Telefone</label>
                            <input
                                value={data.cliente_telefone}
                                onChange={e => setData('cliente_telefone', e.target.value)}
                                className="input"
                                placeholder="55119…"
                                required
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div className="col-span-1">
                            <label className="label mb-1">Data</label>
                            <input
                                type="date"
                                value={datePart(data.inicio)}
                                onChange={e => {
                                    const hora = timePart(data.inicio) || '09:00';
                                    setData('inicio', `${e.target.value}T${hora}`);
                                    buscarSlots(data.recurso_id, e.target.value);
                                }}
                                className="input"
                                required
                            />
                        </div>
                        <div>
                            <label className="label mb-1">Início</label>
                            {slots.length > 0 ? (
                                <select
                                    value={timePart(data.inicio)}
                                    onChange={e => setData('inicio', `${datePart(data.inicio)}T${e.target.value}`)}
                                    className="input"
                                >
                                    <option value="">Selecione</option>
                                    {slots.map(s => <option key={s} value={s}>{s}</option>)}
                                </select>
                            ) : (
                                <input
                                    type="time"
                                    value={timePart(data.inicio)}
                                    onChange={e => setData('inicio', `${datePart(data.inicio)}T${e.target.value}`)}
                                    className="input"
                                />
                            )}
                        </div>
                        <div>
                            <label className="label mb-1">Fim</label>
                            <input
                                type="time"
                                value={timePart(data.fim)}
                                onChange={e => setData('fim', `${datePart(data.inicio)}T${e.target.value}`)}
                                className="input"
                                required
                            />
                        </div>
                    </div>

                    <div>
                        <label className="label mb-1">Observações</label>
                        <textarea
                            value={data.observacoes}
                            onChange={e => setData('observacoes', e.target.value)}
                            rows={2}
                            className="input"
                        />
                    </div>

                    <label className="flex items-center gap-2 text-sm" style={{ color: 'var(--text-2)' }}>
                        <input
                            type="checkbox"
                            checked={data.notificar_cliente}
                            onChange={e => setData('notificar_cliente', e.target.checked)}
                            className="rounded"
                        />
                        Notificar cliente via WhatsApp
                    </label>

                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                        <button type="submit" disabled={processing} className="btn-primary">
                            {processing ? 'Salvando…' : 'Confirmar reserva'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function AgendamentosIndex({ agendamentos, recursos, filtros }: Props) {
    const [modalAberto, setModalAberto] = useState(false);

    const filtrar = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const fd = new FormData(e.currentTarget);
        const params: Record<string, string> = {};
        fd.forEach((v, k) => { if (v) params[k] = String(v); });
        router.get(route('tenant.agendamentos.index'), params, { preserveState: true });
    };

    const cancelar = (id: number) => {
        if (confirm('Cancelar este agendamento?')) {
            router.patch(route('tenant.agendamentos.cancelar', id));
        }
    };

    const concluir = (id: number) => {
        if (confirm('Marcar como concluído?')) {
            router.patch(route('tenant.agendamentos.concluir', id));
        }
    };

    return (
        <AppLayout title="Agendamentos">
            <Head title="Agendamentos" />

            {/* Filtros */}
            <form onSubmit={filtrar} className="card mb-5 flex flex-wrap items-end gap-3 p-4">
                <div>
                    <label className="label mb-1">Data</label>
                    <input type="date" name="data" defaultValue={filtros.data ?? ''} className="input" />
                </div>
                <div>
                    <label className="label mb-1">Recurso</label>
                    <select name="recurso_id" defaultValue={filtros.recurso_id ?? ''} className="input">
                        <option value="">Todos</option>
                        {recursos.map(r => <option key={r.id} value={r.id}>{r.nome}</option>)}
                    </select>
                </div>
                <div>
                    <label className="label mb-1">Status</label>
                    <select name="status" defaultValue={filtros.status ?? ''} className="input">
                        <option value="">Todos</option>
                        <option value="confirmado">Confirmado</option>
                        <option value="concluido">Concluído</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div className="flex-1 min-w-[160px]">
                    <label className="label mb-1">Busca</label>
                    <input type="text" name="busca" defaultValue={filtros.busca ?? ''} placeholder="Nome ou telefone" className="input" />
                </div>
                <div className="flex gap-2">
                    <button type="submit" className="btn-primary">Filtrar</button>
                    <button type="button" onClick={() => router.get(route('tenant.agendamentos.index'))} className="btn-secondary">Limpar</button>
                </div>
            </form>

            {/* Tabela */}
            <div className="card overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
                                {['Cliente', 'Recurso', 'Início', 'Duração', 'Valor', 'Origem', 'Status', ''].map(h => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {agendamentos.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-10 text-center" style={{ color: 'var(--text-3)' }}>
                                        Nenhum agendamento encontrado.
                                    </td>
                                </tr>
                            ) : agendamentos.data.map(a => (
                                <tr
                                    key={a.id}
                                    style={{ borderBottom: '1px solid var(--border)' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = 'var(--bg-surface-2)')}
                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                >
                                    <td className="px-4 py-3">
                                        <p className="font-medium text-primary">{a.cliente_nome}</p>
                                        <p className="text-xs" style={{ color: 'var(--text-3)' }}>{a.cliente_telefone}</p>
                                    </td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>{a.recurso?.nome}</td>
                                    <td className="px-4 py-3 whitespace-nowrap" style={{ color: 'var(--text-2)' }}>{fmtDt(a.inicio)}</td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-3)' }}>{duracaoMin(a.inicio, a.fim)} min</td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>
                                        {a.valor_total
                                            ? Number(a.valor_total).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`badge ${a.origem === 'whatsapp' ? 'badge-green' : 'badge-gray'}`}>
                                            {a.origem === 'whatsapp' ? '🤖 WhatsApp' : '📋 Manual'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`badge ${STATUS_BADGE[a.status] ?? 'badge-gray'}`}>
                                            {STATUS_LABEL[a.status] ?? a.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {a.status === 'confirmado' && (
                                            <div className="flex gap-1">
                                                <button
                                                    onClick={() => concluir(a.id)}
                                                    className="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                                                    style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-2)', border: '1px solid var(--border)' }}
                                                    onMouseEnter={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.1)')}
                                                    onMouseLeave={e => (e.currentTarget.style.background = 'rgba(255,255,255,0.06)')}
                                                >
                                                    Concluir
                                                </button>
                                                <button
                                                    onClick={() => cancelar(a.id)}
                                                    className="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                                                    style={{ background: 'rgba(239,68,68,0.08)', color: '#f87171', border: '1px solid rgba(239,68,68,0.2)' }}
                                                >
                                                    Cancelar
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Paginação */}
                {agendamentos.last_page > 1 && (
                    <div className="flex gap-1 px-4 py-3" style={{ borderTop: '1px solid var(--border)' }}>
                        {agendamentos.links.map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
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

            {/* FAB */}
            <button
                onClick={() => setModalAberto(true)}
                className="fixed bottom-7 right-7 flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold text-primary shadow-lg transition-transform hover:scale-105"
                style={{ background: 'var(--accent)' }}
            >
                + Nova reserva
            </button>

            {modalAberto && <NovaReservaModal recursos={recursos} onClose={() => setModalAberto(false)} />}
        </AppLayout>
    );
}
