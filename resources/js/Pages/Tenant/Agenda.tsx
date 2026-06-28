import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Recurso } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

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

// ─── Constants ───────────────────────────────────────────────────────────────

const PX_PER_HOUR = 64;
const HOUR_START  = 7;
const HOUR_END    = 21;
const TOTAL_HOURS  = HOUR_END - HOUR_START;
const TOTAL_HEIGHT = TOTAL_HOURS * PX_PER_HOUR; // 896px
const HOURS = Array.from({ length: TOTAL_HOURS + 1 }, (_, i) => i + HOUR_START);

const MONTHS = [
    'Janeiro','Fevereiro','Março','Abril','Maio','Junho',
    'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro',
];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function addDays(d: Date, n: number) {
    const r = new Date(d); r.setDate(r.getDate() + n); return r;
}

function startOfWeek(d: Date) {
    const r   = new Date(d);
    const day = r.getDay();
    r.setDate(r.getDate() - day + (day === 0 ? -6 : 1));
    r.setHours(0, 0, 0, 0);
    return r;
}

function toISO(d: Date) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function fmtHora(iso: string) {
    return new Date(iso).toLocaleTimeString('pt-BR', {
        hour: '2-digit', minute: '2-digit', timeZone: 'America/Sao_Paulo',
    });
}

function fmtDiaLongo(d: Date) {
    return d.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' });
}

function getMinutosSP(iso: string): number {
    const d  = new Date(iso);
    const hh = parseInt(d.toLocaleString('en-US', { hour: '2-digit', hour12: false, timeZone: 'America/Sao_Paulo' }), 10);
    const mm = parseInt(d.toLocaleString('en-US', { minute: '2-digit', timeZone: 'America/Sao_Paulo' }), 10);
    return hh * 60 + mm;
}

function getDateSP(iso: string): string {
    return new Date(iso).toLocaleDateString('sv-SE', { timeZone: 'America/Sao_Paulo' });
}

function toLocalDateTimeInput(iso: string): string {
    const sp = new Date(iso).toLocaleString('sv-SE', { timeZone: 'America/Sao_Paulo' });
    return sp.slice(0, 16).replace(' ', 'T');
}

// ─── Color ───────────────────────────────────────────────────────────────────

function slotColor(a: AgendamentoCalendario): { accent: string; bg: string; text: string } {
    if (a.status === 'cancelado') return { accent: '#f87171', bg: 'rgba(239,68,68,0.07)',   text: '#f87171' };
    if (a.status === 'concluido') return { accent: '#6b7280', bg: 'rgba(107,114,128,0.07)', text: 'rgba(232,230,225,0.4)' };
    if (a.origem === 'manual')    return { accent: '#60a5fa', bg: 'rgba(59,130,246,0.08)',  text: '#93c5fd' };
    return                               { accent: '#34d399', bg: 'rgba(52,211,153,0.08)',  text: '#6ee7b7' };
}

// ─── Mini Calendar ────────────────────────────────────────────────────────────

