import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useRef, useState } from 'react';
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
    const [selecionados, setSelecionados] = useState<number[]>([]);
    const [excluindo, setExcluindo] = useState(false);
    const [erroExclusao, setErroExclusao] = useState<string | null>(null);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const idsPagina = useMemo(() => clientes.data.map(cliente => cliente.id), [clientes.data]);
    const todosSelecionados = idsPagina.length > 0 && idsPagina.every(id => selecionados.includes(id));

    const navegar = (params: { busca?: string; segmento?: string }) => {
        setSelecionados([]);
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

    const alternarCliente = (id: number) => {
        setSelecionados(current => current.includes(id) ? current.filter(item => item !== id) : [...current, id]);
    };

    const alternarPagina = () => {
        setSelecionados(current => todosSelecionados
            ? current.filter(id => !idsPagina.includes(id))
            : Array.from(new Set([...current, ...idsPagina])));
    };

    const excluirSelecionados = async () => {
        const quantidade = selecionados.length;
        if (!quantidade || !window.confirm(`Excluir os dados pessoais de ${quantidade} cliente${quantidade > 1 ? 's' : ''}? Os agendamentos serão preservados de forma anonimizada.`)) return;

        setExcluindo(true);
        setErroExclusao(null);
        try {
            await axios.delete(route('tenant.clientes.destroy-bulk'), {
                data: { cliente_ids: selecionados },
            });
            setSelecionados([]);
            router.reload({ only: ['clientes', 'resumo'] });
        } catch {
            setErroExclusao('Não foi possível anonimizar os clientes selecionados. Nenhum lote parcial foi processado.');
        } finally {
            setExcluindo(false);
        }
    };

    const segmentos = [
        { value: '', label: 'Todos', count: resumo.total },
        { value: 'recorrentes', label: 'Recorrentes', count: resumo.recorrentes },
        { value: 'sem_agendamento', label: 'Sem agendamento', count: resumo.sem_agendamento },
    ];

    return (
        <AppLayout title="Clientes" subtitle="Encontre, selecione e resolva ações sem abrir várias telas">
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

            <div className="mb-4 flex flex-col gap-3 sm:flex-row">
                <div className="relative flex-1">
                    <svg className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2" width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" value={busca} onChange={event => pesquisar(event.target.value)} placeholder="Buscar por nome ou telefone…" className="input pl-9" />
                </div>
                <button type="button" onClick={() => router.visit(route('tenant.conversas.index', { nova: 1 }))} className="btn-primary min-h-11 justify-center">
                    Novo cliente
                </button>
            </div>

            {selecionados.length > 0 && (
                <div className="mb-3 flex flex-col gap-3 rounded-xl px-4 py-3 sm:flex-row sm:items-center sm:justify-between" style={{ background: 'var(--accent-light)', border: '1px solid var(--accent)' }}>
                    <div>
                        <p className="text-sm font-medium" style={{ color: 'var(--accent)' }}>{selecionados.length} cliente{selecionados.length > 1 ? 's selecionados' : ' selecionado'}</p>
                        <p className="text-xs" style={{ color: 'var(--text-3)' }}>A anonimização remove dados pessoais e preserva o histórico operacional.</p>
                    </div>
                    <div className="flex gap-2">
                        <button type="button" onClick={() => setSelecionados([])} className="btn-secondary min-h-10">Cancelar</button>
                        <button type="button" onClick={excluirSelecionados} disabled={excluindo} className="min-h-10 rounded-lg px-4 text-sm font-medium text-white disabled:opacity-50" style={{ background: 'var(--danger, #dc2626)' }}>
                            {excluindo ? 'Anonimizando…' : 'Anonimizar selecionados'}
                        </button>
                    </div>
                </div>
            )}

            {erroExclusao && (
                <p className="mb-3 rounded-xl px-4 py-3 text-sm" style={{ background: 'rgba(239,68,68,0.1)', color: '#f87171', border: '1px solid rgba(239,68,68,0.25)' }}>
                    {erroExclusao}
                </p>
            )}

            <div className="card overflow-hidden">
                {clientes.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 px-5 py-12 text-center">
                        <div>
                            <p className="text-sm font-medium text-primary">{busca || filtros.segmento ? 'Nenhum cliente neste filtro' : 'Nenhum cliente cadastrado'}</p>
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                {busca || filtros.segmento ? 'Tente limpar a busca ou escolher outro grupo.' : 'Cadastre pela conversa ou deixe o WhatsApp criar o contato automaticamente.'}
                            </p>
                        </div>
                        {busca || filtros.segmento ? (
                            <button type="button" onClick={() => { setBusca(''); filtrarSegmento(''); }} className="btn-secondary min-h-11">Limpar filtros</button>
                        ) : (
                            <button type="button" onClick={() => router.visit(route('tenant.conversas.index', { nova: 1 }))} className="btn-primary min-h-11">Cadastrar cliente</button>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="flex min-h-12 items-center gap-3 px-4" style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface)' }}>
                            <input type="checkbox" checked={todosSelecionados} onChange={alternarPagina} aria-label="Selecionar clientes desta página" className="h-4 w-4 rounded" />
                            <span className="text-xs font-medium" style={{ color: 'var(--text-3)' }}>Selecionar página</span>
                        </div>
                        <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                            {clientes.data.map(cliente => {
                                const selecionado = selecionados.includes(cliente.id);
                                return (
                                    <div key={cliente.id} className="table-row-hover flex min-h-16 items-center gap-3.5 px-4 py-3.5" style={selecionado ? { background: 'var(--accent-light)' } : undefined}>
                                        <input type="checkbox" checked={selecionado} onChange={() => alternarCliente(cliente.id)} aria-label={`Selecionar ${cliente.nome}`} className="h-4 w-4 shrink-0 rounded" />
                                        <button type="button" onClick={() => router.visit(route('tenant.clientes.show', cliente.id))} className="flex min-w-0 flex-1 items-center gap-3.5 text-left">
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
                                    </div>
                                );
                            })}
                        </div>
                    </>
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
