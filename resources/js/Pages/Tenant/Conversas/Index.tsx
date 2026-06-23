import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Cliente {
    id: number;
    nome: string;
    telefone: string;
}

interface Conversa {
    id: number;
    cliente: Cliente | null;
    telefone_cliente: string;
    status_v2: 'ativa' | 'aguardando_humano' | 'em_atendimento_humano' | 'encerrada';
    ultima_mensagem_em: string | null;
}

interface Mensagem {
    id: number;
    remetente: 'cliente' | 'bot' | 'humano';
    conteudo: string;
    enviada_em: string;
}

interface Props extends PageProps {
    conversas: { data: Conversa[] };
    filtros: { status_v2?: string };
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const STATUS_LABEL: Record<string, string> = {
    ativa:                 'Ativa',
    aguardando_humano:     'Aguardando',
    em_atendimento_humano: 'Em atendimento',
    encerrada:             'Encerrada',
};

const STATUS_COLOR: Record<string, { bg: string; color: string }> = {
    ativa:                 { bg: 'rgba(110,231,183,0.12)', color: '#6ee7b7' },
    aguardando_humano:     { bg: 'rgba(251,191,36,0.12)',  color: '#fbbf24' },
    em_atendimento_humano: { bg: 'rgba(99,102,241,0.15)',  color: '#a5b4fc' },
    encerrada:             { bg: 'rgba(255,255,255,0.05)', color: 'var(--text-3)' },
};

const REMETENTE_LABEL: Record<string, string> = {
    bot:    '🤖 Bot',
    humano: '🧑 Atendente',
};

function fmtHora(iso: string) {
    return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function fmtRelativo(iso: string | null) {
    if (!iso) return '';
    const d = new Date(iso);
    const agora = new Date();
    const diffMin = Math.floor((agora.getTime() - d.getTime()) / 60000);
    if (diffMin < 1)  return 'agora';
    if (diffMin < 60) return `${diffMin}m`;
    const diffH = Math.floor(diffMin / 60);
    if (diffH < 24)   return `${diffH}h`;
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

// ─── Status Badge ─────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: string }) {
    const s = STATUS_COLOR[status] ?? { bg: 'rgba(255,255,255,0.05)', color: 'var(--text-3)' };
    return (
        <span
            className="rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap"
            style={{ background: s.bg, color: s.color }}
        >
            {STATUS_LABEL[status] ?? status}
        </span>
    );
}

// ─── Bubble de mensagem ───────────────────────────────────────────────────────

function Bubble({ msg }: { msg: Mensagem }) {
    const isCliente = msg.remetente === 'cliente';

    const bubbleStyle: React.CSSProperties = isCliente
        ? { background: 'var(--bg-surface)', border: '1px solid var(--border)', color: 'var(--text-1)' }
        : msg.remetente === 'bot'
            ? { background: 'rgba(99,102,241,0.18)', border: '1px solid rgba(99,102,241,0.25)', color: 'var(--text-1)' }
            : { background: 'rgba(110,231,183,0.15)', border: '1px solid rgba(110,231,183,0.25)', color: 'var(--text-1)' };

    return (
        <div className={`flex ${isCliente ? 'justify-start' : 'justify-end'} mb-2`}>
            <div
                className="max-w-xs rounded-2xl px-3.5 py-2.5 text-sm lg:max-w-sm"
                style={bubbleStyle}
            >
                {!isCliente && (
                    <p className="mb-1 text-[10px] font-medium opacity-60">
                        {REMETENTE_LABEL[msg.remetente]}
                    </p>
                )}
                <p style={{ whiteSpace: 'pre-wrap', lineHeight: 1.5 }}>{msg.conteudo}</p>
                <p className="mt-1 text-right text-[10px] opacity-50">{fmtHora(msg.enviada_em)}</p>
            </div>
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export default function ConversasIndex({ conversas, filtros }: Props) {
    const [selecionada, setSelecionada] = useState<Conversa | null>(null);
    const [mensagens,   setMensagens]   = useState<Mensagem[]>([]);
    const [carregando,  setCarregando]  = useState(false);

    const chatRef     = useRef<HTMLDivElement>(null);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const { data, setData, post, processing, reset } = useForm<{ conteudo: string }>({ conteudo: '' });

    // ── Scroll to bottom ──────────────────────────────────────────────────────
    const scrollBottom = () => {
        setTimeout(() => {
            if (chatRef.current) {
                chatRef.current.scrollTop = chatRef.current.scrollHeight;
            }
        }, 60);
    };

    // ── Fetch mensagens ───────────────────────────────────────────────────────
    const buscarMensagens = useCallback(async (conversa: Conversa, silent = false) => {
        if (!silent) setCarregando(true);
        try {
            const res = await fetch(route('tenant.conversas.mensagens', conversa.id), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (!res.ok) return;
            const json: Mensagem[] = await res.json();
            setMensagens(json);
            if (!silent) scrollBottom();
        } catch { /* ignore network errors */ } finally {
            if (!silent) setCarregando(false);
        }
    }, []);

    // ── Iniciar/parar polling ─────────────────────────────────────────────────
    const pararPolling = () => {
        if (intervalRef.current) {
            clearInterval(intervalRef.current);
            intervalRef.current = null;
        }
    };

    const iniciarPolling = useCallback((conversa: Conversa) => {
        pararPolling();
        intervalRef.current = setInterval(() => {
            buscarMensagens(conversa, true);
        }, 5000);
    }, [buscarMensagens]);

    // ── Selecionar conversa ───────────────────────────────────────────────────
    const selecionar = (c: Conversa) => {
        pararPolling();
        setSelecionada(c);
        setMensagens([]);
        buscarMensagens(c).then(() => scrollBottom());
        iniciarPolling(c);
    };

    // ── Cleanup ao desmontar ──────────────────────────────────────────────────
    useEffect(() => () => pararPolling(), []);

    // ── Assumir / devolver ────────────────────────────────────────────────────
    const assumir = () => {
        if (!selecionada) return;
        router.post(route('tenant.conversas.assumir', selecionada.id), {}, {
            preserveScroll: true,
            onSuccess: () => setSelecionada(prev =>
                prev ? { ...prev, status_v2: 'em_atendimento_humano' } : null
            ),
        });
    };

    const devolver = () => {
        if (!selecionada) return;
        router.post(route('tenant.conversas.devolver', selecionada.id), {}, {
            preserveScroll: true,
            onSuccess: () => setSelecionada(prev =>
                prev ? { ...prev, status_v2: 'ativa' } : null
            ),
        });
    };

    // ── Enviar mensagem humana ────────────────────────────────────────────────
    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selecionada || !data.conteudo.trim()) return;
        post(route('tenant.conversas.enviar', selecionada.id), {
            preserveScroll: true,
            onSuccess: () => {
                reset('conteudo');
                buscarMensagens(selecionada).then(() => scrollBottom());
            },
        });
    };

    // ── Filtrar por status ────────────────────────────────────────────────────
    const filtrarStatus = (status: string) => {
        router.get(route('tenant.conversas.index'), status ? { status_v2: status } : {}, { preserveState: true });
    };

    // ── Nome de exibição ──────────────────────────────────────────────────────
    const nomeConversa = (c: Conversa) => c.cliente?.nome ?? c.telefone_cliente;

    const statusFiltros: { label: string; value: string }[] = [
        { label: 'Todas',          value: '' },
        { label: 'Ativas',         value: 'ativa' },
        { label: 'Aguardando',     value: 'aguardando_humano' },
        { label: 'Em atendimento', value: 'em_atendimento_humano' },
        { label: 'Encerradas',     value: 'encerrada' },
    ];

    return (
        <AppLayout title="">
            <Head title="Conversas WhatsApp" />

            {/* Layout two-panel */}
            <div
                className="flex overflow-hidden rounded-xl"
                style={{
                    height: 'calc(100vh - 120px)',
                    border: '1px solid var(--border)',
                    background: 'var(--bg-surface)',
                }}
            >
                {/* ── Left: lista de conversas ────────────────────────────── */}
                <div
                    className="flex w-72 flex-shrink-0 flex-col"
                    style={{ borderRight: '1px solid var(--border)' }}
                >
                    {/* Header lista */}
                    <div
                        className="px-4 py-4"
                        style={{ borderBottom: '1px solid var(--border)' }}
                    >
                        <h2
                            className="text-base font-semibold text-white"
                            style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}
                        >
                            Conversas WhatsApp
                        </h2>

                        {/* Filtros de status */}
                        <div className="mt-3 flex flex-wrap gap-1">
                            {statusFiltros.map(f => {
                                const ativo = (filtros.status_v2 ?? '') === f.value;
                                return (
                                    <button
                                        key={f.value}
                                        onClick={() => filtrarStatus(f.value)}
                                        className="rounded-full px-2.5 py-1 text-[10px] font-medium transition-colors"
                                        style={ativo
                                            ? { background: 'var(--accent)', color: 'white' }
                                            : { background: 'rgba(255,255,255,0.05)', color: 'var(--text-3)' }
                                        }
                                    >
                                        {f.label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Lista */}
                    <div className="flex-1 overflow-y-auto">
                        {conversas.data.length === 0 ? (
                            <div className="flex h-full items-center justify-center">
                                <p className="text-center text-xs" style={{ color: 'var(--text-3)' }}>
                                    Nenhuma conversa encontrada.
                                </p>
                            </div>
                        ) : conversas.data.map(c => {
                            const ativo = selecionada?.id === c.id;
                            return (
                                <button
                                    key={c.id}
                                    onClick={() => selecionar(c)}
                                    className="w-full px-4 py-3 text-left transition-colors"
                                    style={{
                                        borderBottom: '1px solid var(--border)',
                                        background: ativo ? 'var(--accent-light)' : 'transparent',
                                        borderLeft: ativo ? '2px solid var(--accent)' : '2px solid transparent',
                                    }}
                                    onMouseEnter={e => { if (!ativo) (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.03)'; }}
                                    onMouseLeave={e => { if (!ativo) (e.currentTarget as HTMLElement).style.background = 'transparent'; }}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-white">
                                                {nomeConversa(c)}
                                            </p>
                                            <p className="truncate text-[11px]" style={{ color: 'var(--text-3)' }}>
                                                {c.telefone_cliente}
                                            </p>
                                        </div>
                                        <div className="flex flex-col items-end gap-1 flex-shrink-0">
                                            <span className="text-[10px]" style={{ color: 'var(--text-3)' }}>
                                                {fmtRelativo(c.ultima_mensagem_em)}
                                            </span>
                                            <StatusBadge status={c.status_v2} />
                                        </div>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* ── Right: área do chat ──────────────────────────────────── */}
                {selecionada ? (
                    <div className="flex flex-1 flex-col min-w-0">

                        {/* Chat header */}
                        <div
                            className="flex items-center justify-between px-5 py-3.5 flex-shrink-0"
                            style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface)' }}
                        >
                            <div>
                                <p className="font-semibold text-white">{nomeConversa(selecionada)}</p>
                                <div className="flex items-center gap-2 mt-0.5">
                                    <p className="text-xs" style={{ color: 'var(--text-3)' }}>
                                        {selecionada.telefone_cliente}
                                    </p>
                                    <StatusBadge status={selecionada.status_v2} />
                                </div>
                            </div>

                            {/* Ações */}
                            <div className="flex gap-2">
                                {selecionada.status_v2 === 'aguardando_humano' && (
                                    <button
                                        onClick={assumir}
                                        className="rounded-lg px-3.5 py-1.5 text-xs font-semibold text-white transition-opacity hover:opacity-80"
                                        style={{ background: 'var(--accent)' }}
                                    >
                                        Assumir atendimento
                                    </button>
                                )}
                                {selecionada.status_v2 === 'em_atendimento_humano' && (
                                    <button
                                        onClick={devolver}
                                        className="rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-colors"
                                        style={{
                                            background: 'rgba(110,231,183,0.1)',
                                            border: '1px solid rgba(110,231,183,0.25)',
                                            color: '#6ee7b7',
                                        }}
                                    >
                                        Devolver ao bot
                                    </button>
                                )}
                            </div>
                        </div>

                        {/* Mensagens */}
                        <div
                            ref={chatRef}
                            className="flex-1 overflow-y-auto px-5 py-4"
                            style={{ background: 'var(--bg-app)' }}
                        >
                            {carregando ? (
                                <div className="flex h-full items-center justify-center">
                                    <p className="text-sm" style={{ color: 'var(--text-3)' }}>Carregando mensagens…</p>
                                </div>
                            ) : mensagens.length === 0 ? (
                                <div className="flex h-full items-center justify-center">
                                    <p className="text-sm" style={{ color: 'var(--text-3)' }}>Nenhuma mensagem ainda.</p>
                                </div>
                            ) : (
                                mensagens.map(m => <Bubble key={m.id} msg={m} />)
                            )}
                        </div>

                        {/* Input */}
                        {selecionada.status_v2 === 'em_atendimento_humano' ? (
                            <form
                                onSubmit={enviar}
                                className="flex items-end gap-2 px-4 py-3 flex-shrink-0"
                                style={{ borderTop: '1px solid var(--border)', background: 'var(--bg-surface)' }}
                            >
                                <textarea
                                    value={data.conteudo}
                                    onChange={e => setData('conteudo', e.target.value)}
                                    onKeyDown={e => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            enviar(e as unknown as React.FormEvent);
                                        }
                                    }}
                                    placeholder="Digite uma mensagem… (Enter para enviar)"
                                    rows={2}
                                    className="flex-1 resize-none rounded-xl px-3.5 py-2.5 text-sm focus:outline-none"
                                    style={{
                                        background: 'var(--bg-app)',
                                        border: '1px solid var(--border)',
                                        color: 'var(--text-1)',
                                    }}
                                />
                                <button
                                    type="submit"
                                    disabled={processing || !data.conteudo.trim()}
                                    className="flex-shrink-0 rounded-xl px-4 py-2.5 text-sm font-semibold text-white transition-opacity disabled:opacity-40"
                                    style={{ background: 'var(--accent)' }}
                                >
                                    {processing ? '…' : 'Enviar'}
                                </button>
                            </form>
                        ) : (
                            <div
                                className="flex-shrink-0 px-5 py-3 text-center text-xs"
                                style={{
                                    borderTop: '1px solid var(--border)',
                                    background: 'var(--bg-surface)',
                                    color: 'var(--text-3)',
                                }}
                            >
                                {selecionada.status_v2 === 'aguardando_humano'
                                    ? 'Assuma o atendimento para enviar mensagens'
                                    : selecionada.status_v2 === 'encerrada'
                                        ? 'Conversa encerrada'
                                        : 'O bot está gerenciando esta conversa'}
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="flex flex-1 items-center justify-center" style={{ background: 'var(--bg-app)' }}>
                        <div className="text-center">
                            <p className="text-3xl mb-2" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-2)' }}>
                                Conversas
                            </p>
                            <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                                Selecione uma conversa para ver o histórico
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
