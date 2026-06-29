import { useRef, useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import { ConversaPreview } from '@/hooks/useNotificacoes';

interface Props {
    count: number;
    preview: ConversaPreview[];
    size?: 'sm' | 'md';
    onClose?: () => void;
}

function fmtRelativo(iso: string | null) {
    if (!iso) return '';
    const diffMin = Math.floor((Date.now() - new Date(iso).getTime()) / 60000);
    if (diffMin < 1)  return 'agora';
    if (diffMin < 60) return `${diffMin}m`;
    const h = Math.floor(diffMin / 60);
    if (h < 24)       return `${h}h`;
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function previewText(c: ConversaPreview) {
    if (c.tipo === 'imagem')    return '📷 Imagem';
    if (c.tipo === 'audio')     return '🎵 Áudio';
    if (c.tipo === 'video')     return '🎥 Vídeo';
    if (c.tipo === 'documento') return '📄 Documento';
    if (c.tipo === 'sticker')   return '🎭 Figurinha';
    const txt = c.preview ?? '';
    return txt.length > 42 ? txt.slice(0, 42) + '…' : (txt || '…');
}

function Avatar({ name }: { name: string }) {
    const PALETTES = [
        { bg: 'rgba(99,102,241,0.25)',  color: '#a5b4fc' },
        { bg: 'rgba(0,168,132,0.22)',   color: '#34d399' },
        { bg: 'rgba(251,191,36,0.22)',  color: '#fbbf24' },
        { bg: 'rgba(239,68,68,0.20)',   color: '#f87171' },
        { bg: 'rgba(168,85,247,0.22)',  color: '#c084fc' },
    ];
    const p = PALETTES[name.charCodeAt(0) % PALETTES.length];
    return (
        <div
            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-[12px] font-semibold"
            style={{ background: p.bg, color: p.color }}
        >
            {name.charAt(0).toUpperCase()}
        </div>
    );
}

async function marcarLida(id: number) {
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content;
    await fetch(route('tenant.conversas.marcar-lida', id), {
        method: 'POST',
        credentials: 'include',
        headers: {
            'X-CSRF-TOKEN': csrf ?? '',
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
        },
    });
}

export default function NotificacoesBell({ count, preview, size = 'md', onClose }: Props) {
    const [aberto, setAberto] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const btnSize = size === 'sm' ? 'h-7 w-7' : 'h-8 w-8';
    const iconSize = size === 'sm' ? 15 : 17;

    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) {
                setAberto(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    return (
        <div ref={ref} className="relative">
            {/* Botão sino */}
            <button
                onClick={() => setAberto(v => !v)}
                className={`relative flex ${btnSize} shrink-0 items-center justify-center rounded-full transition-colors hover:bg-white/10`}
                style={{ color: count > 0 ? '#00a884' : 'var(--text-3)' }}
                title={count > 0 ? `${count} não lida(s)` : 'Notificações'}
            >
                <svg width={iconSize} height={iconSize} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
                {count > 0 && (
                    <span
                        className="absolute -right-0.5 -top-0.5 flex h-3.5 min-w-[14px] items-center justify-center rounded-full px-0.5 text-[8px] font-bold text-white"
                        style={{ background: '#00a884' }}
                    >
                        {count > 99 ? '99+' : count}
                    </span>
                )}
            </button>

            {/* Dropdown */}
            {aberto && (
                <div
                    className="absolute left-0 top-full z-50 mt-2 w-72 rounded-2xl shadow-2xl overflow-hidden"
                    style={{ background: 'var(--bg-card, var(--bg-surface))', border: '1px solid var(--border)' }}
                >
                    {/* Header */}
                    <div className="flex items-center justify-between px-4 py-2.5" style={{ borderBottom: '1px solid var(--border)' }}>
                        <span className="text-[12px] font-semibold" style={{ color: 'var(--text-1)' }}>
                            Mensagens não lidas
                            {count > 0 && (
                                <span className="ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] font-bold text-white" style={{ background: '#00a884' }}>
                                    {count}
                                </span>
                            )}
                        </span>
                        <Link
                            href={route('tenant.conversas.index')}
                            onClick={() => { setAberto(false); onClose?.(); }}
                            className="text-[10px] transition-colors hover:underline"
                            style={{ color: 'var(--accent)' }}
                        >
                            Ver todas →
                        </Link>
                    </div>

                    {/* Lista */}
                    {preview.length === 0 ? (
                        <div className="px-4 py-6 text-center">
                            <svg className="mx-auto mb-2" width={24} height={24} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"/>
                            </svg>
                            <p className="text-[11px]" style={{ color: 'var(--text-3)' }}>Nenhuma mensagem não lida</p>
                        </div>
                    ) : (
                        <div>
                            {preview.map(c => (
                                <div
                                    key={c.id}
                                    className="group flex items-center gap-2.5 px-3 py-2.5 transition-colors hover:bg-white/5"
                                    style={{ borderBottom: '1px solid var(--border)' }}
                                >
                                    <Link
                                        href={route('tenant.conversas.index')}
                                        onClick={() => { setAberto(false); onClose?.(); }}
                                        className="flex flex-1 items-center gap-2.5 min-w-0"
                                    >
                                        <Avatar name={c.nome} />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-baseline justify-between gap-1">
                                                <p className="truncate text-[12px] font-semibold" style={{ color: 'var(--text-1)' }}>{c.nome}</p>
                                                <span className="shrink-0 text-[10px]" style={{ color: 'var(--text-3)' }}>{fmtRelativo(c.em)}</span>
                                            </div>
                                            <p className="truncate text-[11px]" style={{ color: 'var(--text-3)' }}>{previewText(c)}</p>
                                        </div>
                                    </Link>

                                    {/* Botão marcar como lida */}
                                    <button
                                        title="Marcar como lida"
                                        onClick={() => marcarLida(c.id)}
                                        className="shrink-0 opacity-0 group-hover:opacity-100 flex h-6 w-6 items-center justify-center rounded-full transition-all hover:bg-white/10"
                                        style={{ color: 'var(--text-3)' }}
                                    >
                                        <svg width={12} height={12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
