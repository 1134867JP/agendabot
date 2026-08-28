import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';

type QueueStatus = 'waiting' | 'processing' | 'delayed';
interface FailedJob { id: number; job: string; queue: string; exception: string; failed_at: string; }
interface QueuedJob { id: number; job: string; queue: string; status: QueueStatus; attempts: number; created_at: string; available_at: string; reserved_at: string | null; }
interface RuntimeComponent { status: 'ok' | 'stale' | 'missing' | 'unavailable'; last_seen_at: string | null; age_seconds: number | null; }
interface RuntimeStatus { ready: boolean; workers: Record<string, RuntimeComponent>; scheduler: RuntimeComponent; }
interface QueueStats { failed: number; pending: number; processing: number; delayed: number; total: number; oldest_wait_seconds: number; worker_status: string; worker_last_seen_at: string | null; runtime: RuntimeStatus; }
interface FalhaRecente { id: number; tipo: string; tenant: string | null; mensagem: string | null; ocorrido_em: string; }
interface Props extends PageProps { failed: FailedJob[]; queue: QueueStats; queuedJobs: QueuedJob[]; falhasRecententes?: FalhaRecente[]; falhasRecentes: FalhaRecente[]; }

const labels: Record<QueueStatus, string> = { waiting: 'Aguardando', processing: 'Em execução', delayed: 'Atrasado' };
const colors: Record<QueueStatus, string> = { waiting: '#a5b4fc', processing: '#34d399', delayed: '#fbbf24' };
const date = (value: string | null) => value ? new Date(value).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—';

export default function Jobs({ failed, queue, queuedJobs, falhasRecentes }: Props) {
    const [paused, setPaused] = useState(false);
    const [expanded, setExpanded] = useState<number | null>(null);
    useEffect(() => {
        if (paused) return;
        const timer = window.setInterval(() => router.reload({ only: ['failed', 'queue', 'queuedJobs', 'falhasRecentes'] }), 5000);
        return () => window.clearInterval(timer);
    }, [paused]);
    const cards = [['Aguardando', queue.pending, '#a5b4fc'], ['Em execução', queue.processing, '#34d399'], ['Atrasados', queue.delayed, '#fbbf24'], ['Falhos', queue.failed, '#f87171'], ['Total', queue.total, 'var(--text-1)']] as const;
    const runtime = [
        ...Object.entries(queue.runtime.workers).map(([name, component]) => [`Worker ${name}`, component] as const),
        ['Scheduler', queue.runtime.scheduler] as const,
    ];
    return <AppLayout title="Fila e processamento" subtitle="Acompanhe workers, jobs, integrações e falhas">
        <Head title="Jobs" />
        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap gap-x-4 gap-y-2">
                {runtime.map(([label, component]) => <span key={label} className="text-xs" style={{ color: 'var(--text-3)' }}>
                    <span className="mr-2 inline-block h-2 w-2 rounded-full" style={{ background: component.status === 'ok' ? '#34d399' : component.status === 'stale' ? '#fbbf24' : '#f87171' }} />
                    {label} {component.status === 'ok' ? 'online' : component.status === 'stale' ? 'atrasado' : component.status === 'unavailable' ? 'indisponível' : 'sem heartbeat'}
                    {component.last_seen_at ? ` · visto ${date(component.last_seen_at)}` : ''}
                </span>)}
            </div>
            <button onClick={() => setPaused(value => !value)} className="rounded-lg px-3 py-2 text-xs" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)', color: paused ? 'var(--text-3)' : 'var(--jade)' }}>{paused ? 'Retomar atualização' : 'Atualização automática'}</button>
        </div>
        <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-5">{cards.map(([label, value, color]) => <div key={label} className="rounded-xl p-4" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}><p className="text-[11px]" style={{ color: 'var(--text-3)' }}>{label}</p><p className="mt-1 text-2xl font-semibold" style={{ color }}>{value}</p></div>)}</div>
        <section className="mb-6 overflow-hidden rounded-xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
            <div className="border-b px-4 py-3" style={{ borderColor: 'var(--border)' }}><p className="text-sm font-semibold" style={{ color: 'var(--text-1)' }}>Jobs na fila</p><p className="text-xs" style={{ color: 'var(--text-3)' }}>Aguardando, reservados pelo worker e atrasados</p></div>
            {queuedJobs.length === 0 ? <p className="px-4 py-10 text-center text-sm" style={{ color: 'var(--text-3)' }}>A fila está vazia</p> : <div className="divide-y" style={{ borderColor: 'var(--border)' }}>{queuedJobs.map(job => <div key={job.id} className="flex flex-wrap items-center gap-3 px-4 py-3"><span className="w-24 text-[11px]" style={{ color: colors[job.status] }}>{labels[job.status]}</span><div className="min-w-0 flex-1"><p className="truncate text-xs" style={{ color: 'var(--text-1)' }}>{job.job}</p><p className="text-[11px]" style={{ color: 'var(--text-3)' }}>#{job.id} · fila {job.queue} · tentativa {job.attempts}</p></div><span className="text-[11px]" style={{ color: 'var(--text-3)' }}>{job.status === 'processing' ? `reservado ${date(job.reserved_at)}` : `criado ${date(job.created_at)}`}</span></div>)}</div>}
        </section>
        <section className="mb-6 overflow-hidden rounded-xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
            <div className="border-b px-4 py-3" style={{ borderColor: 'var(--border)' }}><p className="text-sm font-semibold" style={{ color: 'var(--text-1)' }}>Jobs falhos</p><p className="text-xs" style={{ color: 'var(--text-3)' }}>Falhas persistidas para análise operacional</p></div>
            {failed.length === 0 ? <p className="px-4 py-10 text-center text-sm" style={{ color: 'var(--text-3)' }}>Nenhum job falho</p> : <div className="divide-y" style={{ borderColor: 'var(--border)' }}>{failed.map(job => <div key={job.id} className="px-4 py-3"><button onClick={() => setExpanded(expanded === job.id ? null : job.id)} className="w-full text-left"><p className="truncate text-xs" style={{ color: 'var(--text-1)' }}>#{job.id} · {job.job}</p><p className="text-[11px]" style={{ color: 'var(--text-3)' }}>{job.queue} · {date(job.failed_at)}</p></button>{expanded === job.id && <pre className="mt-3 max-h-56 overflow-auto rounded-lg p-3 text-[11px]" style={{ background: 'rgba(0,0,0,.25)', color: '#fca5a5', whiteSpace: 'pre-wrap' }}>{job.exception}</pre>}</div>)}</div>}
        </section>
        {falhasRecentes.length > 0 && <section className="overflow-hidden rounded-xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}><div className="border-b px-4 py-3" style={{ borderColor: 'var(--border)' }}><p className="text-sm font-semibold" style={{ color: 'var(--text-1)' }}>Falhas recentes por tenant</p></div><div className="divide-y" style={{ borderColor: 'var(--border)' }}>{falhasRecentes.map(f => <div key={f.id} className="flex flex-wrap items-center gap-3 px-4 py-3"><span className="rounded px-2 py-1 text-[10px] uppercase" style={{ background: 'rgba(239,68,68,.1)', color: '#f87171' }}>{f.tipo === 'job_failure' ? 'Job' : 'Integração'}</span><span className="flex-1 truncate text-xs" style={{ color: 'var(--text-1)' }}>{f.mensagem || 'Falha registrada'}</span><span className="text-xs" style={{ color: 'var(--text-3)' }}>{f.tenant || 'Global'} · {date(f.ocorrido_em)}</span></div>)}</div></section>}
    </AppLayout>;
}
