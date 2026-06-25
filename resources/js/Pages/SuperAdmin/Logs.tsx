import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';

interface LogEntry {
    at:      string;
    level:   'ERROR' | 'WARNING' | 'INFO' | 'DEBUG';
    message: string;
    context: Record<string, unknown> | null;
}

interface ApiResponse {
    entries: LogEntry[];
    size:    number;
    arquivo: string;
}

const LEVEL_STYLE: Record<string, { bg: string; text: string; label: string }> = {
    ERROR:   { bg: 'rgba(239,68,68,0.1)',   text: '#f87171', label: 'ERROR'   },
    WARNING: { bg: 'rgba(245,158,11,0.1)',  text: '#fbbf24', label: 'WARN'    },
    INFO:    { bg: 'rgba(99,102,241,0.1)',  text: '#a5b4fc', label: 'INFO'    },
    DEBUG:   { bg: 'rgba(255,255,255,0.05)', text: 'rgba(232,230,225,0.35)', label: 'DEBUG' },
};

function LevelBadge({ level }: { level: string }) {
    const s = LEVEL_STYLE[level] ?? LEVEL_STYLE.DEBUG;
    return (
        <span
            className="inline-block shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold tracking-wider font-mono"
            style={{ background: s.bg, color: s.text }}
        >
            {s.label}
        </span>
    );
}

function CopyButton({ entry }: { entry: LogEntry }) {
    const [copied, setCopied] = useState(false);

    const copy = (e: React.MouseEvent) => {
        e.stopPropagation();
        const ctx = entry.context ? '\n\n' + JSON.stringify(entry.context, null, 2) : '';
        const text = `[${entry.level}] ${entry.at}\n${entry.message}${ctx}`;
        navigator.clipboard.writeText(text).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <button
            onClick={copy}
            title="Copiar"
            className="shrink-0 rounded p-1 transition-colors"
            style={{ color: copied ? 'var(--jade)' : 'var(--text-3)' }}
            onMouseEnter={e => { if (!copied) (e.currentTarget as HTMLElement).style.color = 'var(--text-1)'; }}
            onMouseLeave={e => { if (!copied) (e.currentTarget as HTMLElement).style.color = 'var(--text-3)'; }}
        >
            {copied ? (
                <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            ) : (
                <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                    <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/>
                </svg>
            )}
        </button>
    );
}

type NivelFiltro = 'ALL' | 'ERROR' | 'WARNING' | 'INFO';

