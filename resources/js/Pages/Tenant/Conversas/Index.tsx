import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { useNotificacoes } from '@/hooks/useNotificacoes';

// ─── Types ────────────────────────────────────────────────────────────────────

interface Cliente {
    id: number;
    nome: string;
    telefone: string;
}

type TipoMensagem = 'texto' | 'imagem' | 'audio' | 'video' | 'documento' | 'sticker';

interface Mensagem {
    id: number;
    conversa_id: number;
    remetente: 'cliente' | 'bot' | 'humano';
    tipo: TipoMensagem;
    conteudo: string;
    evolution_message_id: string | null;
    enviada_em: string;
}

interface Conversa {
    id: number;
    cliente: Cliente | null;
    telefone_cliente: string;
    status_v2: 'ativa' | 'aguardando_humano' | 'em_atendimento_humano' | 'encerrada';
    ultima_mensagem_em: string | null;
    mensagens?: Mensagem[];
}

interface Props extends PageProps {
    conversas: { data: Conversa[] };
    filtros: { status_v2?: string };
}

interface SyncStatus {
    status: 'idle' | 'queued' | 'running' | 'completed' | 'failed';
    processed?: number;
    total?: number;
    imported?: number;
    ignored?: number;
    errors?: number;
    removed?: number;
    message?: string;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const STATUS_LABEL: Record<string, string> = {
    ativa:                 'Ativa',
    aguardando_humano:     'Aguardando equipe',
    em_atendimento_humano: 'Em atendimento',
    encerrada:             'Encerrada',
};

const STATUS_DOT: Record<string, string> = {
    ativa:                 '#34d399',
    aguardando_humano:     '#fbbf24',
    em_atendimento_humano: '#a5b4fc',
    encerrada:             'var(--text-3)',
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

// ─── Avatar ───────────────────────────────────────────────────────────────────

const AVATAR_PALETTES = [
    { bg: 'rgba(99,102,241,0.22)',  color: '#a5b4fc' },
    { bg: 'rgba(0,168,132,0.20)',   color: '#34d399' },
    { bg: 'rgba(251,191,36,0.20)',  color: '#fbbf24' },
    { bg: 'rgba(239,68,68,0.18)',   color: '#f87171' },
    { bg: 'rgba(168,85,247,0.20)',  color: '#c084fc' },
    { bg: 'rgba(59,130,246,0.20)',  color: '#93c5fd' },
];

function Avatar({ name, size = 'md' }: { name: string; size?: 'sm' | 'md' | 'lg' }) {
    const letter = name.charAt(0).toUpperCase();
    const palette = AVATAR_PALETTES[name.charCodeAt(0) % AVATAR_PALETTES.length];
    const dim = size === 'sm' ? 'h-8 w-8 text-[12px]' : size === 'lg' ? 'h-11 w-11 text-base' : 'h-10 w-10 text-sm';
    return (
        <div
            className={`flex shrink-0 items-center justify-center rounded-full font-semibold ${dim}`}
            style={{ background: palette.bg, color: palette.color }}
        >
            {letter}
        </div>
    );
}

// ─── Status Badge ─────────────────────────────────────────────────────────────

function StatusBadge({ status }: { status: string }) {
    const dot = STATUS_DOT[status] ?? 'var(--text-3)';
    return (
        <span className="flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium whitespace-nowrap" style={{ background: 'var(--bg-surface-2)', color: dot }}>
            <span className="h-1.5 w-1.5 rounded-full shrink-0" style={{ background: dot }} />
            {STATUS_LABEL[status] ?? status}
        </span>
    );
}

// ─── Bubble ───────────────────────────────────────────────────────────────────

function Bubble({ msg, prevRemetente }: { msg: Mensagem; prevRemetente?: string }) {
    const isCliente = msg.remetente === 'cliente';
    const isBot = msg.remetente === 'bot';
    const showLabel = !isCliente && msg.remetente !== prevRemetente;

    let bg: string, border: string, radius: string, labelColor: string;
    if (isCliente) {
        bg = 'var(--bg-surface)';
        border = '1px solid var(--border-strong)';
        radius = '4px 18px 18px 18px';
        labelColor = '';
    } else if (isBot) {
        bg = 'rgba(99,102,241,0.15)';
        border = '1px solid rgba(99,102,241,0.22)';
        radius = '18px 4px 18px 18px';
        labelColor = 'rgba(165,180,252,0.85)';
    } else {
        bg = 'rgba(0,168,132,0.14)';
        border = '1px solid rgba(0,168,132,0.22)';
        radius = '18px 4px 18px 18px';
        labelColor = 'rgba(52,211,153,0.85)';
    }

    const mediaUrl = (msg.tipo !== 'texto' && msg.evolution_message_id)
        ? route('tenant.conversas.media', { conversa: msg.conversa_id, mensagem: msg.id })
        : null;

    return (
        <div className={`flex ${isCliente ? 'justify-start' : 'justify-end'} ${showLabel ? 'mt-3' : 'mt-1'}`}>
            <div
                className="max-w-[88%] text-sm shadow-sm sm:max-w-[75%] lg:max-w-[60%]"
                style={{ background: bg, border, borderRadius: radius, color: 'var(--text-1)', wordBreak: 'break-word', overflow: 'hidden' }}
            >
                {showLabel && (
                    <p className="px-3.5 pt-2.5 mb-1 text-[10px] font-semibold" style={{ color: labelColor }}>
                        {isBot ? 'Bot' : 'Atendente'}
                    </p>
                )}

                {/* Imagem */}
                {msg.tipo === 'imagem' && mediaUrl && (
                    <img
                        src={mediaUrl}
                        alt={msg.conteudo || 'imagem'}
                        loading="lazy"
                        className="block w-full max-w-xs object-cover cursor-pointer"
                        style={{ maxHeight: '280px' }}
                        onClick={() => window.open(mediaUrl, '_blank')}
                    />
                )}

                {/* Áudio */}
                {msg.tipo === 'audio' && mediaUrl && (
                    <div className="px-3.5 pt-2.5">
                        <audio controls src={mediaUrl} className="w-full max-w-[260px]" style={{ height: '36px' }} />
                    </div>
                )}

                {/* Vídeo */}
                {msg.tipo === 'video' && mediaUrl && (
                    <video controls src={mediaUrl} className="block w-full max-w-xs" style={{ maxHeight: '280px' }} />
                )}

                {/* Documento / Sticker / Fallback */}
                {(msg.tipo === 'documento' || msg.tipo === 'sticker' || (!mediaUrl && msg.tipo !== 'texto')) && (
                    <div className="px-3.5 py-2.5 flex items-center gap-2">
                        <span className="text-[11px] font-medium" style={{ color: 'var(--text-3)' }}>
                            {msg.tipo === 'documento' ? 'Documento' : msg.tipo === 'sticker' ? 'Figurinha' : 'Anexo'}
                        </span>
                        <span style={{ color: 'var(--text-2)', fontSize: '12px' }}>
                            {msg.conteudo || msg.tipo}
                        </span>
                        {mediaUrl && (
                            <a href={mediaUrl} target="_blank" rel="noreferrer" style={{ color: 'var(--accent)', fontSize: '11px' }}>
                                Baixar
                            </a>
                        )}
                    </div>
                )}

                {/* Texto (legenda ou conteúdo normal) */}
                {(msg.tipo === 'texto' || msg.conteudo) && (
                    <p className="px-3.5 py-2.5" style={{ whiteSpace: 'pre-wrap', lineHeight: 1.55, paddingTop: msg.tipo !== 'texto' && msg.conteudo ? '4px' : undefined }}>
                        {msg.conteudo}
                    </p>
                )}

                <p className="px-3.5 pb-2 text-right text-[10px] opacity-40">{fmtHora(msg.enviada_em)}</p>
            </div>
        </div>
    );
}

// ─── Send icon ────────────────────────────────────────────────────────────────

function SendIcon() {
    return (
        <svg width={18} height={18} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="22" y1="2" x2="11" y2="13"/>
            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
        </svg>
    );
}

// ─── Modal Nova Conversa ──────────────────────────────────────────────────────

function NovaConversaModal({ onClose, initialTelefone = '' }: { onClose: () => void; initialTelefone?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        telefone: initialTelefone,
        mensagem: '',
    });

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.conversas.iniciar'), {
            onSuccess: () => { reset(); onClose(); },
        });
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 backdrop-blur-sm sm:items-center sm:px-4"
            role="dialog"
            aria-modal="true"
            onClick={e => { if (e.target === e.currentTarget) onClose(); }}
        >
            <div
                className="max-h-[92dvh] w-full max-w-sm overflow-y-auto rounded-t-2xl p-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] shadow-2xl sm:rounded-2xl sm:p-6"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}
            >
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                        Nova conversa
                    </h3>
                    <button
                        onClick={onClose}
                        style={{ color: 'var(--text-3)' }}
                        className="hover:text-primary transition-colors text-lg leading-none"
                    >
                        ✕
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-3">
                    <div>
                        <label className="label mb-1">Telefone (com DDD e código do país)</label>
                        <input
                            type="tel"
                            value={data.telefone}
                            onChange={e => setData('telefone', e.target.value)}
                            placeholder="Ex: 5549999999999"
                            className="input"
                            autoFocus
                        />
                        {errors.telefone && <p className="mt-1 text-xs text-red-400">{errors.telefone}</p>}
                    </div>
                    <div>
                        <label className="label mb-1">Mensagem</label>
                        <textarea
                            value={data.mensagem}
                            onChange={e => setData('mensagem', e.target.value)}
                            placeholder="Olá! Tudo bem?"
                            rows={3}
                            className="input resize-none"
                        />
                        {errors.mensagem && <p className="mt-1 text-xs text-red-400">{errors.mensagem}</p>}
                    </div>
                    <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                        <button type="button" onClick={onClose} className="btn-secondary">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={processing || !data.telefone.trim() || !data.mensagem.trim()}
                            className="btn-primary"
                        >
                            {processing ? 'Enviando…' : 'Enviar'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────

export default function ConversasIndex({ conversas, filtros }: Props) {
    const [selecionada,    setSelecionada]    = useState<Conversa | null>(null);
    const [mensagens,      setMensagens]      = useState<Mensagem[]>([]);
    const [carregando,     setCarregando]     = useState(false);
    const [assumindo,      setAssumindo]      = useState(false);
    const [showChat,       setShowChat]       = useState(false);
    const [showModalNova,  setShowModalNova]  = useState(false);
    const [sincronizando,  setSincronizando]  = useState(false);
    const [syncStatus, setSyncStatus] = useState<SyncStatus | null>(null);

    useEffect(() => {
        if (new URLSearchParams(window.location.search).get('nova') === '1') {
            setShowModalNova(true);
        }
    }, []);
    const [busca,          setBusca]          = useState('');
    const buscaRef = useRef<HTMLInputElement>(null);

    const chatRef      = useRef<HTMLDivElement>(null);
    const intervalRef  = useRef<ReturnType<typeof setInterval> | null>(null);
    const syncRef      = useRef<ReturnType<typeof setInterval> | null>(null);
    const syncWasActiveRef = useRef(false);

    const { data, setData, post, processing, reset } = useForm<{ conteudo: string }>({ conteudo: '' });

    // Mantém a lista de conversas atualizada sem exigir reload manual: sempre que o
    // contador de não lidas mudar (nova mensagem ou conversa nova), recarrega a lista.
    const { novaMensagem, resetarNovaMensagem } = useNotificacoes(true);
    useEffect(() => {
        if (!novaMensagem) return;
        router.reload({ only: ['conversas'] });
        resetarNovaMensagem();
    }, [novaMensagem, resetarNovaMensagem]);

    const scrollBottom = () => {
        setTimeout(() => { if (chatRef.current) chatRef.current.scrollTop = chatRef.current.scrollHeight; }, 60);
    };

    const buscarMensagens = useCallback(async (conversa: Conversa, silent = false) => {
        if (!silent) setCarregando(true);
        try {
            const res = await fetch(route('tenant.conversas.mensagens', conversa.id), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            });
            if (!res.ok) return;
            setMensagens(await res.json());
            if (!silent) scrollBottom();
        } catch { /* ignore */ } finally {
            if (!silent) setCarregando(false);
        }
    }, []);

    const pararPolling = () => {
        if (intervalRef.current) { clearInterval(intervalRef.current); intervalRef.current = null; }
    };

    const iniciarPolling = useCallback((c: Conversa) => {
        pararPolling();
        intervalRef.current = setInterval(() => buscarMensagens(c, true), 5000);
    }, [buscarMensagens]);

    const selecionar = (c: Conversa) => {
        pararPolling();
        setSelecionada(c);
        setMensagens([]);
        setShowChat(true);
        buscarMensagens(c).then(() => scrollBottom());
        iniciarPolling(c);
    };

    const assumir = (onSuccess?: () => void) => {
        if (!selecionada) return;
        setAssumindo(true);
        router.post(route('tenant.conversas.assumir', selecionada.id), {}, {
            preserveScroll: true,
            onSuccess: () => { setSelecionada(p => p ? { ...p, status_v2: 'em_atendimento_humano' } : null); onSuccess?.(); },
            onFinish: () => setAssumindo(false),
        });
    };

    const devolver = () => {
        if (!selecionada) return;
        router.post(route('tenant.conversas.devolver', selecionada.id), {}, {
            preserveScroll: true,
            onSuccess: () => setSelecionada(p => p ? { ...p, status_v2: 'ativa' } : null),
        });
    };

    const enviar = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selecionada || !data.conteudo.trim()) return;
        const doPost = () => post(route('tenant.conversas.enviar', selecionada.id), {
            preserveScroll: true,
            onSuccess: () => { reset('conteudo'); buscarMensagens(selecionada).then(() => scrollBottom()); },
        });
        if (selecionada.status_v2 !== 'em_atendimento_humano') assumir(doPost);
        else doPost();
    };

    const filtrarStatus = (status: string) => {
        router.get(route('tenant.conversas.index'), status ? { status_v2: status } : {}, { preserveState: true });
    };

    const pararSyncPolling = () => {
        if (syncRef.current) {
            clearInterval(syncRef.current);
            syncRef.current = null;
        }
        setSincronizando(false);
    };

    const consultarStatusSync = async () => {
        try {
            const response = await fetch(route('tenant.conversas.sincronizacao.status'), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (! response.ok) return;

            const status = await response.json() as SyncStatus;
            setSyncStatus(status);

            const ativo = status.status === 'queued' || status.status === 'running';
            const estavaAtivo = syncWasActiveRef.current;
            syncWasActiveRef.current = ativo;
            setSincronizando(ativo);

            if (! ativo) {
                if (syncRef.current) {
                    clearInterval(syncRef.current);
                    syncRef.current = null;
                }
                if (status.status === 'completed' && estavaAtivo) {
                    router.reload({ only: ['conversas'] });
                }
            }
        } catch {
            // Mantém a tela utilizável mesmo se a consulta de progresso falhar.
        }
    };

    const iniciarSyncPolling = () => {
        if (syncRef.current) clearInterval(syncRef.current);
        void consultarStatusSync();
        syncRef.current = setInterval(() => void consultarStatusSync(), 2000);
    };

    useEffect(() => {
        iniciarSyncPolling();
        return () => {
            pararPolling();
            if (syncRef.current) clearInterval(syncRef.current);
        };
    }, []);

    const sincronizar = () => {
        setSincronizando(true);
        syncWasActiveRef.current = true;
        setSyncStatus({
            status: 'queued',
            processed: 0,
            total: 0,
            imported: 0,
            message: 'Preparando a sincronização.',
        });

        router.post(route('tenant.conversas.sincronizar'), {}, {
            preserveScroll: true,
            onSuccess: iniciarSyncPolling,
            onError: () => {
                pararSyncPolling();
                setSyncStatus({
                    status: 'failed',
                    message: 'Não foi possível iniciar. Verifique a conexão do WhatsApp.',
                });
            },
        });
    };

    const nomeDe = (c: Conversa) => c.cliente?.nome ?? c.telefone_cliente;
    const previewDe = (c: Conversa) => {
        const texto = c.mensagens?.[0]?.conteudo;
        if (!texto) return c.telefone_cliente;
        return texto.length > 48 ? texto.slice(0, 48) + '…' : texto;
    };

    const conversasFiltradas = busca.trim()
        ? conversas.data.filter(c => {
            const q = busca.toLowerCase().replace(/\D/g, '');
            const nome = nomeDe(c).toLowerCase();
            const tel  = c.telefone_cliente.replace(/\D/g, '');
            return nome.includes(busca.toLowerCase()) || tel.includes(q);
          })
        : conversas.data;

    const statusFiltros = [
        { label: 'Todas',          value: '' },
        { label: 'Ativas',         value: 'ativa' },
        { label: 'Aguardando',     value: 'aguardando_humano' },
        { label: 'Em atendimento', value: 'em_atendimento_humano' },
        { label: 'Encerradas',     value: 'encerrada' },
    ];

    const emAtendimento = selecionada?.status_v2 === 'em_atendimento_humano';
    const dotColor = selecionada ? (STATUS_DOT[selecionada.status_v2] ?? 'var(--text-3)') : '';
    const syncAtivo = syncStatus?.status === 'queued' || syncStatus?.status === 'running';
    const syncProgresso = syncStatus?.total
        ? Math.round(((syncStatus.processed ?? 0) / syncStatus.total) * 100)
        : 0;

    return (
        <AppLayout title="" fullHeight>
            <Head title="Conversas WhatsApp" />

            <div
                className="flex flex-1 min-h-0 overflow-hidden rounded-xl mx-3 mb-3 md:mx-4 md:mb-4"
                style={{ border: '1px solid var(--border)', background: 'var(--bg-surface)' }}
            >
                {/* ── Painel esquerdo — lista de conversas ────────────────── */}
                <div
                    className={`flex flex-col flex-shrink-0 w-full md:w-72 ${showChat ? 'hidden md:flex' : 'flex'}`}
                    style={{ borderRight: '1px solid var(--border)' }}
                >
                    {/* Header */}
                    <div className="px-4 pt-3.5 pb-2" style={{ borderBottom: '1px solid var(--border)' }}>
                        <div className="flex items-center justify-between gap-2 mb-2.5">
                            <h2 className="text-[15px] font-semibold text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                                Conversas
                            </h2>
                            <div className="flex items-center gap-1.5">
                                <button
                                    onClick={sincronizar}
                                    disabled={sincronizando}
                                    title={sincronizando ? 'Sincronização em andamento' : 'Sincronizar conversas do WhatsApp'}
                                    aria-label={sincronizando ? 'Sincronização em andamento' : 'Sincronizar conversas do WhatsApp'}
                                    className="flex h-9 w-9 items-center justify-center rounded-full transition-colors hover:bg-surface-2 disabled:opacity-60"
                                    style={{ color: sincronizando ? 'var(--jade)' : 'var(--text-3)' }}
                                >
                                    <svg
                                        width={14} height={14} viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
                                        className={sincronizando ? 'animate-spin' : ''}
                                    >
                                        <polyline points="23 4 23 10 17 10"/>
                                        <path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                                    </svg>
                                </button>
                                <button
                                    onClick={() => setShowModalNova(true)}
                                    title="Nova conversa"
                                    className="flex items-center justify-center rounded-full p-1.5 transition-colors hover:bg-surface-2"
                                    style={{ color: 'var(--jade)' }}
                                >
                                    <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {syncStatus && syncStatus.status !== 'idle' && (
                            <div
                                className="mb-3 rounded-xl p-3"
                                style={{
                                    background: syncStatus.status === 'failed' ? 'rgba(239,68,68,.08)' : 'var(--bg-surface-2)',
                                    border: `1px solid ${syncStatus.status === 'failed' ? 'rgba(239,68,68,.22)' : 'var(--border)'}`,
                                }}
                                role="status"
                                aria-live="polite"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-xs font-semibold text-primary">
                                            {syncStatus.status === 'queued' && 'Aguardando processamento'}
                                            {syncStatus.status === 'running' && 'Sincronizando conversas'}
                                            {syncStatus.status === 'completed' && 'Sincronização concluída'}
                                            {syncStatus.status === 'failed' && 'Falha na sincronização'}
                                        </p>
                                        <p className="mt-0.5 text-[11px] leading-relaxed" style={{ color: 'var(--text-3)' }}>
                                            {syncStatus.message}
                                        </p>
                                    </div>
                                    {syncAtivo && (
                                        <span className="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-[var(--border-strong)] border-t-[var(--jade)]" />
                                    )}
                                </div>

                                {syncStatus.status === 'running' && (syncStatus.total ?? 0) > 0 && (
                                    <div className="mt-2.5">
                                        <div className="mb-1 flex items-center justify-between text-[10px]" style={{ color: 'var(--text-3)' }}>
                                            <span>{syncStatus.processed ?? 0} de {syncStatus.total} conversas</span>
                                            <span>{syncProgresso}%</span>
                                        </div>
                                        <div className="h-1.5 overflow-hidden rounded-full" style={{ background: 'var(--border)' }}>
                                            <div className="h-full rounded-full transition-all" style={{ width: `${syncProgresso}%`, background: 'var(--jade)' }} />
                                        </div>
                                    </div>
                                )}

                                {syncStatus.status === 'completed' && (
                                    <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[10px]" style={{ color: 'var(--text-3)' }}>
                                        <span>{syncStatus.imported ?? 0} mensagens importadas</span>
                                        {(syncStatus.removed ?? 0) > 0 && <span>{syncStatus.removed} conversas vazias removidas</span>}
                                        {(syncStatus.errors ?? 0) > 0 && <span>{syncStatus.errors} falhas</span>}
                                    </div>
                                )}

                                {syncStatus.status === 'failed' && (
                                    <button type="button" onClick={sincronizar} className="mt-2 text-xs font-semibold" style={{ color: 'var(--danger)' }}>
                                        Tentar novamente
                                    </button>
                                )}
                            </div>
                        )}

                        {/* Campo de pesquisa estilo WhatsApp Web */}
                        <div className="relative mb-2.5">
                            <svg
                                className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2"
                                width={13} height={13} viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"
                                style={{ color: 'var(--text-3)' }}
                            >
                                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                            </svg>
                            <input
                                ref={buscaRef}
                                type="text"
                                value={busca}
                                onChange={e => setBusca(e.target.value)}
                                placeholder="Pesquisar conversa…"
                                className="w-full rounded-full py-2 pl-8 pr-8 text-[12px] outline-none transition-colors"
                                style={{
                                    background: 'var(--bg-surface-2)',
                                    color: 'var(--text-1)',
                                    border: '1px solid var(--border)',
                                }}
                                onFocus={e => (e.currentTarget.style.borderColor = 'var(--accent)')}
                                onBlur={e  => (e.currentTarget.style.borderColor = 'var(--border)')}
                            />
                            {busca && (
                                <button
                                    onClick={() => { setBusca(''); buscaRef.current?.focus(); }}
                                    className="absolute right-2.5 top-1/2 -translate-y-1/2 flex h-4 w-4 items-center justify-center rounded-full"
                                    style={{ color: 'var(--text-3)', background: 'var(--bg-surface)' }}
                                >
                                    <svg width={8} height={8} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round">
                                        <path d="M18 6L6 18M6 6l12 12"/>
                                    </svg>
                                </button>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-1">
                            {statusFiltros.map(f => {
                                const ativo = (filtros.status_v2 ?? '') === f.value;
                                return (
                                    <button
                                        key={f.value}
                                        onClick={() => filtrarStatus(f.value)}
                                        className="rounded-full px-2.5 py-1 text-[10px] font-medium transition-colors"
                                        style={ativo
                                            ? { background: 'var(--accent)', color: 'white' }
                                            : { background: 'var(--bg-surface-2)', color: 'var(--text-3)' }
                                        }
                                    >
                                        {f.label}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Lista */}
                    <div className="flex-1 overflow-y-auto" style={{ scrollbarWidth: 'thin', scrollbarColor: 'var(--border) transparent' }}>
                        {conversasFiltradas.length === 0 ? (
                            <div className="flex h-full flex-col items-center justify-center gap-2 px-6 text-center">
                                <svg width={32} height={32} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                                    <path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                <p className="text-xs font-medium text-primary">
                                    {busca ? 'Nenhum resultado' : 'Nenhuma conversa'}
                                </p>
                                <p className="text-[11px]" style={{ color: 'var(--text-3)' }}>
                                    {busca ? `Nada encontrado para "${busca}"` : 'Inicie um atendimento ou aguarde novas mensagens.'}
                                </p>
                                {!busca && (
                                    <button type="button" onClick={() => setShowModalNova(true)} className="btn-primary mt-2 min-h-11 text-xs">
                                        Iniciar conversa
                                    </button>
                                )}
                            </div>
                        ) : conversasFiltradas.map(c => {
                            const ativo = selecionada?.id === c.id;
                            return (
                                <button
                                    key={c.id}
                                    onClick={() => selecionar(c)}
                                    className="table-row-hover w-full px-3 py-3 text-left"
                                    style={{
                                        borderBottom: '1px solid var(--border)',
                                        background: ativo ? 'var(--accent-light)' : 'transparent',
                                        borderLeft: `3px solid ${ativo ? 'var(--accent)' : 'transparent'}`,
                                    }}
                                >
                                    <div className="flex items-center gap-3">
                                        <Avatar name={nomeDe(c)} />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-center justify-between gap-1">
                                                <p className="truncate text-[13px] font-semibold text-primary">{nomeDe(c)}</p>
                                                <span className="shrink-0 text-[10px]" style={{ color: 'var(--text-3)' }}>
                                                    {fmtRelativo(c.ultima_mensagem_em)}
                                                </span>
                                            </div>
                                            <div className="mt-0.5 flex items-center justify-between gap-1">
                                                <p className="truncate text-[11px]" style={{ color: 'var(--text-3)' }}>
                                                    {previewDe(c)}
                                                </p>
                                                <StatusBadge status={c.status_v2} />
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                </div>

                {/* ── Painel direito — chat ────────────────────────────────── */}
                {selecionada ? (
                    <div className={`flex flex-1 flex-col min-w-0 ${showChat ? 'flex' : 'hidden md:flex'}`}>

                        {/* Chat header */}
                        <div
                            className="flex flex-shrink-0 items-center justify-between gap-2 px-3 py-2.5 sm:gap-3 sm:px-4 sm:py-3"
                            style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface)' }}
                        >
                            <div className="flex items-center gap-3 min-w-0">
                                <button
                                    className="md:hidden flex-shrink-0 rounded-md p-1.5 transition-colors hover:bg-surface-2"
                                    style={{ color: 'var(--text-2)' }}
                                    onClick={() => setShowChat(false)}
                                    aria-label="Voltar"
                                >
                                    <svg width={18} height={18} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                        <polyline points="15 18 9 12 15 6"/>
                                    </svg>
                                </button>

                                <Avatar name={nomeDe(selecionada)} />

                                <div className="min-w-0">
                                    <p className="truncate text-[15px] font-semibold text-primary">{nomeDe(selecionada)}</p>
                                    <div className="flex items-center gap-1.5 mt-0.5">
                                        <span className="h-1.5 w-1.5 rounded-full shrink-0" style={{ background: dotColor }} />
                                        <p className="text-[11px]" style={{ color: dotColor }}>
                                            {STATUS_LABEL[selecionada.status_v2]}
                                        </p>
                                        <span className="hidden text-[11px] sm:inline" style={{ color: 'var(--text-3)' }}>· {selecionada.telefone_cliente}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="flex gap-2 flex-shrink-0">
                                {!emAtendimento && selecionada.status_v2 !== 'encerrada' && (
                                    <button
                                        onClick={() => assumir()}
                                        disabled={assumindo}
                                        className="min-h-9 rounded-full px-3 py-1.5 text-xs font-semibold text-white transition-all hover:brightness-110 disabled:opacity-50 sm:px-4"
                                        style={{ background: 'var(--accent)' }}
                                    >
                                        {assumindo ? '…' : 'Assumir'}
                                    </button>
                                )}
                                {emAtendimento && (
                                    <button
                                        onClick={devolver}
                                        className="min-h-9 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors sm:px-4"
                                        style={{ background: 'var(--jade-light)', border: '1px solid rgba(0,168,132,0.25)', color: 'var(--jade)' }}
                                    >
                                        <span className="sm:hidden">Bot</span>
                                        <span className="hidden sm:inline">Devolver ao bot</span>
                                    </button>
                                )}
                            </div>
                        </div>

                        {selecionada.status_v2 === 'aguardando_humano' && (
                            <div className="flex items-center justify-between gap-3 px-3 py-2.5 sm:px-4" style={{ background: 'var(--amber-btn-bg)', borderBottom: '1px solid var(--amber-btn-bdr)' }}>
                                <p className="text-xs" style={{ color: 'var(--amber-text)' }}>Este cliente foi transferido pelo bot e aguarda sua equipe.</p>
                                <button type="button" onClick={() => assumir()} disabled={assumindo} className="shrink-0 text-xs font-semibold" style={{ color: 'var(--amber-text)' }}>Assumir →</button>
                            </div>
                        )}

                        {/* Mensagens */}
                        <div
                            ref={chatRef}
                            className="flex-1 overflow-y-auto px-3 py-4 sm:px-4 sm:py-5 md:px-6"
                            style={{
                                background: 'var(--bg-app)',
                                backgroundImage: 'radial-gradient(circle, var(--border) 1px, transparent 1px)',
                                backgroundSize: '24px 24px',
                            }}
                        >
                            {carregando ? (
                                <div className="flex h-full items-center justify-center">
                                    <div className="flex gap-1.5">
                                        {[0,1,2].map(i => (
                                            <span key={i} className="h-2 w-2 rounded-full animate-bounce" style={{ background: 'var(--text-3)', animationDelay: `${i * 0.15}s` }} />
                                        ))}
                                    </div>
                                </div>
                            ) : mensagens.length === 0 ? (
                                <div className="flex h-full items-center justify-center">
                                    <p className="text-sm" style={{ color: 'var(--text-3)' }}>Nenhuma mensagem ainda.</p>
                                </div>
                            ) : (
                                mensagens.map((m, i) => (
                                    <Bubble key={m.id} msg={m} prevRemetente={mensagens[i - 1]?.remetente} />
                                ))
                            )}
                        </div>

                        {/* Input */}
                        <form
                            onSubmit={enviar}
                            className="flex flex-shrink-0 items-end gap-2 px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-2.5 sm:gap-2.5 sm:px-4 sm:py-3 md:px-5"
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
                                placeholder={emAtendimento ? 'Mensagem… (Enter para enviar)' : 'Escrever para assumir o atendimento…'}
                                rows={1}
                                className="flex-1 resize-none rounded-2xl px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-accent/40"
                                style={{
                                    background: 'var(--bg-surface-2)',
                                    border: '1px solid var(--border-strong)',
                                    color: 'var(--text-1)',
                                    maxHeight: '120px',
                                    lineHeight: '1.5',
                                }}
                                onInput={e => {
                                    const el = e.currentTarget;
                                    el.style.height = 'auto';
                                    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
                                }}
                            />
                            <button
                                type="submit"
                                disabled={processing || assumindo || !data.conteudo.trim()}
                                className="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full text-white transition-all hover:brightness-110 disabled:opacity-40"
                                style={{ background: data.conteudo.trim() ? 'var(--jade)' : 'var(--text-3)' }}
                            >
                                {(processing || assumindo)
                                    ? <span className="h-4 w-4 rounded-full border-2 border-white/40 border-t-white animate-spin" />
                                    : <SendIcon />
                                }
                            </button>
                        </form>
                    </div>
                ) : (
                    <div className="hidden md:flex flex-1 items-center justify-center flex-col gap-3" style={{ background: 'var(--bg-app)', backgroundImage: 'radial-gradient(circle, var(--border) 1px, transparent 1px)', backgroundSize: '24px 24px' }}>
                        <div
                            className="flex h-16 w-16 items-center justify-center rounded-full"
                            style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
                        >
                            <svg width={28} height={28} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.25" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                            </svg>
                        </div>
                        <div className="text-center">
                            <p className="text-base font-medium text-primary">Selecione uma conversa</p>
                            <p className="mt-0.5 text-sm" style={{ color: 'var(--text-3)' }}>O histórico aparece aqui</p>
                        </div>
                    </div>
                )}
            </div>

            {showModalNova && (
                <NovaConversaModal
                    initialTelefone={new URLSearchParams(window.location.search).get('telefone') ?? ''}
                    onClose={() => setShowModalNova(false)}
                />
            )}
        </AppLayout>
    );
}
