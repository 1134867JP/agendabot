import { Head, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, PaginatedData } from '@/types';

interface Cliente {
    id: number;
    nome: string;
    telefone: string;
    agendamentos_count: number;
    created_at: string;
}

interface Props extends PageProps {
    clientes: PaginatedData<Cliente>;
    filtros: { busca?: string; segmento?: string };
    resumo: { total: number; recorrentes: number; sem_agendamento: number };
}

const AVATAR_COLORS = [
    ['#6366f1', 'rgba(99,102,241,0.15)'],
    ['#00a884', 'rgba(0,168,132,0.15)'],
    ['#f59e0b', 'rgba(245,158,11,0.15)'],
    ['#ef4444', 'rgba(239,68,68,0.15)'],
    ['#8b5cf6', 'rgba(139,92,246,0.15)'],
    ['#06b6d4', 'rgba(6,182,212,0.15)'],
];

function Avatar({ nome }: { nome: string }) {
    const [fg, bg] = AVATAR_COLORS[(nome.charCodeAt(0) || 0) % AVATAR_COLORS.length];
    const initials = nome.trim().split(/\s+/).slice(0, 2).map(word => word[0]?.toUpperCase() ?? '').join('');
    return <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold" style={{ background: bg, color: fg }}>{initials}</div>;
}

function fmtTelefone(tel: string) {
    const digits = tel.replace(/\D/g, '');
    const local = digits.startsWith('55') && digits.length >= 12 ? digits.slice(2) : digits;
    if (local.length === 11) return '(' + local.slice(0, 2) + ') ' + local.slice(2, 7) + '-' + local.slice(7);
    if (local.length === 10) return '(' + local.slice(0, 2) + ') ' + local.slice(2, 6) + '-' + local.slice(6);
    return digits;
}

export default function ClientesIndex({ clientes, filtros, resumo }: Props) {
    const [busca, setBusca] = useState(filtros.busca ?? '');
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const navegar = (params: { busca?: string; segmento?: string }) => {
        router.get(route('tenant.clientes.index'), params, { preserveState: true, replace: true });
    };

    const pesquisar = (valor: string) => {
        setBusca(valor);
        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => navegar({
            ...(valor ? { busca: valor } : {}),
            ...(filtros.segmento ? { segmento: filtros.segmento } : {}),
        }), 350);
    };

    const filtrarSegmento = (segmento: string) => navegar({
        ...(busca ? { busca } : {}),
        ...(segmento ? { segmento } : {}),
    });

    const segmentos = [
        { value: '', label: 'Todos', count: resumo.total },
        { value: 'recorrentes', label: 'Recorrentes', count: resumo.recorrentes },
        { value: 'sem_agendamento', label: 'Sem agendamento', count: resumo.sem_agendamento },
    ];

    return (
        <AppLayout title="Clientes" subtitle="Histórico, relacionamento e oportunidades de retorno">
            <Head title="Clientes" />

            <div className="mb-4 flex gap-2 overflow-x-auto scroll-hidden pb-1">
                {segmentos.map(item => {
                    const active = (filtros.segmento ?? '') === item.value;
                    return (
                        <button
                            key={item.value}
                            type="button"
                            onClick={() => filtrarSegmento(item.value)}
                            className="flex min-h-10 shrink-0 items-center gap-2 rounded-full px-3 text-xs font-medium transition-colors"
                            style={{
                                background: active ? 'var(--accent-light)' : 'var(--bg-surface)',
                                color: active ? 'var(--accent)' : 'var(--text-2)',
                                border: '1px solid ' + (active ? 'var(--accent)' : 'var(--border)'),
                            }}
                        >
                            {item.label}
                            <span className="rounded-full px-1.5 py-0.5 text-[10px]" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)' }}>{item.count}</span>
                        </button>
                    );
                })}
            </div>

            <div className="mb-4">
                <div className="relative">
                    <svg className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2" width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" value={busca} onChange={event => pesquisar(event.target.value)} placeholder="Buscar por nome ou telefone…" className="input pl-9" />
                </div>
            </div>

            <div className="card overflow-hidden">
                {clientes.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 px-5 py-12 text-center">
                        <div>
                            <p className="text-sm font-medium text-primary">{busca || filtros.segmento ? 'Nenhum cliente neste filtro' : 'Comece seu relacionamento com clientes'}</p>
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                {busca || filtros.segmento ? 'Tente limpar a busca ou escolher outro grupo.' : 'Inicie uma conversa; o contato e o histórico aparecerão aqui automaticamente.'}
                            </p>
                        </div>
                        {busca || filtros.segmento ? (
                            <button type="button" onClick={() => { setBusca(''); filtrarSegmento(''); }} className="btn-secondary min-h-11">Limpar filtros</button>
                        ) : (
                            <button type="button" onClick={() => router.visit(route('tenant.conversas.index', { nova: 1 }))} className="btn-primary min-h-11">Iniciar primeira conversa</button>
                        )}
                    </div>
                ) : (
                    <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                        {clientes.data.map(cliente => (
                            <button key={cliente.id} onClick={() => router.visit(route('tenant.clientes.show', cliente.id))} className="table-row-hover flex min-h-16 w-full items-center gap-3.5 px-4 py-3.5 text-left">
                                <Avatar nome={cliente.nome} />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium text-primary">{cliente.nome}</p>
                                    <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>{fmtTelefone(cliente.telefone)}</p>
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <span className="rounded-full px-2 py-0.5 text-[11px] font-medium" style={{ background: cliente.agendamentos_count > 0 ? 'var(--accent-light)' : 'var(--bg-surface-2)', color: cliente.agendamentos_count > 0 ? 'var(--accent)' : 'var(--text-3)' }}>
                                        {cliente.agendamentos_count > 0 ? cliente.agendamentos_count + ' ag.' : 'Novo'}
                                    </span>
                                    <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}><polyline points="9 18 15 12 9 6"/></svg>
                                </div>
                            </button>
                        ))}
                    </div>
                )}

                {clientes.last_page > 1 && (
                    <div className="flex gap-1 overflow-x-auto px-4 py-3" style={{ borderTop: '1px solid var(--border)' }}>
                        {clientes.links.map((link, index) => (
                            <button
                                key={index}
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                className="min-h-10 shrink-0 rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors disabled:opacity-40"
                                style={link.active ? { background: 'var(--accent)', borderColor: 'var(--accent)', color: 'white' } : { borderColor: 'var(--border-strong)', color: 'var(--text-2)' }}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
