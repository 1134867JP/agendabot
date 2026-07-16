import { Head, Link, router } from '@inertiajs/react';
import { useState, useEffect, useCallback, useMemo, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Recurso } from '@/types';

// ─── Types ───────────────────────────────────────────────────────────────────

interface AgendamentoCalendario {
    id: number;
    tipo: 'agendamento' | 'bloqueio';
    title: string;
    start: string;
    end: string;
    telefone: string;
    status: 'confirmado' | 'cancelado' | 'concluido' | 'bloqueado';
    valor_total: number | null;
    origem: 'whatsapp' | 'manual';
    profissional_id: number | null;
    profissional_nome: string | null;
    servico_nome: string | null;
}

interface EntidadeAgenda {
    id: number;
    nome: string;
    tipo: 'recurso' | 'profissional';
    cor: string;
}

interface Props extends PageProps {
    recursos: Recurso[];
    profissionais: { id: number; nome: string }[];
    servicos: ServicoAgenda[];
}

interface ServicoAgenda {
    id: number;
    nome: string;
    duracao_minutos: number;
    valor_min: number | string | null;
    valor_max: number | string | null;
    profissional_ids: number[];
}

interface ClienteBusca {
    id: number;
    nome: string;
    telefone: string;
    agendamentos_count: number;
}

// ─── Constants ───────────────────────────────────────────────────────────────

const PX_PER_HOUR = 64;
const HOUR_START  = 7;
const HOUR_END    = 21;
const TOTAL_HOURS  = HOUR_END - HOUR_START;
const TOTAL_HEIGHT = TOTAL_HOURS * PX_PER_HOUR; // 896px
const HOURS = Array.from({ length: TOTAL_HOURS + 1 }, (_, i) => i + HOUR_START);
const ALL_PROFESSIONALS_ID = 0;
const PROFESSIONAL_COLORS = [
    { accent: '#34d399', bg: 'rgba(52,211,153,0.10)', text: '#6ee7b7' },
    { accent: '#60a5fa', bg: 'rgba(59,130,246,0.10)', text: '#93c5fd' },
    { accent: '#f59e0b', bg: 'rgba(245,158,11,0.10)', text: '#fbbf24' },
    { accent: '#a78bfa', bg: 'rgba(139,92,246,0.10)', text: '#c4b5fd' },
    { accent: '#f472b6', bg: 'rgba(236,72,153,0.10)', text: '#f9a8d4' },
    { accent: '#22d3ee', bg: 'rgba(6,182,212,0.10)', text: '#67e8f9' },
];

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

function addMinutesToTime(time: string, minutes: number): string {
    const [hours, currentMinutes] = time.split(':').map(Number);
    const totalMinutes = (hours * 60 + currentMinutes + minutes) % (24 * 60);

    return `${String(Math.floor(totalMinutes / 60)).padStart(2, '0')}:${String(totalMinutes % 60).padStart(2, '0')}`;
}

function normalizePhone(phone: string): string {
    return phone.replace(/\D/g, '').slice(0, 13);
}

function isValidPhone(phone: string): boolean {
    return /^(?:55)?[1-9][0-9]{9,10}$/.test(normalizePhone(phone));
}

function professionalColor(id: number | null | undefined) {
    if (!id) return PROFESSIONAL_COLORS[0];
    return PROFESSIONAL_COLORS[(id - 1) % PROFESSIONAL_COLORS.length];
}

function descricaoServico(servico: ServicoAgenda) {
    const partes = [`${servico.duracao_minutos} min`];
    const minimo = servico.valor_min != null ? Number(servico.valor_min) : null;
    const maximo = servico.valor_max != null ? Number(servico.valor_max) : null;

    if (minimo != null && maximo != null && Math.abs(minimo - maximo) < 0.01) {
        partes.push(minimo.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }));
    } else if (minimo != null) {
        partes.push(`a partir de ${minimo.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`);
    }

    return partes.join(' · ');
}

// ─── Color ───────────────────────────────────────────────────────────────────

