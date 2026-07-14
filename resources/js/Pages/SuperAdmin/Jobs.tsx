import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';

interface FailedJob {
    id: number;
    uuid: string;
    job: string;
    job_full: string;
    queue: string;
    error: string;
    exception: string;
    failed_at: string;
    connection: string;
}

interface QueueStats {
    failed: number;
    pending: number;
}

interface FalhaRecente {
    id: number;
    tipo: string;
    provider: string | null;
    tenant: string | null;
    mensagem: string | null;
    ocorrido_em: string;
}

interface Props extends PageProps {
    failed: FailedJob[];
    queue: QueueStats;
    falhasRecentes: FalhaRecente[];
}

function fmtDt(s: string) {
    return new Date(s).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
    });
}

export default function Jobs({ failed, queue, falhasRecentes }: Props) {
    const [expanded, setExpanded] = useState<number | null>(null);
    const [loading, setLoading] = useState<number | 'all' | 'flush' | null>(null);

    const retry = (id: number) => {
        setLoading(id);
        router.post(route('superadmin.jobs.retry', id), {}, {
            onFinish: () => setLoading(null),
        });
    };

    const retryAll = () => {
        if (!confirm('Reenfileirar todos os jobs falhados?')) return;
        setLoading('all');
        router.post(route('superadmin.jobs.retry-all'), {}, {
            onFinish: () => setLoading(null),
        });
    };

    const destroy = (id: number) => {
        if (!confirm('Remover este job permanentemente?')) return;
        setLoading(id);
        router.delete(route('superadmin.jobs.destroy', id), {
            onFinish: () => setLoading(null),
        });
    };

    const flush = () => {
        if (!confirm('Limpar TODOS os jobs falhados? Essa ação não pode ser desfeita.')) return;
        setLoading('flush');
        router.delete(route('superadmin.jobs.destroy-all'), {
            onFinish: () => setLoading(null),
        });
    };

    return (
        <AppLayout title="Jobs falhados" subtitle="Tarefas da fila que encontraram erros durante a execução">
            <Head title="Jobs" />

            {/* Stats */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2">
                <div
                    className="flex items-center gap-4 rounded-xl p-5"
                    style={{
                        background: queue.failed > 0 ? 'rgba(239,68,68,0.08)' : 'var(--bg-surface)',
                        border: `1px solid ${queue.failed > 0 ? 'rgba(239,68,68,0.25)' : 'var(--border)'}`,
                    }}
                >
                    <div
                        className="flex h-11 w-11 items-center justify-center rounded-xl"
                        style={{ background: queue.failed > 0 ? 'rgba(239,68,68,0.15)' : 'var(--bg-surface-2)' }}
                    >
                        <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"
                            style={{ color: queue.failed > 0 ? '#f87171' : 'var(--text-3)' }}>
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>Jobs falhados</p>
                        <p className="text-3xl font-bold" style={{
                            fontFamily: 'Instrument Serif, Georgia, serif',
                            color: queue.failed > 0 ? '#f87171' : 'var(--text-1)',
                        }}>
                            {queue.failed}
                        </p>
                    </div>
                </div>

                <div
                    className="flex items-center gap-4 rounded-xl p-5"
                    style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
                >
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl" style={{ background: 'var(--bg-surface-2)' }}>
                        <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"
                            style={{ color: 'var(--text-3)' }}>
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>Na fila agora</p>
                        <p className="text-3xl font-bold" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>
                            {queue.pending}
                        </p>
                    </div>
                </div>
            </div>

            {/* Ações globais */}
            {failed.length > 0 && (
                <div className="mb-4 flex gap-2">
                    <button
                        onClick={retryAll}
                        disabled={loading === 'all'}
                        className="flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-medium transition-all hover:brightness-110 disabled:opacity-50"
                        style={{ background: 'var(--accent)', color: 'white' }}
                    >
                        {loading === 'all' ? 'Reenfileirando…' : `Retentar todos (${failed.length})`}
                    </button>
                    <button
                        onClick={flush}
                        disabled={loading === 'flush'}
                        className="flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-medium transition-all disabled:opacity-50"
                        style={{ background: 'rgba(239,68,68,0.1)', color: '#f87171', border: '1px solid rgba(239,68,68,0.2)' }}
                    >
                        {loading === 'flush' ? 'Limpando…' : 'Limpar fila'}
                    </button>
                </div>
            )}

            {/* Lista de jobs falhados */}
            <div className="overflow-hidden rounded-xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
                {failed.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-20 text-center">
                        <svg width={40} height={40} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"
                            className="mb-3" style={{ color: 'var(--jade)' }}>
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <p className="text-sm font-medium" style={{ color: 'var(--text-2)' }}>Nenhum job falhado</p>
                        <p className="text-xs" style={{ color: 'var(--text-3)' }}>Tudo processando normalmente.</p>
                    </div>
                ) : (
                    <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                        {/* Header */}
                        <div className="grid px-4 py-2.5" style={{
                            gridTemplateColumns: '1fr 140px 160px 100px',
                            background: 'var(--bg-surface-2)',
                        }}>
                            {['Job / Erro', 'Fila', 'Falhou em', 'Ações'].map(h => (
                                <p key={h} className="text-[10px] font-semibold uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>{h}</p>
                            ))}
                        </div>

                        {failed.map(job => (
                            <div key={job.id}>
                                <div
                                    className="grid cursor-pointer items-center px-4 py-3.5 transition-colors hover:bg-white/[0.015]"
                                    style={{ gridTemplateColumns: '1fr 140px 160px 100px' }}
                                    onClick={() => setExpanded(expanded === job.id ? null : job.id)}
                                >
                                    {/* Job + erro */}
                                    <div className="min-w-0 pr-4">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="shrink-0 rounded px-1.5 py-0.5 font-mono text-[10px] font-bold"
                                                style={{ background: 'rgba(239,68,68,0.1)', color: '#f87171' }}
                                            >
                                                #{job.id}
                                            </span>
                                            <span className="truncate text-[13px] font-medium" style={{ color: 'var(--text-1)' }}>
                                                {job.job}
                                            </span>
                                        </div>
                                        <p className="mt-0.5 truncate text-[11px]" style={{ color: '#f87171', opacity: 0.8 }}>
                                            {job.error}
                                        </p>
                                    </div>

                                    {/* Fila */}
                                    <span className="text-xs" style={{ color: 'var(--text-3)' }}>{job.queue}</span>

                                    {/* Timestamp */}
                                    <span className="font-mono text-[11px]" style={{ color: 'var(--text-3)' }}>{fmtDt(job.failed_at)}</span>

                                    {/* Ações */}
                                    <div className="flex gap-1.5" onClick={e => e.stopPropagation()}>
                                        <button
                                            onClick={() => retry(job.id)}
                                            disabled={loading === job.id}
                                            className="rounded-lg px-2.5 py-1 text-[11px] font-medium transition-all hover:brightness-110 disabled:opacity-50"
                                            style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
                                            title="Retentar"
                                        >
                                            {loading === job.id ? '…' : '↺'}
                                        </button>
                                        <button
                                            onClick={() => destroy(job.id)}
                                            disabled={loading === job.id}
                                            className="rounded-lg px-2.5 py-1 text-[11px] font-medium transition-all disabled:opacity-50"
                                            style={{ background: 'rgba(239,68,68,0.1)', color: '#f87171' }}
                                            title="Remover"
                                        >
                                            ✕
                                        </button>
                                    </div>
                                </div>

                                {/* Stack trace expandido */}
                                {expanded === job.id && (
                                    <div
                                        className="border-t px-4 pb-4 pt-3"
                                        style={{ borderColor: 'var(--border)', background: 'rgba(0,0,0,0.2)' }}
                                    >
                                        <p className="mb-1 text-[10px] font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                                            Stack trace — {job.job_full}
                                        </p>
                                        <pre
                                            className="max-h-80 overflow-auto rounded-lg p-3 text-[11px] leading-relaxed"
                                            style={{
                                                background:  'rgba(0,0,0,0.4)',
                                                color:       '#fca5a5',
                                                fontFamily:  'monospace',
                                                whiteSpace:  'pre-wrap',
                                                wordBreak:   'break-all',
                                            }}
                                        >
                                            {job.exception}
                                        </pre>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Falhas recentes por tenant (jobs + integrações) */}
            {falhasRecentes.length > 0 && (
                <div className="mt-6 overflow-hidden rounded-xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
                    <div className="px-4 py-3" style={{ borderBottom: '1px solid var(--border)' }}>
                        <p className="text-[13px] font-semibold" style={{ color: 'var(--text-1)' }}>Falhas recentes por tenant</p>
                        <p className="text-xs" style={{ color: 'var(--text-3)' }}>Contexto estruturado de falhas de jobs e integrações (WhatsApp, Asaas, Google Calendar), mesmo depois que o job já saiu da fila de falhas.</p>
                    </div>
                    <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                        {falhasRecentes.map(f => (
                            <div key={f.id} className="grid items-center gap-2 px-4 py-3" style={{ gridTemplateColumns: '140px 1fr 160px 140px' }}>
                                <span
                                    className="w-fit rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase"
                                    style={{
                                        background: f.tipo === 'job_failure' ? 'rgba(239,68,68,0.1)' : 'rgba(245,158,11,0.1)',
                                        color: f.tipo === 'job_failure' ? '#f87171' : '#f59e0b',
                                    }}
                                >
                                    {f.tipo === 'job_failure' ? 'Job' : 'Integração'}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-[12px]" style={{ color: 'var(--text-1)' }}>{f.mensagem ?? '—'}</p>
                                    {f.provider && <p className="text-[11px]" style={{ color: 'var(--text-3)' }}>{f.provider}</p>}
                                </div>
                                <span className="truncate text-xs" style={{ color: 'var(--text-3)' }}>{f.tenant ?? '—'}</span>
                                <span className="font-mono text-[11px]" style={{ color: 'var(--text-3)' }}>{fmtDt(f.ocorrido_em)}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
