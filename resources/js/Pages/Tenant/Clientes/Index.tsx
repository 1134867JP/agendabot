import { Head, router } from '@inertiajs/react';
import { useState, useRef } from 'react';
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
    filtros: { busca?: string };
}

const AVATAR_COLORS = [
    ['#6366f1', 'rgba(99,102,241,0.15)'],
    ['#00a884', 'rgba(0,168,132,0.15)'],
    ['#f59e0b', 'rgba(245,158,11,0.15)'],
    ['#ef4444', 'rgba(239,68,68,0.15)'],
    ['#8b5cf6', 'rgba(139,92,246,0.15)'],
    ['#06b6d4', 'rgba(6,182,212,0.15)'],
];

function Avatar({ nome, size = 36 }: { nome: string; size?: number }) {
    const [fg, bg] = AVATAR_COLORS[(nome.charCodeAt(0) || 0) % AVATAR_COLORS.length];
    const initials = nome.trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
    return (
        <div
            className="flex shrink-0 items-center justify-center rounded-full text-xs font-semibold"
            style={{ width: size, height: size, background: bg, color: fg, fontSize: size * 0.35 }}
        >
            {initials}
        </div>
    );
}

function fmtData(iso: string) {
    return new Date(iso).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' });
}

export default function ClientesIndex({ clientes, filtros }: Props) {
    const [busca, setBusca] = useState(filtros.busca ?? '');
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const pesquisar = (valor: string) => {
        setBusca(valor);
        if (timerRef.current) clearTimeout(timerRef.current);
        timerRef.current = setTimeout(() => {
            router.get(route('tenant.clientes.index'), valor ? { busca: valor } : {}, { preserveState: true, replace: true });
        }, 350);
    };

    return (
        <AppLayout title="Clientes" subtitle="Histórico de clientes que agendaram via WhatsApp ou painel">
            <Head title="Clientes" />

            {/* Search */}
            <div className="mb-5">
                <div className="relative max-w-xs">
                    <svg
                        className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2"
                        width={14} height={14} viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"
                        style={{ color: 'var(--text-3)' }}
                    >
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input
                        type="text"
                        value={busca}
                        onChange={e => pesquisar(e.target.value)}
                        placeholder="Buscar por nome ou telefone"
                        className="input pl-9"
                    />
                </div>
            </div>

            <div className="card overflow-hidden">
                {clientes.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-16">
                        <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 110 8 4 4 0 010-8z"/>
                        </svg>
                        <p className="text-sm font-medium text-primary">Nenhum cliente encontrado</p>
                        <p className="text-xs" style={{ color: 'var(--text-3)' }}>
                            {busca ? 'Tente outra busca.' : 'Os clientes aparecem aqui após o primeiro agendamento.'}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead>
                                <tr style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
                                    {['Cliente', 'Telefone', 'Agendamentos', 'Desde'].map(h => (
                                        <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {clientes.data.map(c => (
                                    <tr
                                        key={c.id}
                                        className="table-row-hover cursor-pointer"
                                        style={{ borderBottom: '1px solid var(--border)' }}
                                        onClick={() => router.visit(route('tenant.clientes.show', c.id))}
                                    >
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-3">
                                                <Avatar nome={c.nome} />
                                                <span className="font-medium text-primary">{c.nome}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>
                                            {c.telefone}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                                style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
                                            >
                                                {c.agendamentos_count}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-xs" style={{ color: 'var(--text-3)' }}>
                                            {fmtData(c.created_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {clientes.last_page > 1 && (
                    <div className="flex gap-1 px-4 py-3" style={{ borderTop: '1px solid var(--border)' }}>
                        {clientes.links.map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url}
                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                className="rounded-lg border px-3 py-1.5 text-xs font-medium transition-colors disabled:opacity-40"
                                style={link.active
                                    ? { background: 'var(--accent)', borderColor: 'var(--accent)', color: 'white' }
                                    : { borderColor: 'var(--border-strong)', color: 'var(--text-2)' }
                                }
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {clientes.total > 0 && (
                <p className="mt-3 text-xs" style={{ color: 'var(--text-3)' }}>
                    {clientes.total} cliente{clientes.total !== 1 ? 's' : ''} no total
                </p>
            )}
        </AppLayout>
    );
}