export default function Logs() {
    const [entries,  setEntries]  = useState<LogEntry[]>([]);
    const [nivel,    setNivel]    = useState<NivelFiltro>('ALL');
    const [busca,    setBusca]    = useState('');
    const [pausado,  setPausado]  = useState(false);
    const [loading,  setLoading]  = useState(true);
    const [tamanho,  setTamanho]  = useState(0);
    const [arquivo,  setArquivo]  = useState('');
    const [expanded, setExpanded] = useState<number | null>(null);
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const buscar = async (n: NivelFiltro) => {
        try {
            const res  = await fetch(route('superadmin.logs.json') + `?nivel=${n}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data: ApiResponse = await res.json();
            setEntries(data.entries);
            setTamanho(data.size);
            setArquivo(data.arquivo);
        } catch {
            // silenciar erros de rede
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        buscar(nivel);
    }, [nivel]);

    useEffect(() => {
        if (pausado) {
            if (intervalRef.current) clearInterval(intervalRef.current);
            return;
        }
        intervalRef.current = setInterval(() => buscar(nivel), 5000);
        return () => { if (intervalRef.current) clearInterval(intervalRef.current); };
    }, [pausado, nivel]);

    const niveis: NivelFiltro[] = ['ALL', 'ERROR', 'WARNING', 'INFO'];

    const buscaLower = busca.toLowerCase();
    const visiveis = busca
        ? entries.filter(e => e.message.toLowerCase().includes(buscaLower) || JSON.stringify(e.context).toLowerCase().includes(buscaLower))
        : entries;

    return (
        <AppLayout title="Logs do sistema" subtitle="Registro de erros, avisos e eventos da plataforma">
            <Head title="Logs" />

            {/* Toolbar */}
            <div className="mb-5 flex flex-wrap items-center gap-3">
                {/* Filtro de nível */}
                <div
                    className="flex items-center gap-1 rounded-lg p-1"
                    style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
                >
                    {niveis.map(n => (
                        <button
                            key={n}
                            onClick={() => { setNivel(n); setLoading(true); }}
                            className="rounded px-3 py-1.5 text-xs font-medium transition-all"
                            style={
                                nivel === n
                                    ? { background: 'var(--accent)', color: 'white' }
                                    : { color: 'var(--text-2)' }
                            }
                        >
                            {n === 'ALL' ? 'Todos' : n}
                        </button>
                    ))}
                </div>

                {/* Polling toggle */}
                <button
                    onClick={() => setPausado(p => !p)}
                    className="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium transition-colors"
                    style={{
                        background: 'var(--bg-surface)',
                        border:     '1px solid var(--border)',
                        color:      pausado ? 'var(--text-3)' : 'var(--jade)',
                    }}
                >
                    {pausado ? (
                        <>
                            <svg width={12} height={12} viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                            Retomar
                        </>
                    ) : (
                        <>
                            <span className="h-2 w-2 rounded-full animate-pulse" style={{ background: 'var(--jade)' }} />
                            Ao vivo
                        </>
                    )}
                </button>

                {/* Reload manual */}
                <button
                    onClick={() => { setLoading(true); buscar(nivel); }}
                    className="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium transition-colors"
                    style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', color: 'var(--text-2)' }}
                >
                    <svg width={12} height={12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
                    </svg>
                    Recarregar
                </button>

                {/* Busca por texto */}
                <div className="relative flex-1 min-w-[200px] max-w-sm">
                    <svg
                        width={12} height={12} viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
                        className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2"
                        style={{ color: 'var(--text-3)' }}
                    >
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input
                        type="text"
                        value={busca}
                        onChange={e => { setBusca(e.target.value); setExpanded(null); }}
                        placeholder="Buscar nos logs…"
                        className="w-full rounded-lg py-2 pl-8 pr-3 text-xs outline-none"
                        style={{
                            background: 'var(--bg-surface)',
                            border:     '1px solid var(--border)',
                            color:      'var(--text-1)',
                        }}
                    />
                    {busca && (
                        <button
                            onClick={() => setBusca('')}
                            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs"
                            style={{ color: 'var(--text-3)' }}
                        >
                            ✕
                        </button>
                    )}
                </div>

                {/* Info do arquivo */}
                {arquivo && (
                    <span className="ml-auto shrink-0 text-[11px]" style={{ color: 'var(--text-3)' }}>
                        {arquivo} · {(tamanho / 1024).toFixed(0)} KB
                    </span>
                )}
            </div>

            {/* Tabela de logs */}
            <div
                className="overflow-hidden rounded-xl"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
            >
                {loading ? (
                    <div className="flex items-center justify-center py-16">
                        <svg className="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24" style={{ color: 'var(--text-3)' }}>
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </div>
                ) : visiveis.length === 0 ? (
                    <div className="py-16 text-center text-sm" style={{ color: 'var(--text-3)' }}>
                        {busca ? `Nenhum resultado para "${busca}"` : 'Nenhuma entrada encontrada'}
                    </div>
                ) : (
                    <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                        {visiveis.map((e, i) => (
                            <div key={i}>
                                <button
                                    onClick={() => setExpanded(expanded === i ? null : i)}
                                    className="w-full px-4 py-3 text-left transition-colors hover:bg-white/[0.02]"
                                >
                                    {/* Meta row: badge + timestamp + copy + chevron */}
                                    <div className="flex items-center gap-2">
                                        <LevelBadge level={e.level} />
                                        <span
                                            className="flex-1 font-mono text-[11px]"
                                            style={{ color: 'var(--text-3)' }}
                                        >
                                            {e.at}
                                        </span>
                                        <CopyButton entry={e} />
                                        {e.context && (
                                            <svg
                                                width={13} height={13} viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" strokeWidth="2"
                                                strokeLinecap="round" strokeLinejoin="round"
                                                className="shrink-0 transition-transform duration-200"
                                                style={{
                                                    color: 'var(--text-3)',
                                                    transform: expanded === i ? 'rotate(180deg)' : 'rotate(0)',
                                                }}
                                            >
                                                <polyline points="6 9 12 15 18 9"/>
                                            </svg>
                                        )}
                                    </div>
                                    {/* Message: full width, wraps */}
                                    <p
                                        className="mt-1.5 text-[13px] leading-snug"
                                        style={{ color: 'var(--text-1)', wordBreak: 'break-word' }}
                                    >
                                        {e.message}
                                    </p>
                                </button>

                                {/* Contexto expandido */}
                                {expanded === i && e.context && (
                                    <div
                                        className="border-t px-4 pb-4 pt-3"
                                        style={{ borderColor: 'var(--border)', background: 'rgba(0,0,0,0.15)' }}
                                    >
                                        <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                                            Contexto
                                        </p>
                                        <pre
                                            className="overflow-x-auto rounded-lg p-3 text-[11px] leading-relaxed"
                                            style={{
                                                background:  'rgba(0,0,0,0.3)',
                                                color:       'var(--text-2)',
                                                fontFamily:  "'Fira Code', 'Cascadia Code', monospace",
                                                whiteSpace:  'pre-wrap',
                                                wordBreak:   'break-word',
                                            }}
                                        >
                                            {JSON.stringify(e.context, null, 2)}
                                        </pre>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <p className="mt-3 text-center text-[11px]" style={{ color: 'var(--text-3)' }}>
                {busca ? `${visiveis.length} de ${entries.length} entradas` : `${entries.length} entradas`} · Atualização automática a cada 5s
            </p>
        </AppLayout>
    );
}