function MiniCalendar({
    selected,
    onSelect,
    dotDates,
}: {
    selected: Date;
    onSelect: (d: Date) => void;
    dotDates: Set<string>;
}) {
    const [vm, setVm] = useState(() => new Date(selected.getFullYear(), selected.getMonth(), 1));

    useEffect(() => {
        setVm(new Date(selected.getFullYear(), selected.getMonth(), 1));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selected.getFullYear(), selected.getMonth()]);

    const hoje  = toISO(new Date());
    const selISO = toISO(selected);
    const year   = vm.getFullYear();
    const month  = vm.getMonth();

    const firstDow   = (new Date(year, month, 1).getDay() + 6) % 7; // Mon = 0
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const cells: (Date | null)[] = [];
    for (let i = 0; i < firstDow; i++) cells.push(null);
    for (let d = 1; d <= daysInMonth; d++) cells.push(new Date(year, month, d));
    while (cells.length % 7 !== 0) cells.push(null);

    const prev = () => setVm(month === 0 ? new Date(year - 1, 11, 1) : new Date(year, month - 1, 1));
    const next = () => setVm(month === 11 ? new Date(year + 1, 0, 1) : new Date(year, month + 1, 1));

    return (
        <div className="select-none px-3 pt-4 pb-3">
            {/* Month header */}
            <div className="mb-3 flex items-center justify-between">
                <button
                    onClick={prev}
                    className="flex h-6 w-6 items-center justify-center rounded-md transition-colors hover:bg-[var(--bg-surface-2)]"
                    style={{ color: 'var(--text-3)' }}
                >
                    <svg width={11} height={11} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M15 18l-6-6 6-6"/>
                    </svg>
                </button>
                <span className="text-[12px] font-semibold" style={{ color: 'var(--text-1)' }}>
                    {MONTHS[month]} {year}
                </span>
                <button
                    onClick={next}
                    className="flex h-6 w-6 items-center justify-center rounded-md transition-colors hover:bg-[var(--bg-surface-2)]"
                    style={{ color: 'var(--text-3)' }}
                >
                    <svg width={11} height={11} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M9 18l6-6-6-6"/>
                    </svg>
                </button>
            </div>

            {/* Weekday labels */}
            <div className="mb-1 grid grid-cols-7 text-center">
                {['S','T','Q','Q','S','S','D'].map((d, i) => (
                    <span key={i} style={{ fontSize: 10, color: 'var(--text-3)', fontWeight: 600 }}>{d}</span>
                ))}
            </div>

            {/* Day cells */}
            <div className="grid grid-cols-7">
                {cells.map((d, i) => {
                    if (!d) return <div key={i} style={{ height: 28 }} />;
                    const iso    = toISO(d);
                    const isSel  = iso === selISO;
                    const isToday = iso === hoje;
                    const hasDot = dotDates.has(iso);
                    return (
                        <button
                            key={i}
                            onClick={() => onSelect(d)}
                            className="relative flex flex-col items-center justify-center rounded-full transition-colors"
                            style={{
                                height: 28,
                                fontSize: 11,
                                fontWeight: isSel || isToday ? 600 : 400,
                                background: isSel ? 'var(--jade)' : 'transparent',
                                color: isSel ? 'white' : isToday ? 'var(--jade)' : 'var(--text-2)',
                            }}
                        >
                            {d.getDate()}
                            {hasDot && !isSel && (
                                <span
                                    className="absolute block rounded-full"
                                    style={{ bottom: 2, width: 4, height: 4, background: isToday ? 'var(--jade)' : 'var(--text-3)' }}
                                />
                            )}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

// ─── Now Indicator ────────────────────────────────────────────────────────────

function NowIndicator() {
    const [top, setTop] = useState<number | null>(null);

    useEffect(() => {
        const calc = () => {
            const min  = getMinutosSP(new Date().toISOString());
            const base = HOUR_START * 60;
            const end  = HOUR_END * 60;
            if (min < base || min > end) { setTop(null); return; }
            setTop((min - base) * PX_PER_HOUR / 60);
        };
        calc();
        const id = setInterval(calc, 30_000);
        return () => clearInterval(id);
    }, []);

    if (top === null) return null;

    return (
        <div
            className="pointer-events-none absolute left-0 right-0 z-10 flex items-center"
            style={{ top }}
        >
            <div
                className="h-2 w-2 flex-shrink-0 rounded-full"
                style={{ background: 'var(--accent)', marginLeft: -4 }}
            />
            <div className="flex-1" style={{ borderTop: '1.5px solid var(--accent)', opacity: 0.7 }} />
        </div>
    );
}

// ─── Event Block (desktop proportional) ──────────────────────────────────────

function eventPosition(a: AgendamentoCalendario): { top: number; height: number } {
    const startMin = getMinutosSP(a.start);
    const endMin   = getMinutosSP(a.end);
    const base     = HOUR_START * 60;
    const top      = Math.max(0, (startMin - base)) * PX_PER_HOUR / 60;
    const height   = Math.max(16, (endMin - startMin) * PX_PER_HOUR / 60);
    return { top, height };
}

function EventBlock({
    event,
    onClick,
}: {
    event: AgendamentoCalendario;
    onClick: (a: AgendamentoCalendario) => void;
}) {
    const { top, height }      = eventPosition(event);
    const { accent, bg, text } = slotColor(event);
    const compact = height < 40;

    return (
        <div
            onClick={e => { e.stopPropagation(); onClick(event); }}
            className="absolute left-1 right-1 cursor-pointer overflow-hidden rounded-md transition-all hover:brightness-110 hover:z-20"
            style={{ top, height, background: bg, borderLeft: `3px solid ${accent}`, zIndex: 1 }}
        >
            <div className="px-1.5 py-1" style={{ color: text }}>
                <p className={`truncate font-semibold leading-tight ${compact ? 'text-[9px]' : 'text-[11px]'}`}>
                    {event.title}
                </p>
                {!compact && (
                    <p className="mt-0.5 text-[10px] leading-tight opacity-70">
                        {fmtHora(event.start)}–{fmtHora(event.end)}
                    </p>
                )}
            </div>
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
    const hoje   = toISO(new Date());
    const bodyRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        // Scroll to 8am on mount
        if (bodyRef.current) bodyRef.current.scrollTop = PX_PER_HOUR;
    }, []);

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            {/* Sticky day headers */}
            <div
                className="flex flex-shrink-0"
                style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-sidebar)' }}
            >
                <div style={{ width: 52, flexShrink: 0 }} />
                {dias.map((d, i) => {
                    const isHoje = toISO(d) === hoje;
                    return (
                        <div
                            key={i}
                            className="flex-1 py-2 text-center"
                            style={{ borderLeft: '1px solid var(--border)' }}
                        >
                            <p
                                className="text-[10px] font-semibold uppercase tracking-wide"
                                style={{ color: isHoje ? 'var(--jade)' : 'var(--text-3)' }}
                            >
                                {d.toLocaleDateString('pt-BR', { weekday: 'short' })}
                            </p>
                            <div
                                className="mx-auto mt-1 flex h-7 w-7 items-center justify-center rounded-full text-[13px] font-semibold"
                                style={{
                                    background: isHoje ? 'var(--jade)' : 'transparent',
                                    color: isHoje ? 'white' : 'var(--text-1)',
                                }}
                            >
                                {d.getDate()}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Scrollable body */}
            <div ref={bodyRef} className="flex-1 overflow-y-auto scroll-hidden">
                {loading ? (
                    <div className="flex items-center justify-center" style={{ height: TOTAL_HEIGHT }}>
                        <svg className="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" style={{ color: 'var(--text-3)' }}>
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                    </div>
                ) : (
                    <div className="flex" style={{ height: TOTAL_HEIGHT }}>
                        {/* Time gutter */}
                        <div style={{ width: 52, flexShrink: 0, position: 'relative' }}>
                            {HOURS.slice(0, -1).map(h => (
                                <div
                                    key={h}
                                    className="absolute right-2 font-mono"
                                    style={{
                                        top: (h - HOUR_START) * PX_PER_HOUR - 7,
                                        fontSize: 10,
                                        color: 'var(--text-3)',
                                    }}
                                >
                                    {String(h).padStart(2, '0')}:00
                                </div>
                            ))}
                        </div>

                        {/* Day columns */}
                        {dias.map((d, dIdx) => {
                            const iso       = toISO(d);
                            const isHoje    = iso === hoje;
                            const dayEvents = agendamentos.filter(a => getDateSP(a.start) === iso);
                            return (
                                <div
                                    key={dIdx}
                                    style={{
                                        flex: 1,
                                        position: 'relative',
                                        borderLeft: '1px solid var(--border)',
                                        background: isHoje ? 'rgba(0,168,132,0.02)' : undefined,
                                    }}
                                >
                                    {/* Hour slot lines & click areas */}
                                    {HOURS.slice(0, -1).map(h => (
                                        <div
                                            key={h}
                                            onClick={() => onSlotClick(iso, `${String(h).padStart(2, '0')}:00`)}
                                            className="absolute left-0 right-0 cursor-pointer transition-colors hover:bg-[var(--accent-light)]"
                                            style={{
                                                top: (h - HOUR_START) * PX_PER_HOUR,
                                                height: PX_PER_HOUR,
                                                borderTop: '1px solid var(--border)',
                                            }}
                                        />
                                    ))}

                                    {/* Events */}
                                    {dayEvents.map(a => (
                                        <EventBlock key={a.id} event={a} onClick={onDetalhe} />
                                    ))}

                                    {/* Current time indicator */}
                                    {isHoje && <NowIndicator />}
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </div>
    );
}

// ─── Day Timeline (mobile) ────────────────────────────────────────────────────

function DayTimeline({
    dia,
    agendamentos,
    onDetalhe,
    onNovo,
    loading,
}: {
    dia: Date;
    agendamentos: AgendamentoCalendario[];
    onDetalhe: (a: AgendamentoCalendario) => void;
    onNovo: (hora: string) => void;
    loading: boolean;
}) {
    const iso = toISO(dia);
    const dayEvents = agendamentos
        .filter(a => getDateSP(a.start) === iso)
        .sort((a, b) => a.start.localeCompare(b.start));

    if (loading) {
        return (
            <div className="flex flex-1 items-center justify-center">
                <svg className="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" style={{ color: 'var(--text-3)' }}>
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            </div>
        );
    }

    if (dayEvents.length === 0) {
        return (
            <div className="flex flex-1 flex-col items-center justify-center gap-3 pb-20">
                <p className="text-sm font-medium text-primary">Dia livre</p>
                <p className="text-xs" style={{ color: 'var(--text-3)' }}>Nenhum agendamento para este dia</p>
                <button onClick={() => onNovo('09:00')} className="btn-primary text-sm mt-1">
                    <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    Nova reserva
                </button>
            </div>
        );
    }

    return (
        <div className="flex-1 overflow-y-auto scroll-hidden px-4 py-3 pb-24 space-y-2">
            {dayEvents.map(a => {
                const { accent, bg, text } = slotColor(a);
                const initials = a.title.trim().split(/\s+/).map(w => w[0] ?? '').slice(0, 2).join('').toUpperCase();
                return (
                    <button
                        key={a.id}
                        onClick={() => onDetalhe(a)}
                        className="flex w-full items-center gap-3 overflow-hidden rounded-xl px-3 py-3 text-left transition-opacity hover:opacity-80"
                        style={{ background: bg, borderLeft: `4px solid ${accent}` }}
                    >
                        <div
                            className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold"
                            style={{ background: accent + '30', color: accent }}
                        >
                            {initials}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold" style={{ color: text }}>
                                {a.title}
                            </p>
                            <p className="text-xs opacity-80" style={{ color: text }}>
                                {fmtHora(a.start)} – {fmtHora(a.end)}
                            </p>
                        </div>
                        <svg
                            width={14} height={14} viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" strokeWidth="2"
                            style={{ color: text, opacity: 0.4, flexShrink: 0 }}
                        >
                            <path d="M9 18l6-6-6-6" />
                        </svg>
                    </button>
                );
            })}
        </div>
    );
}

// ─── Detail Modal ─────────────────────────────────────────────────────────────

function DetalheModal({
    detalhe,
    onClose,
    onConcluir,
    onCancelar,
    onSalvar,
}: {
    detalhe: AgendamentoCalendario;
    onClose: () => void;
    onConcluir: (id: number) => void;
    onCancelar: (id: number) => void;
    onSalvar: (id: number, dados: { cliente_nome: string; cliente_telefone: string; inicio: string; fim: string; status: string }) => void;
}) {
    const [editando, setEditando]   = useState(false);
    const [salvando, setSalvando]   = useState(false);
    const [form, setForm] = useState({
        cliente_nome:     detalhe.title,
        cliente_telefone: detalhe.telefone,
        inicio:           toLocalDateTimeInput(detalhe.start),
        fim:              detalhe.end ? toLocalDateTimeInput(detalhe.end) : '',
        status:           detalhe.status,
    });
    const nomeRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (editando) nomeRef.current?.focus();
    }, [editando]);

    const salvar = () => {
        setSalvando(true);
        onSalvar(detalhe.id, {
            cliente_nome:     form.cliente_nome,
            cliente_telefone: form.cliente_telefone,
            inicio:           form.inicio + ':00',
            fim:              form.fim + ':00',
            status:           form.status,
        });
    };

    const { accent } = slotColor(detalhe);

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 px-4 backdrop-blur-sm sm:items-center">
            <div
                className="w-full max-w-sm overflow-hidden rounded-t-2xl shadow-2xl sm:rounded-2xl"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}
            >
                {/* Accent top bar */}
                <div style={{ height: 3, background: accent }} />

                <div className="p-6">
                    <div className="mb-4 flex items-start justify-between">
                        <div>
                            <h3 className="text-xl font-semibold text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                                {editando ? 'Editar agendamento' : detalhe.title}
                            </h3>
                            {!editando && (
                                <p className="text-sm" style={{ color: 'var(--text-3)' }}>{detalhe.telefone}</p>
                            )}
                        </div>
                        <button
                            onClick={onClose}
                            className="flex h-7 w-7 items-center justify-center rounded-full transition-colors hover:bg-[var(--bg-surface-2)]"
                            style={{ color: 'var(--text-3)' }}
                        >
                            <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {editando ? (
                        <div className="space-y-3">
                            <div>
                                <label className="label mb-1">Nome do cliente</label>
                                <input ref={nomeRef} value={form.cliente_nome} onChange={e => setForm(f => ({ ...f, cliente_nome: e.target.value }))} className="input" />
                            </div>
                            <div>
                                <label className="label mb-1">Telefone</label>
                                <input value={form.cliente_telefone} onChange={e => setForm(f => ({ ...f, cliente_telefone: e.target.value }))} className="input" />
                            </div>
                            <div>
                                <label className="label mb-1">Início</label>
                                <input type="datetime-local" value={form.inicio} onChange={e => setForm(f => ({ ...f, inicio: e.target.value }))} className="input" />
                            </div>
                            <div>
                                <label className="label mb-1">Término</label>
                                <input type="datetime-local" value={form.fim} onChange={e => setForm(f => ({ ...f, fim: e.target.value }))} className="input" />
                            </div>
                            <div>
                                <label className="label mb-1">Status</label>
                                <select value={form.status} onChange={e => setForm(f => ({ ...f, status: e.target.value }))} className="input">
                                    <option value="confirmado">Confirmado</option>
                                    <option value="concluido">Concluído</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>
                            <div className="flex gap-2 pt-1">
                                <button onClick={() => setEditando(false)} className="btn-secondary flex-1 justify-center text-xs py-2">Voltar</button>
                                <button
                                    onClick={salvar}
                                    disabled={salvando || !form.cliente_nome || !form.inicio || !form.fim}
                                    className="btn-primary flex-1 justify-center text-xs py-2"
                                >
                                    {salvando ? 'Salvando…' : 'Salvar'}
                                </button>
                            </div>
                        </div>
                    ) : (
                        <>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span style={{ color: 'var(--text-3)' }}>Horário</span>
                                    <span className="font-medium text-primary">{fmtHora(detalhe.start)} – {fmtHora(detalhe.end)}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span style={{ color: 'var(--text-3)' }}>Origem</span>
                                    <span style={{ color: 'var(--text-2)' }}>
                                        {detalhe.origem === 'whatsapp' ? 'WhatsApp' : 'Manual'}
                                    </span>
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

                            <div className="mt-5 flex flex-wrap gap-2">
                                <a href={`tel:${detalhe.telefone}`} className="btn-secondary flex-1 justify-center text-xs py-2">
                                    Ligar
                                </a>
                                <button onClick={() => setEditando(true)} className="btn-secondary flex-1 justify-center text-xs py-2">
                                    Editar
                                </button>
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
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────

export default function Agenda({ recursos, profissionais }: Props) {
    const entidades = useMemo<EntidadeAgenda[]>(() =>
        recursos.length > 0
            ? recursos.map(r => ({ id: r.id, nome: r.nome, tipo: 'recurso' as const }))
            : profissionais.map(p => ({ id: p.id, nome: p.nome, tipo: 'profissional' as const })),
    [recursos, profissionais]);

    const [semana, setSemana]         = useState(() => startOfWeek(new Date()));
    const [diaAtivo, setDiaAtivo]     = useState(() => new Date());
    const [entidadeId, setEntidadeId] = useState<number>(() => entidades[0]?.id ?? 0);
    const [agendamentos, setAgs]      = useState<AgendamentoCalendario[]>([]);
    const [loading, setLoading]       = useState(false);
    const [detalhe, setDetalhe]       = useState<AgendamentoCalendario | null>(null);
    const [modalNova, setModalNova]   = useState<{ data: string; hora: string } | null>(null);
    const [novaForm, setNovaForm]     = useState({ nome: '', tel: '', fim: '', obs: '' });
    const [salvando, setSalvando]     = useState(false);
    const [erroReserva, setErroReserva] = useState<string | null>(null);

    const tipoEntidade = useMemo(
        () => entidades.find(e => e.id === entidadeId)?.tipo ?? 'profissional',
        [entidades, entidadeId],
    );

    const dias = useMemo(() => Array.from({ length: 7 }, (_, i) => addDays(semana, i)), [semana]);

    const dotDates = useMemo(() => {
        const s = new Set<string>();
        agendamentos.forEach(a => s.add(getDateSP(a.start)));
        return s;
    }, [agendamentos]);

    const carregar = useCallback(() => {
        if (!entidadeId) return;
        setLoading(true);
        const inicio = semana;
        const fim    = addDays(addDays(inicio, 6), 1);
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
    }, [entidadeId, tipoEntidade, semana]);

    useEffect(() => { carregar(); }, [carregar]);

    const navegarDia = (d: Date) => {
        setDiaAtivo(d);
        setSemana(startOfWeek(d));
    };

    const prevWeek = () => { const s = addDays(semana, -7); setSemana(s); setDiaAtivo(s); };
    const nextWeek = () => { const s = addDays(semana,  7); setSemana(s); setDiaAtivo(s); };
    const goToday  = () => { const t = new Date(); setSemana(startOfWeek(t)); setDiaAtivo(t); };

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
    const salvarEdicao = (id: number, dados: { cliente_nome: string; cliente_telefone: string; inicio: string; fim: string; status: string }) => {
        router.put(route('tenant.agendamentos.update', id), dados, {
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
            onError: errors => {
                const msg = Object.values(errors)[0] as string;
                setErroReserva(msg ?? 'Erro ao criar agendamento.');
            },
            onFinish: () => setSalvando(false),
        });
    };

    const fmtRange = () => {
        const d0 = dias[0];
        const d6 = dias[6];
        const m0 = d0.toLocaleDateString('pt-BR', { month: 'short' });
        const m6 = d6.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
        return `${d0.getDate()} ${m0} – ${d6.getDate()} ${m6}`;
    };

    return (
        <AppLayout fullHeight>
            <Head title="Agenda" />

            {/* ───────────── Mobile ───────────── */}
            <div className="flex flex-1 flex-col md:hidden overflow-hidden">
                {/* Top panel: mini calendar + day nav + entity pills */}
                <div
                    className="flex-shrink-0"
                    style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-sidebar)' }}
                >
                    <MiniCalendar selected={diaAtivo} onSelect={navegarDia} dotDates={dotDates} />

                    {/* Day navigation row */}
                    <div className="flex items-center gap-2 px-4 pb-2">
                        <button
                            onClick={() => navegarDia(addDays(diaAtivo, -1))}
                            className="flex h-8 w-8 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
                            style={{ color: 'var(--text-3)' }}
                        >
                            <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                        </button>
                        <p className="flex-1 text-center text-[13px] font-semibold text-primary">{fmtDiaLongo(diaAtivo)}</p>
                        <button
                            onClick={() => navegarDia(addDays(diaAtivo, 1))}
                            className="flex h-8 w-8 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
                            style={{ color: 'var(--text-3)' }}
                        >
                            <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </button>
                    </div>

                    {/* Entity pills */}
                    {entidades.length > 1 && (
                        <div className="flex gap-2 overflow-x-auto scroll-hidden px-4 pb-3">
                            {entidades.map(e => (
                                <button
                                    key={e.id}
                                    onClick={() => setEntidadeId(e.id)}
                                    className="flex-shrink-0 rounded-full px-3 py-1 text-xs font-medium transition-all"
                                    style={{
                                        background: entidadeId === e.id ? 'var(--jade)' : 'var(--bg-surface-2)',
                                        color: entidadeId === e.id ? 'white' : 'var(--text-2)',
                                    }}
                                >
                                    {e.nome}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Day events */}
                <DayTimeline
                    dia={diaAtivo}
                    agendamentos={agendamentos}
                    onDetalhe={setDetalhe}
                    onNovo={hora => setModalNova({ data: toISO(diaAtivo), hora })}
                    loading={loading}
                />

                {/* FAB */}
                <button
                    onClick={() => setModalNova({ data: toISO(diaAtivo), hora: '09:00' })}
                    aria-label="Nova reserva"
                    className="fixed bottom-6 right-5 z-20 flex items-center justify-center rounded-full shadow-lg transition-transform hover:scale-105 active:scale-95"
                    style={{ width: 52, height: 52, background: 'var(--jade)', color: 'white' }}
                >
                    <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                </button>
            </div>

            {/* ───────────── Desktop ───────────── */}
            <div className="hidden flex-1 md:flex overflow-hidden">
                {/* Left panel */}
                <aside
                    className="flex flex-col overflow-y-auto scroll-hidden flex-shrink-0"
                    style={{ width: 220, borderRight: '1px solid var(--border)', background: 'var(--bg-sidebar)' }}
                >
                    <MiniCalendar
                        selected={diaAtivo}
                        onSelect={d => { setDiaAtivo(d); setSemana(startOfWeek(d)); }}
                        dotDates={dotDates}
                    />

                    {/* Entity selector */}
                    {entidades.length > 0 && (
                        <div style={{ borderTop: '1px solid var(--border)', padding: '12px 8px 8px' }}>
                            <p className="label px-2 mb-2">
                                {entidades[0].tipo === 'profissional' ? 'Profissionais' : 'Recursos'}
                            </p>
                            {entidades.map(e => (
                                <button
                                    key={e.id}
                                    onClick={() => setEntidadeId(e.id)}
                                    className="mb-0.5 flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left transition-all"
                                    style={{
                                        background: entidadeId === e.id ? 'var(--jade-light)' : 'transparent',
                                        color: entidadeId === e.id ? 'var(--jade)' : 'var(--text-2)',
                                        fontSize: 13,
                                        fontWeight: entidadeId === e.id ? 500 : 400,
                                    }}
                                >
                                    <span
                                        className="h-2 w-2 flex-shrink-0 rounded-full"
                                        style={{ background: entidadeId === e.id ? 'var(--jade)' : 'var(--text-3)' }}
                                    />
                                    {e.nome}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Legend */}
                    <div style={{ borderTop: '1px solid var(--border)', padding: '12px 10px 8px', marginTop: 'auto' }}>
                        <p className="label px-2 mb-2">Legenda</p>
                        {[
                            { accent: '#34d399', label: 'WhatsApp' },
                            { accent: '#60a5fa', label: 'Manual' },
                            { accent: '#6b7280', label: 'Concluído' },
                            { accent: '#f87171', label: 'Cancelado' },
                        ].map(l => (
                            <div key={l.label} className="flex items-center gap-2 px-2 py-0.5">
                                <span className="h-2.5 w-2.5 flex-shrink-0 rounded-sm" style={{ background: l.accent, opacity: 0.85 }} />
                                <span style={{ fontSize: 11, color: 'var(--text-3)' }}>{l.label}</span>
                            </div>
                        ))}
                    </div>
                </aside>

                {/* Main area */}
                <div className="flex min-h-0 flex-1 flex-col overflow-hidden">
                    {/* Toolbar */}
                    <div
                        className="flex flex-shrink-0 items-center gap-3 px-5 py-3"
                        style={{ borderBottom: '1px solid var(--border)' }}
                    >
                        <button
                            onClick={prevWeek}
                            className="flex h-8 w-8 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
                            style={{ color: 'var(--text-3)' }}
                        >
                            <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                        </button>

                        <button
                            onClick={goToday}
                            className="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors hover:bg-[var(--bg-surface-2)]"
                            style={{ border: '1px solid var(--border-strong)', color: 'var(--text-2)' }}
                        >
                            Hoje
                        </button>

                        <button
                            onClick={nextWeek}
                            className="flex h-8 w-8 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
                            style={{ color: 'var(--text-3)' }}
                        >
                            <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </button>

                        <span className="text-sm font-medium" style={{ color: 'var(--text-2)' }}>
                            {fmtRange()}
                        </span>

                        <div className="flex-1" />

                        {loading && (
                            <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" style={{ color: 'var(--text-3)' }}>
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                        )}

                        <button
                            onClick={() => setModalNova({ data: toISO(diaAtivo), hora: '09:00' })}
                            className="btn-primary text-sm"
                        >
                            <svg width={12} height={12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                            Nova reserva
                        </button>
                    </div>

                    {/* Week grid or empty state */}
                    {entidades.length === 0 ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-2">
                            <p className="font-medium text-primary">Nenhum profissional cadastrado</p>
                            <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                                Cadastre um profissional para usar a agenda.
                            </p>
                        </div>
                    ) : (
                        <WeekGrid
                            dias={dias}
                            agendamentos={agendamentos}
                            loading={false}
                            onSlotClick={(data, hora) => setModalNova({ data, hora })}
                            onDetalhe={setDetalhe}
                        />
                    )}
                </div>
            </div>

            {/* ── Detail modal ── */}
            {detalhe && (
                <DetalheModal
                    detalhe={detalhe}
                    onClose={() => setDetalhe(null)}
                    onConcluir={concluir}
                    onCancelar={cancelar}
                    onSalvar={salvarEdicao}
                />
            )}

            {/* ── New booking modal ── */}
            {modalNova && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 backdrop-blur-sm sm:items-center sm:px-4">
                    <div
                        className="w-full max-w-sm rounded-t-2xl shadow-2xl sm:rounded-2xl"
                        style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}
                    >
                        <div className="p-6">
                            <div className="mb-1 flex items-start justify-between">
                                <h3
                                    className="text-xl font-semibold text-primary"
                                    style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}
                                >
                                    Nova reserva
                                </h3>
                                <button
                                    onClick={() => setModalNova(null)}
                                    className="flex h-7 w-7 items-center justify-center rounded-full transition-colors hover:bg-[var(--bg-surface-2)]"
                                    style={{ color: 'var(--text-3)' }}
                                >
                                    <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                        <path d="M18 6L6 18M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <p className="mb-4 text-sm" style={{ color: 'var(--text-3)' }}>
                                {new Date(modalNova.data + 'T12:00:00').toLocaleDateString('pt-BR', {
                                    weekday: 'long', day: '2-digit', month: 'long',
                                })} às {modalNova.hora}
                            </p>

                            <div className="space-y-3">
                                <div>
                                    <label className="label mb-1">Nome do cliente</label>
                                    <input
                                        autoFocus
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
                                <button
                                    onClick={() => { setModalNova(null); setErroReserva(null); }}
                                    className="btn-secondary flex-1 justify-center"
                                >
                                    Cancelar
                                </button>
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
                </div>
            )}
        </AppLayout>
    );
}