function slotColor(a: AgendamentoCalendario): { accent: string; bg: string; text: string } {
    if (a.tipo === 'bloqueio')   return { accent: '#94a3b8', bg: 'rgba(100,116,139,0.16)', text: '#cbd5e1' };
    if (a.status === 'cancelado') return { accent: '#f87171', bg: 'rgba(239,68,68,0.07)',   text: '#f87171' };
    if (a.status === 'concluido') return { accent: '#6b7280', bg: 'rgba(107,114,128,0.07)', text: 'rgba(232,230,225,0.4)' };
    if (a.profissional_id)        return professionalColor(a.profissional_id);
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
                    className="flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
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
                    className="flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
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
                    if (!d) return <div key={i} style={{ height: 36 }} />;
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
                                height: 36,
                                fontSize: 12,
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

function eventLane(event: AgendamentoCalendario, dayEvents: AgendamentoCalendario[]): { index: number; count: number } {
    const eventStart = new Date(event.start).getTime();
    const eventEnd = new Date(event.end).getTime();
    const simultaneous = dayEvents
        .filter(other => {
            const otherStart = new Date(other.start).getTime();
            const otherEnd = new Date(other.end).getTime();
            return eventStart < otherEnd && eventEnd > otherStart;
        })
        .sort((a, b) => (a.profissional_id ?? 0) - (b.profissional_id ?? 0) || a.id - b.id);

    return {
        index: Math.max(0, simultaneous.findIndex(item => item.id === event.id)),
        count: Math.max(1, simultaneous.length),
    };
}

function EventBlock({
    event,
    dayEvents,
    onClick,
}: {
    event: AgendamentoCalendario;
    dayEvents: AgendamentoCalendario[];
    onClick: (a: AgendamentoCalendario) => void;
}) {
    const { top, height }      = eventPosition(event);
    const lane = eventLane(event, dayEvents);
    const { accent, bg, text } = slotColor(event);
    const compact = height < 40;

    return (
        <div
            onClick={e => { e.stopPropagation(); onClick(event); }}
            className="absolute cursor-pointer overflow-hidden rounded-md transition-all hover:brightness-110 hover:z-20"
            style={{
                top,
                height,
                left: `calc(${lane.index * 100 / lane.count}% + 4px)`,
                width: `calc(${100 / lane.count}% - 8px)`,
                background: bg,
                borderLeft: `3px solid ${accent}`,
                zIndex: 1,
            }}
        >
            <div className="px-1.5 py-1" style={{ color: text }}>
                <p className={`truncate font-semibold leading-tight ${compact ? 'text-[9px]' : 'text-[11px]'}`}>
                    {event.title}
                </p>
                {!compact && (
                    <div className="mt-0.5 flex items-center gap-1 text-[10px] leading-tight opacity-75">
                        {event.profissional_id && (
                            <span className="h-1.5 w-1.5 flex-shrink-0 rounded-full" style={{ background: professionalColor(event.profissional_id).accent }} />
                        )}
                        <span className="truncate">
                            {event.profissional_nome ? `${event.profissional_nome} · ` : ''}{fmtHora(event.start)}–{fmtHora(event.end)}
                        </span>
                    </div>
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
                                        <EventBlock key={`${a.tipo}-${a.id}`} event={a} dayEvents={dayEvents} onClick={onDetalhe} />
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
                        key={`${a.tipo}-${a.id}`}
                        onClick={() => onDetalhe(a)}
                        className="flex w-full items-center gap-3 overflow-hidden rounded-xl px-3 py-3 text-left transition-opacity hover:opacity-80"
                        style={{ background: bg, borderLeft: `4px solid ${accent}` }}
                    >
                        <div
                            className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold"
                            style={{ background: accent + '30', color: accent }}
                        >
                            {a.tipo === 'bloqueio' ? (
                                <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                    <rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" />
                                </svg>
                            ) : initials}
                        </div>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold" style={{ color: text }}>
                                {a.title}
                            </p>
                            <p className="text-xs opacity-80" style={{ color: text }}>
                                {fmtHora(a.start)} – {fmtHora(a.end)}
                            </p>
                            {a.profissional_nome && (
                                <p className="mt-0.5 flex items-center gap-1 text-[11px] opacity-70" style={{ color: text }}>
                                    <span className="h-1.5 w-1.5 rounded-full" style={{ background: professionalColor(a.profissional_id).accent }} />
                                    {a.profissional_nome}
                                </p>
                            )}
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
    const telefoneValido = isValidPhone(form.cliente_telefone);

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
                                <input
                                    value={form.cliente_telefone}
                                    onChange={e => setForm(f => ({ ...f, cliente_telefone: normalizePhone(e.target.value) }))}
                                    className="input"
                                    inputMode="tel"
                                    autoComplete="tel"
                                    maxLength={13}
                                    aria-invalid={!telefoneValido}
                                />
                                {!telefoneValido && (
                                    <p className="mt-1 text-xs" style={{ color: '#f87171' }}>
                                        Informe o telefone com DDD, por exemplo: 54999999999.
                                    </p>
                                )}
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
                                <select value={form.status} onChange={e => setForm(f => ({ ...f, status: e.target.value as typeof f.status }))} className="input">
                                    <option value="confirmado">Confirmado</option>
                                    <option value="concluido">Concluído</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>
                            <div className="flex gap-2 pt-1">
                                <button onClick={() => setEditando(false)} className="btn-secondary flex-1 justify-center text-xs py-2">Voltar</button>
                                <button
                                    onClick={salvar}
                                    disabled={salvando || !form.cliente_nome || !telefoneValido || !form.inicio || !form.fim}
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
                                {detalhe.profissional_nome && (
                                    <div className="flex justify-between gap-4">
                                        <span style={{ color: 'var(--text-3)' }}>Profissional</span>
                                        <span className="flex items-center gap-1.5 text-right" style={{ color: 'var(--text-2)' }}>
                                            <span className="h-2 w-2 rounded-full" style={{ background: professionalColor(detalhe.profissional_id).accent }} />
                                            {detalhe.profissional_nome}
                                        </span>
                                    </div>
                                )}
                                {detalhe.servico_nome && (
                                    <div className="flex justify-between gap-4">
                                        <span style={{ color: 'var(--text-3)' }}>Serviço</span>
                                        <span className="text-right" style={{ color: 'var(--text-2)' }}>{detalhe.servico_nome}</span>
                                    </div>
                                )}
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

export default function Agenda({ recursos, profissionais, servicos }: Props) {
    const entidades = useMemo<EntidadeAgenda[]>(() =>
        recursos.length > 0
            ? recursos.map((r, index) => ({ id: r.id, nome: r.nome, tipo: 'recurso' as const, cor: PROFESSIONAL_COLORS[index % PROFESSIONAL_COLORS.length].accent }))
            : profissionais.map(p => ({ id: p.id, nome: p.nome, tipo: 'profissional' as const, cor: professionalColor(p.id).accent })),
    [recursos, profissionais]);

    const temVisaoGeral = recursos.length === 0 && profissionais.length > 1;

    const [semana, setSemana]         = useState(() => startOfWeek(new Date()));
    const [diaAtivo, setDiaAtivo]     = useState(() => new Date());
    const [entidadeId, setEntidadeId] = useState<number>(() => temVisaoGeral ? ALL_PROFESSIONALS_ID : (entidades[0]?.id ?? 0));
    const [agendamentos, setAgs]      = useState<AgendamentoCalendario[]>([]);
    const [loading, setLoading]       = useState(false);
    const [detalhe, setDetalhe]       = useState<AgendamentoCalendario | null>(null);
    const [detalheBloqueio, setDetalheBloqueio] = useState<AgendamentoCalendario | null>(null);
    const [modalNova, setModalNova]   = useState<{ data: string; hora: string } | null>(null);
    const [modalBloqueio, setModalBloqueio] = useState<{ data: string; hora: string } | null>(null);
    const [bloqueioForm, setBloqueioForm] = useState({ fim: '10:00', motivo: '' });
    const [bloqueioEntidadeId, setBloqueioEntidadeId] = useState<number>(() => entidades[0]?.id ?? 0);
    const [erroBloqueio, setErroBloqueio] = useState<string | null>(null);
    const [novaForm, setNovaForm]     = useState({ nome: '', tel: '', fim: '', obs: '', servicoId: 0 });
    const [clientesEncontrados, setClientesEncontrados] = useState<ClienteBusca[]>([]);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [clienteSelecionadoId, setClienteSelecionadoId] = useState<number | null>(null);
    const clienteBuscaTimer = useRef<number | null>(null);
    const [novoProfissionalId, setNovoProfissionalId] = useState<number>(() => profissionais[0]?.id ?? 0);
    const [salvando, setSalvando]     = useState(false);
    const [erroReserva, setErroReserva] = useState<string | null>(null);
    const telefoneValido = isValidPhone(novaForm.tel);

    const pesquisarCliente = (valor: string) => {
        setClienteSelecionadoId(null);
        if (clienteBuscaTimer.current) window.clearTimeout(clienteBuscaTimer.current);

        const termo = valor.trim();
        if (termo.length < 2) {
            setClientesEncontrados([]);
            setBuscandoCliente(false);
            return;
        }

        setBuscandoCliente(true);
        clienteBuscaTimer.current = window.setTimeout(async () => {
            try {
                const response = await fetch(`${route('tenant.clientes.search')}?q=${encodeURIComponent(termo)}`, {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json() as { clientes?: ClienteBusca[] };
                setClientesEncontrados(response.ok ? (payload.clientes ?? []) : []);
            } catch {
                setClientesEncontrados([]);
            } finally {
                setBuscandoCliente(false);
            }
        }, 300);
    };

    const selecionarCliente = (cliente: ClienteBusca) => {
        setClienteSelecionadoId(cliente.id);
        setNovaForm(form => ({ ...form, nome: cliente.nome, tel: normalizePhone(cliente.telefone) }));
        setClientesEncontrados([]);
    };

    const abrirBloqueio = (data: string, hora: string) => {
        setBloqueioEntidadeId(entidadeId || entidades[0]?.id || 0);
        setBloqueioForm({ fim: addMinutesToTime(hora, 60), motivo: '' });
        setErroBloqueio(null);
        setModalBloqueio({ data, hora });
    };

    const abrirDetalhe = (evento: AgendamentoCalendario) => {
        if (evento.tipo === 'bloqueio') setDetalheBloqueio(evento);
        else setDetalhe(evento);
    };

    const tipoEntidade = useMemo(
        () => entidades.find(e => e.id === entidadeId)?.tipo ?? 'profissional',
        [entidades, entidadeId],
    );

    const servicosDisponiveis = useMemo(() => servicos.filter(servico =>
        tipoEntidade === 'recurso'
        || servico.profissional_ids.length === 0
        || servico.profissional_ids.includes(novoProfissionalId)
    ), [novoProfissionalId, servicos, tipoEntidade]);

    const atualizarServico = (servicoId: number) => {
        const servico = servicos.find(item => item.id === servicoId);
        setNovaForm(form => ({
            ...form,
            servicoId,
            fim: modalNova && servico
                ? addMinutesToTime(modalNova.hora, servico.duracao_minutos)
                : form.fim,
        }));
    };

    const atualizarProfissional = (profissionalId: number) => {
        setNovoProfissionalId(profissionalId);
        const opcoes = servicos.filter(servico =>
            servico.profissional_ids.length === 0 || servico.profissional_ids.includes(profissionalId)
        );
        const servico = opcoes[0];
        setNovaForm(form => ({
            ...form,
            servicoId: servico?.id ?? 0,
            fim: modalNova
                ? addMinutesToTime(modalNova.hora, servico?.duracao_minutos ?? 30)
                : form.fim,
        }));
    };

    const dias = useMemo(() => Array.from({ length: 7 }, (_, i) => addDays(semana, i)), [semana]);

    const dotDates = useMemo(() => {
        const s = new Set<string>();
        agendamentos.forEach(a => s.add(getDateSP(a.start)));
        return s;
    }, [agendamentos]);

    const carregar = useCallback(() => {
        if (entidades.length === 0) return;
        setLoading(true);
        const inicio = semana;
        const fim    = addDays(addDays(inicio, 6), 1);
        const param  = tipoEntidade === 'recurso'
            ? `recurso_id=${entidadeId}`
            : entidadeId === ALL_PROFESSIONALS_ID
                ? 'todos_profissionais=1'
                : `profissional_id=${entidadeId}`;
        fetch(
            route('tenant.agenda.disponibilidade') +
            `?${param}&data_inicio=${toISO(inicio)}&data_fim=${toISO(fim)}`,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        )
            .then(r => r.json())
            .then(setAgs)
            .finally(() => setLoading(false));
    }, [entidadeId, entidades.length, tipoEntidade, semana]);

    useEffect(() => { carregar(); }, [carregar]);

    useEffect(() => {
        if (!modalNova) return;

        setNovaForm(form => {
            const servicoInicial = servicosDisponiveis.find(servico => servico.id === form.servicoId)
                ?? servicosDisponiveis[0];
            return {
                ...form,
                servicoId: servicoInicial?.id ?? 0,
                fim: addMinutesToTime(modalNova.hora, servicoInicial?.duracao_minutos ?? 30),
            };
        });
        if (tipoEntidade === 'profissional') {
            setNovoProfissionalId(entidadeId || profissionais[0]?.id || 0);
        }
        setErroReserva(null);
    }, [entidadeId, modalNova, profissionais, servicosDisponiveis, tipoEntidade]);

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
        if (!modalNova || (tipoEntidade === 'recurso' ? !entidadeId : !novoProfissionalId)) return;
        if (!telefoneValido) {
            setErroReserva('Informe um telefone válido com DDD, por exemplo: 54999999999.');
            return;
        }
        setSalvando(true);
        setErroReserva(null);
        const base = {
            cliente_nome:      novaForm.nome,
            cliente_telefone:  normalizePhone(novaForm.tel),
            inicio:            `${modalNova.data}T${modalNova.hora}:00`,
            fim:               novaForm.fim
                ? `${modalNova.data}T${novaForm.fim}:00`
                : `${modalNova.data}T${addMinutesToTime(modalNova.hora, 30)}:00`,
            observacoes:       novaForm.obs,
            servico_id:        novaForm.servicoId || undefined,
            notificar_cliente: false as boolean,
        };
        const payload = tipoEntidade === 'recurso'
            ? { ...base, recurso_id: entidadeId }
            : { ...base, profissional_id: novoProfissionalId };

        router.post(route('tenant.agendamentos.store'), payload, {
            onSuccess: () => {
                setModalNova(null);
                setNovaForm({ nome: '', tel: '', fim: '', obs: '', servicoId: 0 });
                setClientesEncontrados([]);
                setClienteSelecionadoId(null);
                carregar();
            },
            onError: errors => {
                const msg = Object.values(errors)[0] as string;
                setErroReserva(msg ?? 'Erro ao criar agendamento.');
            },
            onFinish: () => setSalvando(false),
        });
    };

    const criarBloqueio = () => {
        if (!modalBloqueio || !bloqueioEntidadeId) return;
        if (bloqueioForm.fim <= modalBloqueio.hora) {
            setErroBloqueio('O término deve ser posterior ao início.');
            return;
        }

        setSalvando(true);
        setErroBloqueio(null);
        const entidade = entidades.find(item => item.id === bloqueioEntidadeId);
        const payload = {
            inicio: `${modalBloqueio.data}T${modalBloqueio.hora}:00`,
            fim: `${modalBloqueio.data}T${bloqueioForm.fim}:00`,
            motivo: bloqueioForm.motivo,
            ...(entidade?.tipo === 'recurso'
                ? { recurso_id: bloqueioEntidadeId }
                : { profissional_id: bloqueioEntidadeId }),
        };

        router.post(route('tenant.agenda.bloqueios.store'), payload, {
            onSuccess: () => {
                setModalBloqueio(null);
                carregar();
            },
            onError: errors => setErroBloqueio((Object.values(errors)[0] as string) ?? 'Erro ao bloquear horário.'),
            onFinish: () => setSalvando(false),
        });
    };

    const removerBloqueio = (id: number) => {
        if (!window.confirm('Remover este bloqueio e liberar o horário?')) return;
        router.delete(route('tenant.agenda.bloqueios.destroy', id), {
            onSuccess: () => {
                setDetalheBloqueio(null);
                carregar();
            },
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
                            className="flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
                            style={{ color: 'var(--text-3)' }}
                        >
                            <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                        </button>
                        <p className="flex-1 text-center text-[13px] font-semibold text-primary">{fmtDiaLongo(diaAtivo)}</p>
                        <button
                            onClick={() => navegarDia(addDays(diaAtivo, 1))}
                            className="flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
                            style={{ color: 'var(--text-3)' }}
                        >
                            <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </button>
                    </div>

                    <div className="flex justify-end px-4 pb-3">
                        <button
                            onClick={() => abrirBloqueio(toISO(diaAtivo), '09:00')}
                            className="btn-secondary min-h-10 text-xs"
                        >
                            <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" />
                            </svg>
                            Bloquear horário
                        </button>
                    </div>

                    {/* Entity pills */}
                    {entidades.length > 1 && (
                        <div className="flex gap-2 overflow-x-auto scroll-hidden px-4 pb-3">
                            {temVisaoGeral && (
                                <button
                                    onClick={() => setEntidadeId(ALL_PROFESSIONALS_ID)}
                                    className="flex min-h-10 flex-shrink-0 items-center rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                                    style={{
                                        background: entidadeId === ALL_PROFESSIONALS_ID ? 'var(--jade)' : 'var(--bg-surface-2)',
                                        color: entidadeId === ALL_PROFESSIONALS_ID ? 'white' : 'var(--text-2)',
                                    }}
                                >
                                    Todos
                                </button>
                            )}
                            {entidades.map(e => (
                                <button
                                    key={e.id}
                                    onClick={() => setEntidadeId(e.id)}
                                    className="flex min-h-10 flex-shrink-0 items-center rounded-full px-3 py-1.5 text-xs font-medium transition-all"
                                    style={{
                                        background: entidadeId === e.id ? 'var(--jade)' : 'var(--bg-surface-2)',
                                        color: entidadeId === e.id ? 'white' : 'var(--text-2)',
                                    }}
                                >
                                    <span className="mr-1.5 h-2 w-2 rounded-full" style={{ background: e.cor }} />
                                    {e.nome}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {entidades.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 px-6 text-center">
                        <p className="font-medium text-primary">Prepare a agenda</p>
                        <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                            Cadastre profissionais ou recursos e defina os horários disponíveis.
                        </p>
                        <Link href={route('tenant.configuracoes.index')} className="btn-primary min-h-11">Configurar agenda</Link>
                    </div>
                ) : (
                    <>
                        <DayTimeline
                            dia={diaAtivo}
                            agendamentos={agendamentos}
                            onDetalhe={abrirDetalhe}
                            onNovo={hora => setModalNova({ data: toISO(diaAtivo), hora })}
                            loading={loading}
                        />
                        <button
                            onClick={() => setModalNova({ data: toISO(diaAtivo), hora: '09:00' })}
                            aria-label="Nova reserva"
                            className="fixed bottom-[max(1.5rem,env(safe-area-inset-bottom))] right-5 z-20 flex items-center justify-center rounded-full shadow-lg transition-transform hover:scale-105 active:scale-95"
                            style={{ width: 52, height: 52, background: 'var(--jade)', color: 'white' }}
                        >
                            <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                        </button>
                    </>
                )}
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
                            {temVisaoGeral && (
                                <button
                                    onClick={() => setEntidadeId(ALL_PROFESSIONALS_ID)}
                                    className="mb-0.5 flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left transition-all"
                                    style={{
                                        background: entidadeId === ALL_PROFESSIONALS_ID ? 'var(--jade-light)' : 'transparent',
                                        color: entidadeId === ALL_PROFESSIONALS_ID ? 'var(--jade)' : 'var(--text-2)',
                                        fontSize: 13,
                                        fontWeight: entidadeId === ALL_PROFESSIONALS_ID ? 500 : 400,
                                    }}
                                >
                                    <span className="flex h-3 w-3 items-center justify-center">
                                        <span className="h-2 w-2 rounded-full bg-[var(--jade)]" />
                                    </span>
                                    Visão geral
                                </button>
                            )}
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
                                        style={{ background: e.cor }}
                                    />
                                    {e.nome}
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Legend */}
                    <div style={{ borderTop: '1px solid var(--border)', padding: '12px 10px 8px', marginTop: 'auto' }}>
                        <p className="label px-2 mb-2">Legenda</p>
                        {(tipoEntidade === 'profissional'
                            ? entidades.map(e => ({ accent: e.cor, label: e.nome }))
                            : [
                                { accent: '#34d399', label: 'WhatsApp' },
                                { accent: '#60a5fa', label: 'Manual' },
                                { accent: '#6b7280', label: 'Concluído' },
                                { accent: '#f87171', label: 'Cancelado' },
                            ]
                        ).map(l => (
                            <div key={l.label} className="flex items-center gap-2 px-2 py-0.5">
                                <span className="h-2.5 w-2.5 flex-shrink-0 rounded-sm" style={{ background: l.accent, opacity: 0.85 }} />
                                <span style={{ fontSize: 11, color: 'var(--text-3)' }}>{l.label}</span>
                            </div>
                        ))}
                        <div className="mt-1 flex items-center gap-2 px-2 py-0.5">
                            <span className="h-2.5 w-2.5 flex-shrink-0 rounded-sm" style={{ background: '#94a3b8', opacity: 0.85 }} />
                            <span style={{ fontSize: 11, color: 'var(--text-3)' }}>Bloqueado</span>
                        </div>
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
                            className="flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
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
                            className="flex h-10 w-10 items-center justify-center rounded-lg transition-colors hover:bg-[var(--bg-surface-2)]"
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
                            onClick={() => abrirBloqueio(toISO(diaAtivo), '09:00')}
                            className="btn-secondary text-sm"
                        >
                            <svg width={12} height={12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                                <rect x="5" y="10" width="14" height="10" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3" />
                            </svg>
                            Bloquear
                        </button>

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
                                Cadastre quem atende e os horários disponíveis.
                            </p>
                            <Link href={route('tenant.profissionais.index')} className="btn-primary mt-2 min-h-11">
                                Criar primeiro profissional
                            </Link>
                        </div>
                    ) : (
                        <WeekGrid
                            dias={dias}
                            agendamentos={agendamentos}
                            loading={false}
                            onSlotClick={(data, hora) => setModalNova({ data, hora })}
                            onDetalhe={abrirDetalhe}
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

            {detalheBloqueio && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 backdrop-blur-sm sm:items-center sm:px-4">
                    <div className="w-full max-w-sm rounded-t-2xl p-6 shadow-2xl sm:rounded-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                        <div className="mb-4 flex items-start justify-between">
                            <div>
                                <p className="label mb-1">Horário bloqueado</p>
                                <h3 className="text-xl font-semibold text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                                    {detalheBloqueio.title}
                                </h3>
                            </div>
                            <button onClick={() => setDetalheBloqueio(null)} className="flex h-8 w-8 items-center justify-center rounded-full hover:bg-[var(--bg-surface-2)]" style={{ color: 'var(--text-3)' }}>
                                <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M18 6L6 18M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div className="rounded-xl px-4 py-3 text-sm" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-2)' }}>
                            <p>{new Date(detalheBloqueio.start).toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', timeZone: 'America/Sao_Paulo' })}</p>
                            <p className="mt-1 font-medium">{fmtHora(detalheBloqueio.start)} – {fmtHora(detalheBloqueio.end)}</p>
                            {detalheBloqueio.profissional_nome && <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>{detalheBloqueio.profissional_nome}</p>}
                        </div>
                        <div className="mt-5 flex gap-2">
                            <button onClick={() => setDetalheBloqueio(null)} className="btn-secondary flex-1 justify-center">Fechar</button>
                            <button onClick={() => removerBloqueio(detalheBloqueio.id)} className="flex-1 justify-center rounded-lg px-4 py-2 text-sm font-medium" style={{ background: 'rgba(239,68,68,0.12)', color: '#f87171' }}>
                                Remover bloqueio
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {modalBloqueio && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 backdrop-blur-sm sm:items-center sm:px-4">
                    <div className="w-full max-w-sm rounded-t-2xl shadow-2xl sm:rounded-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                        <div className="p-6">
                            <div className="mb-4 flex items-start justify-between">
                                <div>
                                    <p className="label mb-1">Indisponibilidade</p>
                                    <h3 className="text-xl font-semibold text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>Bloquear horário</h3>
                                </div>
                                <button onClick={() => setModalBloqueio(null)} className="flex h-8 w-8 items-center justify-center rounded-full hover:bg-[var(--bg-surface-2)]" style={{ color: 'var(--text-3)' }}>
                                    <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M18 6L6 18M6 6l12 12" /></svg>
                                </button>
                            </div>
                            <div className="space-y-3">
                                <p className="rounded-xl px-3 py-2 text-sm" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-2)' }}>
                                    {new Date(modalBloqueio.data + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' })}
                                </p>
                                <div>
                                    <label className="label mb-1">{tipoEntidade === 'recurso' ? 'Recurso' : 'Profissional'}</label>
                                    <select value={bloqueioEntidadeId} onChange={e => setBloqueioEntidadeId(Number(e.target.value))} className="input">
                                        {entidades.map(entidade => <option key={`${entidade.tipo}-${entidade.id}`} value={entidade.id}>{entidade.nome}</option>)}
                                    </select>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="label mb-1">Início</label>
                                        <input
                                            type="time"
                                            value={modalBloqueio.hora}
                                            onChange={e => setModalBloqueio(current => current ? { ...current, hora: e.target.value } : current)}
                                            className="input"
                                        />
                                    </div>
                                    <div>
                                        <label className="label mb-1">Término</label>
                                        <input type="time" value={bloqueioForm.fim} onChange={e => setBloqueioForm(form => ({ ...form, fim: e.target.value }))} className="input" />
                                    </div>
                                </div>
                                <div>
                                    <label className="label mb-1">Motivo</label>
                                    <input value={bloqueioForm.motivo} onChange={e => setBloqueioForm(form => ({ ...form, motivo: e.target.value }))} maxLength={120} className="input" placeholder="Ex.: almoço, reunião ou folga" />
                                </div>
                            </div>
                            {erroBloqueio && <p className="mt-3 rounded-lg px-3 py-2 text-sm" style={{ background: 'rgba(239,68,68,0.1)', color: '#f87171' }}>{erroBloqueio}</p>}
                            <div className="mt-5 flex gap-2">
                                <button onClick={() => setModalBloqueio(null)} className="btn-secondary flex-1 justify-center">Cancelar</button>
                                <button onClick={criarBloqueio} disabled={salvando || !bloqueioEntidadeId} className="btn-primary flex-1 justify-center">{salvando ? 'Salvando…' : 'Bloquear'}</button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* ── New booking modal ── */}
            {modalNova && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 backdrop-blur-sm sm:items-center sm:px-4">
                    <div
                        className="max-h-[92dvh] w-full max-w-sm overflow-y-auto rounded-t-2xl shadow-2xl sm:rounded-2xl"
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
                                {tipoEntidade === 'profissional' && (
                                    <div>
                                        <label className="label mb-1">Profissional</label>
                                        <select
                                            value={novoProfissionalId}
                                            onChange={e => atualizarProfissional(Number(e.target.value))}
                                            className="input"
                                        >
                                            {profissionais.map(profissional => (
                                                <option key={profissional.id} value={profissional.id}>
                                                    {profissional.nome}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                {servicosDisponiveis.length > 0 ? (
                                    <div>
                                        <label className="label mb-1">Serviço</label>
                                        <select
                                            value={novaForm.servicoId}
                                            onChange={e => atualizarServico(Number(e.target.value))}
                                            className="input"
                                        >
                                            {servicosDisponiveis.map(servico => (
                                                <option key={servico.id} value={servico.id}>
                                                    {servico.nome} — {descricaoServico(servico)}
                                                </option>
                                            ))}
                                        </select>
                                        <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                            O término é calculado pela duração do serviço.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="rounded-xl px-3 py-2.5 text-xs" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)', border: '1px solid var(--border)' }}>
                                        Nenhum serviço disponível para este profissional. A reserva será criada sem serviço, com 30 minutos.
                                    </div>
                                )}
                                <div className="relative">
                                    <label className="label mb-1">Cliente</label>
                                    <div className="relative">
                                        <input
                                            autoFocus
                                            value={novaForm.nome}
                                            onChange={event => {
                                                const nome = event.target.value;
                                                setNovaForm(form => ({ ...form, nome }));
                                                pesquisarCliente(nome);
                                            }}
                                            className="input pr-9"
                                            placeholder="Digite o nome para buscar"
                                            autoComplete="off"
                                        />
                                        {buscandoCliente && (
                                            <svg className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin" viewBox="0 0 24 24" fill="none" style={{ color: 'var(--text-3)' }} aria-label="Buscando cliente">
                                                <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="3" opacity=".25" />
                                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
                                            </svg>
                                        )}
                                    </div>
                                    {clientesEncontrados.length > 0 && (
                                        <div className="absolute z-20 mt-1 max-h-52 w-full overflow-y-auto rounded-xl p-1 shadow-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                                            {clientesEncontrados.map(cliente => (
                                                <button
                                                    key={cliente.id}
                                                    type="button"
                                                    onClick={() => selecionarCliente(cliente)}
                                                    className="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-left transition-colors hover:bg-[var(--bg-surface-2)]"
                                                >
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-medium text-primary">{cliente.nome}</span>
                                                        <span className="block text-xs" style={{ color: 'var(--text-3)' }}>{cliente.telefone}</span>
                                                    </span>
                                                    <span className="shrink-0 text-[10px]" style={{ color: 'var(--text-3)' }}>
                                                        {cliente.agendamentos_count} ag.
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                    {clienteSelecionadoId && (
                                        <p className="mt-1 text-xs" style={{ color: 'var(--jade)' }}>
                                            Cliente existente selecionado. Nome e telefone foram preenchidos automaticamente.
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <label className="label mb-1">Telefone</label>
                                    <input
                                        value={novaForm.tel}
                                        onChange={event => {
                                            const tel = normalizePhone(event.target.value);
                                            setNovaForm(form => ({ ...form, tel }));
                                            pesquisarCliente(tel);
                                        }}
                                        className="input"
                                        inputMode="tel"
                                        autoComplete="tel"
                                        maxLength={13}
                                        aria-invalid={Boolean(novaForm.tel) && !telefoneValido}
                                        placeholder="54999999999"
                                    />
                                    {novaForm.tel && !telefoneValido && (
                                        <p className="mt-1 text-xs" style={{ color: '#f87171' }}>
                                            Informe o telefone com DDD, por exemplo: 54999999999.
                                        </p>
                                    )}
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
                                    disabled={salvando || !novaForm.nome || !telefoneValido || (tipoEntidade === 'profissional' && !novoProfissionalId) || (servicosDisponiveis.length > 0 && !novaForm.servicoId)}
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
