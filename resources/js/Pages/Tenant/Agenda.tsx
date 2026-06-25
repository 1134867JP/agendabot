import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useCallback, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Recurso } from '@/types';

interface AgendamentoCalendario {
    id: number;
    title: string;
    start: string;
    end: string;
    telefone: string;
    status: 'confirmado' | 'cancelado' | 'concluido';
    valor_total: number | null;
    origem: 'whatsapp' | 'manual';
}

interface EntidadeAgenda {
    id: number;
    nome: string;
    tipo: 'recurso' | 'profissional';
}

interface Props extends PageProps {
    recursos: Recurso[];
    profissionais: { id: number; nome: string }[];
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function addDays(d: Date, n: number) {
    const r = new Date(d); r.setDate(r.getDate() + n); return r;
}
function startOfWeek(d: Date) {
    const r = new Date(d);
    const day = r.getDay();
    r.setDate(r.getDate() - day + (day === 0 ? -6 : 1));
    r.setHours(0, 0, 0, 0);
    return r;
}
function toISO(d: Date) { return d.toISOString().split('T')[0]; }
function fmtHora(iso: string) {
    return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}
function fmtDiaHeader(d: Date) {
    return d.toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit' });
}
function fmtDiaLongo(d: Date) {
    return d.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' });
}

const HORAS = Array.from({ length: 15 }, (_, i) => i + 7); // 07..21

// ─── Slot color ───────────────────────────────────────────────────────────────

function slotColor(a: AgendamentoCalendario): { bg: string; border: string; text: string } {
    if (a.status === 'cancelado')  return { bg: 'rgba(239,68,68,0.08)',   border: 'rgba(239,68,68,0.2)',    text: '#f87171' };
    if (a.status === 'concluido')  return { bg: 'var(--bg-surface-2)',    border: 'rgba(255,255,255,0.1)',  text: 'rgba(232,230,225,0.4)' };
    if (a.origem === 'manual')     return { bg: 'rgba(59,130,246,0.1)',   border: 'rgba(59,130,246,0.25)', text: '#93c5fd' };
    return                                { bg: 'rgba(110,231,183,0.08)', border: 'rgba(110,231,183,0.2)', text: '#6ee7b7' };
}

// ─── Detail Modal ─────────────────────────────────────────────────────────────

function DetalheModal({
    detalhe,
    onClose,
    onConcluir,
    onCancelar,
}: {
    detalhe: AgendamentoCalendario;
    onClose: () => void;
    onConcluir: (id: number) => void;
    onCancelar: (id: number) => void;
}) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-2xl p-6 shadow-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                <div className="mb-4 flex items-start justify-between">
                    <div>
                        <h3 style={{ fontFamily: 'Instrument Serif, Georgia, serif' }} className="text-xl font-semibold text-primary">
                            {detalhe.title}
                        </h3>
                        <p className="text-sm" style={{ color: 'var(--text-3)' }}>{detalhe.telefone}</p>
                    </div>
                    <button onClick={onClose} style={{ color: 'var(--text-3)' }} className="hover:text-primary transition-colors">✕</button>
                </div>
                <div className="space-y-2 text-sm">
                    <div className="flex justify-between">
                        <span style={{ color: 'var(--text-3)' }}>Horário</span>
                        <span className="font-medium text-primary">{fmtHora(detalhe.start)} – {fmtHora(detalhe.end)}</span>
                    </div>
                    <div className="flex justify-between">
                        <span style={{ color: 'var(--text-3)' }}>Origem</span>
                        <span style={{ color: 'var(--text-2)' }}>{detalhe.origem === 'whatsapp' ? '🤖 WhatsApp' : '📋 Manual'}</span>
                    </div>
                    {detalhe.valor_total != null && (
                        <div className="flex justify-between">
                            <span style={{ color: 'var(--text-3)' }}>Valor</span>
                            <span className="font-medium text-primary">
                                {Number(detalhe.valor_total).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                            </span>
                        </div>
                    )}
                    <div className="flex justify-between">
                        <span style={{ color: 'var(--text-3)' }}>Status</span>
                        <span className={`badge ${detalhe.status === 'confirmado' ? 'badge-green' : detalhe.status === 'cancelado' ? 'badge-red' : 'badge-gray'}`}>
                            {detalhe.status}
                        </span>
                    </div>
                </div>
                <div className="mt-5 flex gap-2">
                    <a href={`tel:${detalhe.telefone}`} className="btn-secondary flex-1 justify-center text-xs py-2">
                        📞 Ligar
                    </a>
                    {detalhe.status === 'confirmado' && (
                        <>
                            <button onClick={() => onConcluir(detalhe.id)} className="btn-secondary flex-1 justify-center text-xs py-2">
                                Concluir
                            </button>
                            <button onClick={() => onCancelar(detalhe.id)} className="btn-danger flex-1 justify-center text-xs py-2">
                                Cancelar
                            </button>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

// ─── Day List View (mobile) ───────────────────────────────────────────────────

function DayListView({
    dia,
    agendamentos,
    onDia,
    onNovo,
    onDetalhe,
    loading,
}: {
    dia: Date;
    agendamentos: AgendamentoCalendario[];
    onDia: (d: Date) => void;
    onNovo: (hora: string) => void;
    onDetalhe: (a: AgendamentoCalendario) => void;
    loading: boolean;
}) {
    const hoje = toISO(new Date());
    const isHoje = toISO(dia) === hoje;

    // Build hour slots
    return (
        <div className="card overflow-hidden">
            {/* Day header */}
            <div
                className="flex items-center justify-between px-4 py-3"
                style={{ borderBottom: '1px solid var(--border)', background: isHoje ? 'var(--accent-light)' : undefined }}
            >
                <button
                    onClick={() => onDia(addDays(dia, -1))}
                    className="rounded-lg px-2 py-1 text-lg transition-colors hover:bg-[var(--bg-surface-2)]"
                    style={{ color: 'var(--text-3)' }}
                >‹</button>
                <div className="text-center">
                    <p className="text-sm font-semibold" style={{ color: isHoje ? 'var(--accent)' : 'var(--text-1)' }}>
                        {fmtDiaLongo(dia)}
                    </p>
                    {isHoje && <p className="text-[10px] font-medium" style={{ color: 'var(--accent)' }}>Hoje</p>}
                </div>
                <button
                    onClick={() => onDia(addDays(dia, 1))}
                    className="rounded-lg px-2 py-1 text-lg transition-colors hover:bg-[var(--bg-surface-2)]"
                    style={{ color: 'var(--text-3)' }}
                >›</button>
            </div>

            {loading ? (
                <div className="flex items-center justify-center py-12">
                    <svg className="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" style={{ color: 'var(--text-3)' }}>
                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
            ) : (
                <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                    {HORAS.map(h => {
                        const ags = agendamentos.filter(a => {
                            const d = new Date(a.start);
                            return toISO(d) === toISO(dia) && d.getHours() === h;
                        });
                        return (
                            <div key={h} className="flex gap-3 px-4 py-2.5">
                                <span
                                    className="w-10 shrink-0 pt-0.5 text-right font-mono text-xs"
                                    style={{ color: 'var(--text-3)' }}
                                >
                                    {String(h).padStart(2, '0')}:00
                                </span>
                                <div className="flex-1">
                                    {ags.length === 0 ? (
                                        <button
                                            onClick={() => onNovo(`${String(h).padStart(2, '0')}:00`)}
                                            className="h-8 w-full rounded-md transition-colors hover:bg-[var(--accent-light)]"
                                        />
                                    ) : ags.map(a => {
                                        const { bg, border, text } = slotColor(a);
                                        return (
                                            <button
                                                key={a.id}
                                                onClick={() => onDetalhe(a)}
                                                className="mb-1 w-full rounded-lg border px-3 py-2 text-left transition-opacity hover:opacity-80"
                                                style={{ background: bg, borderColor: border }}
                                            >
                                                <p className="truncate text-sm font-medium" style={{ color: text }}>{a.title}</p>
                                                <p className="text-xs opacity-70" style={{ color: text }}>
                                                    {fmtHora(a.start)} – {fmtHora(a.end)}
                                                </p>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

// ─── Week Grid (desktop) ──────────────────────────────────────────────────────

function WeekGrid({
    dias,
    agendamentos,
    loading,
    onSlotClick,
    onDetalhe,
}: {
    dias: Date[];
    agendamentos: AgendamentoCalendario[];
    loading: boolean;
    onSlotClick: (data: string, hora: string) => void;
    onDetalhe: (a: AgendamentoCalendario) => void;
}) {
    const hoje = toISO(new Date());

    const agsNaHora = (diaIdx: number, hora: number) =>
        agendamentos.filter(a => {
            const d = new Date(a.start);
            return toISO(d) === toISO(dias[diaIdx]) && d.getHours() === hora;
        });

    return (
        <div className="card overflow-x-auto">
            <table className="min-w-full text-xs" style={{ tableLayout: 'fixed' }}>
                <colgroup>
                    <col style={{ width: '52px' }} />
                    {dias.map((_, i) => <col key={i} />)}
                </colgroup>
                <thead>
                    <tr style={{ borderBottom: '1px solid var(--border)' }}>
                        <th style={{ background: 'var(--bg-surface-2)' }} className="px-2 py-2.5" />
                        {dias.map((d, i) => {
                            const isHoje = toISO(d) === hoje;
                            return (
                                <th
                                    key={i}
                                    className="px-2 py-2.5 text-center font-semibold"
                                    style={{
                                        background: isHoje ? 'var(--accent-light)' : 'var(--bg-surface-2)',
                                        color: isHoje ? 'var(--accent)' : 'var(--text-3)',
                                    }}
                                >
                                    <span className="block text-xs uppercase">{fmtDiaHeader(d)}</span>
                                    {isHoje && (
                                        <span className="mt-0.5 inline-block h-1.5 w-1.5 rounded-full" style={{ background: 'var(--accent)' }} />
                                    )}
                                </th>
                            );
                        })}
                    </tr>
                </thead>
                <tbody>
                    {loading ? (
                        <tr>
                            <td colSpan={8} className="py-16 text-center">
                                <svg className="mx-auto h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" style={{ color: 'var(--text-3)' }}>
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                            </td>
                        </tr>
                    ) : HORAS.map(h => (
                        <tr key={h} style={{ borderBottom: '1px solid var(--border)', height: '52px' }}>
                            <td className="px-2 py-1 text-center text-xs align-top pt-2" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)' }}>
                                {String(h).padStart(2, '0')}:00
                            </td>
                            {dias.map((_, dIdx) => {
                                const ags = agsNaHora(dIdx, h);
                                const isHoje = toISO(dias[dIdx]) === hoje;
                                return (
                                    <td
                                        key={dIdx}
                                        onClick={() => ags.length === 0 && onSlotClick(toISO(dias[dIdx]), `${String(h).padStart(2, '0')}:00`)}
                                        className="px-1 py-1 align-top cursor-pointer"
                                        style={{
                                            borderLeft: '1px solid var(--border)',
                                            background: isHoje ? 'rgba(99,102,241,0.03)' : undefined,
                                        }}
                                    >
                                        {ags.length === 0 ? (
                                            <div className="h-full w-full rounded transition-colors hover:bg-[var(--accent-light)]" />
                                        ) : ags.map(a => {
                                            const { bg, border, text } = slotColor(a);
                                            return (
                                                <div
                                                    key={a.id}
                                                    onClick={e => { e.stopPropagation(); onDetalhe(a); }}
                                                    className="mb-0.5 cursor-pointer rounded-md border px-1.5 py-0.5 leading-tight transition-opacity hover:opacity-80"
                                                    style={{ background: bg, borderColor: border, color: text }}
                                                >
                                                    <p className="truncate font-medium text-xs">{a.title}</p>
                                                    <p className="text-xs opacity-70">{fmtHora(a.start)}–{fmtHora(a.end)}</p>
                                                </div>
                                            );
                                        })}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────

export default function Agenda({ recursos, profissionais }: Props) {
    // Estável entre renders — só muda se recursos/profissionais mudarem (props do servidor)
    const entidades = useMemo<EntidadeAgenda[]>(() =>
        recursos.length > 0
            ? recursos.map(r => ({ id: r.id, nome: r.nome, tipo: 'recurso' as const }))
            : profissionais.map(p => ({ id: p.id, nome: p.nome, tipo: 'profissional' as const })),
    [recursos, profissionais]);

    const [semana, setSemana]           = useState(() => startOfWeek(new Date()));
    const [diaAtivo, setDiaAtivo]       = useState(() => new Date());
    const [entidadeId, setEntidadeId]   = useState<number>(() => entidades[0]?.id ?? 0);
    const [agendamentos, setAgs]        = useState<AgendamentoCalendario[]>([]);
    const [loading, setLoading]         = useState(false);
    const [detalhe, setDetalhe]         = useState<AgendamentoCalendario | null>(null);
    const [modalNova, setModalNova]     = useState<{ data: string; hora: string } | null>(null);
    const [novaForm, setNovaForm]       = useState({ nome: '', tel: '', fim: '', obs: '' });
    const [salvando, setSalvando]       = useState(false);
    const [erroReserva, setErroReserva] = useState<string | null>(null);

    // Tipo da entidade selecionada — derivado de entidadeId sem criar novo objeto
    const tipoEntidade = useMemo(
        () => entidades.find(e => e.id === entidadeId)?.tipo ?? 'profissional',
        [entidades, entidadeId],
    );

    const dias = Array.from({ length: 7 }, (_, i) => addDays(semana, i));

    const carregar = useCallback(() => {
        if (!entidadeId) return;
        setLoading(true);
        const inicio = startOfWeek(diaAtivo);
        const fim    = addDays(addDays(inicio, 6), 1); // inclui até o fim do último dia
        const param  = tipoEntidade === 'recurso'
            ? `recurso_id=${entidadeId}`
            : `profissional_id=${entidadeId}`;
        fetch(
            route('tenant.agenda.disponibilidade') +
            `?${param}&data_inicio=${toISO(inicio)}&data_fim=${toISO(fim)}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        )
            .then(r => r.json())
            .then(setAgs)
            .finally(() => setLoading(false));
    }, [entidadeId, tipoEntidade, semana, diaAtivo]);

    useEffect(() => { carregar(); }, [carregar]);

    // Keep semana in sync when diaAtivo changes (mobile navigation)
    const navegarDia = (d: Date) => {
        setDiaAtivo(d);
        setSemana(startOfWeek(d));
    };

    const cancelar = (id: number) => {
        router.patch(route('tenant.agendamentos.cancelar', id), {}, {
            onSuccess: () => { setDetalhe(null); carregar(); },
        });
    };
    const concluir = (id: number) => {
        router.patch(route('tenant.agendamentos.concluir', id), {}, {
            onSuccess: () => { setDetalhe(null); carregar(); },
        });
    };

    const criarReserva = () => {
        if (!modalNova || !entidadeId) return;
        setSalvando(true);
        setErroReserva(null);
        const base = {
            cliente_nome:      novaForm.nome,
            cliente_telefone:  novaForm.tel,
            inicio:            `${modalNova.data}T${modalNova.hora}:00`,
            fim:               novaForm.fim
                ? `${modalNova.data}T${novaForm.fim}:00`
                : `${modalNova.data}T${modalNova.hora}:30`,
            observacoes:       novaForm.obs,
            notificar_cliente: false as boolean,
        };
        const payload = tipoEntidade === 'recurso'
            ? { ...base, recurso_id: entidadeId }
            : { ...base, profissional_id: entidadeId };
        router.post(route('tenant.agendamentos.store'), payload, {
            onSuccess: () => {
                setModalNova(null);
                setNovaForm({ nome: '', tel: '', fim: '', obs: '' });
                carregar();
            },
            onError: (errors) => {
                const msg = Object.values(errors)[0] as string;
                setErroReserva(msg ?? 'Erro ao criar agendamento.');
            },
            onFinish: () => setSalvando(false),
        });
    };

    return (
        <AppLayout title="Agenda" subtitle="Visualize e gerencie os horários da semana">
            <Head title="Agenda" />

            {/* Empty state */}
            {entidades.length === 0 && (
                <div className="mb-6 rounded-xl p-6 text-center" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
                    <p className="font-medium text-primary mb-1">Nenhum profissional ou recurso cadastrado</p>
                    <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                        Cadastre um profissional em <strong>Profissionais</strong> para usar a agenda.
                    </p>
                </div>
            )}

            {/* Controls */}
            <div className="mb-4 flex flex-wrap items-center gap-3">
                {entidades.length > 1 && (
                    <select
                        value={entidadeId}
                        onChange={e => setEntidadeId(Number(e.target.value))}
                        className="input w-auto"
                    >
                        {entidades.map(e => (
                            <option key={e.id} value={e.id}>{e.nome}</option>
                        ))}
                    </select>
                )}

                {/* Week navigator — hidden on mobile (day view has its own nav) */}
                <div className="hidden md:flex items-center rounded-lg overflow-hidden" style={{ border: '1px solid var(--border-strong)' }}>
                    <button
                        onClick={() => { setSemana(addDays(semana, -7)); setDiaAtivo(addDays(semana, -7)); }}
                        className="px-3 py-2 text-sm transition-colors hover:bg-[var(--bg-surface-2)]"
                        style={{ color: 'var(--text-3)' }}
                    >←</button>
                    <button
                        onClick={() => { setSemana(startOfWeek(new Date())); setDiaAtivo(new Date()); }}
                        className="px-3 py-2 text-sm font-medium transition-colors hover:bg-[var(--bg-surface-2)]"
                        style={{ color: 'var(--text-2)', borderLeft: '1px solid var(--border)', borderRight: '1px solid var(--border)' }}
                    >Hoje</button>
                    <button
                        onClick={() => { setSemana(addDays(semana, 7)); setDiaAtivo(addDays(semana, 7)); }}
                        className="px-3 py-2 text-sm transition-colors hover:bg-[var(--bg-surface-2)]"
                        style={{ color: 'var(--text-3)' }}
                    >→</button>
                </div>

                {/* Mobile: Hoje button */}
                <button
                    onClick={() => { setSemana(startOfWeek(new Date())); setDiaAtivo(new Date()); }}
                    className="flex md:hidden items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition-colors"
                    style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)', color: 'var(--text-2)' }}
                >
                    Hoje
                </button>

                <span className="hidden md:inline text-sm font-medium" style={{ color: 'var(--text-2)' }}>
                    {dias[0].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' })} –{' '}
                    {dias[6].toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' })}
                </span>
            </div>

            {/* Mobile: day list view */}
            <div className="md:hidden">
                <DayListView
                    dia={diaAtivo}
                    agendamentos={agendamentos}
                    onDia={navegarDia}
                    onNovo={hora => setModalNova({ data: toISO(diaAtivo), hora })}
                    onDetalhe={setDetalhe}
                    loading={loading}
                />
            </div>

            {/* Desktop: week grid */}
            <div className="hidden md:block">
                <WeekGrid
                    dias={dias}
                    agendamentos={agendamentos}
                    loading={loading}
                    onSlotClick={(data, hora) => setModalNova({ data, hora })}
                    onDetalhe={setDetalhe}
                />

                {/* Legenda */}
                <div className="mt-3 flex flex-wrap gap-4 text-xs" style={{ color: 'var(--text-3)' }}>
                    {[
                        { bg: 'rgba(110,231,183,0.08)', border: 'rgba(110,231,183,0.2)', label: 'WhatsApp' },
                        { bg: 'rgba(59,130,246,0.1)',   border: 'rgba(59,130,246,0.25)', label: 'Manual' },
                        { bg: 'var(--bg-surface-2)',    border: 'rgba(255,255,255,0.1)', label: 'Concluído' },
                        { bg: 'rgba(239,68,68,0.08)',   border: 'rgba(239,68,68,0.2)',   label: 'Cancelado' },
                    ].map(l => (
                        <span key={l.label} className="flex items-center gap-1.5">
                            <span className="h-3 w-3 rounded-sm border" style={{ background: l.bg, borderColor: l.border }} />
                            {l.label}
                        </span>
                    ))}
                    <span className="flex items-center gap-1.5">
                        <span className="h-3 w-3 rounded-sm border border-dashed" style={{ background: 'var(--accent-light)', borderColor: 'var(--accent)' }} />
                        Slot livre (clique para reservar)
                    </span>
                </div>
            </div>

            {/* Detail modal */}
            {detalhe && (
                <DetalheModal
                    detalhe={detalhe}
                    onClose={() => setDetalhe(null)}
                    onConcluir={concluir}
                    onCancelar={cancelar}
                />
            )}

            {/* New booking modal */}
            {modalNova && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4 backdrop-blur-sm">
                    <div className="w-full max-w-sm rounded-2xl p-6 shadow-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                        <div className="mb-1 flex items-start justify-between">
                            <h3 style={{ fontFamily: 'Instrument Serif, Georgia, serif' }} className="text-xl font-semibold text-primary">
                                Nova reserva
                            </h3>
                            <button onClick={() => setModalNova(null)} style={{ color: 'var(--text-3)' }} className="hover:text-primary transition-colors">✕</button>
                        </div>
                        <p className="mb-4 text-sm" style={{ color: 'var(--text-3)' }}>
                            {new Date(modalNova.data).toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' })} às {modalNova.hora}
                        </p>
                        <div className="space-y-3">
                            <div>
                                <label className="label mb-1">Nome do cliente</label>
                                <input
                                    value={novaForm.nome}
                                    onChange={e => setNovaForm(f => ({ ...f, nome: e.target.value }))}
                                    className="input"
                                    placeholder="João Silva"
                                />
                            </div>
                            <div>
                                <label className="label mb-1">Telefone</label>
                                <input
                                    value={novaForm.tel}
                                    onChange={e => setNovaForm(f => ({ ...f, tel: e.target.value }))}
                                    className="input"
                                    placeholder="55119…"
                                />
                            </div>
                            <div>
                                <label className="label mb-1">Horário de término</label>
                                <input
                                    type="time"
                                    value={novaForm.fim}
                                    onChange={e => setNovaForm(f => ({ ...f, fim: e.target.value }))}
                                    className="input"
                                />
                            </div>
                            <div>
                                <label className="label mb-1">Observações</label>
                                <textarea
                                    value={novaForm.obs}
                                    onChange={e => setNovaForm(f => ({ ...f, obs: e.target.value }))}
                                    rows={2}
                                    className="input"
                                />
                            </div>
                        </div>
                        {erroReserva && (
                            <p className="mt-3 rounded-lg px-3 py-2 text-sm" style={{ background: 'rgba(239,68,68,0.1)', color: '#f87171' }}>
                                {erroReserva}
                            </p>
                        )}
                        <div className="mt-4 flex gap-2">
                            <button onClick={() => { setModalNova(null); setErroReserva(null); }} className="btn-secondary flex-1 justify-center">Cancelar</button>
                            <button
                                onClick={criarReserva}
                                disabled={salvando || !novaForm.nome || !novaForm.tel}
                                className="btn-primary flex-1 justify-center"
                            >
                                {salvando ? 'Salvando…' : 'Confirmar'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
