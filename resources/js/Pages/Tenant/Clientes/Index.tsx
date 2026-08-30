import { Head, router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { useMemo, useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/Modal';
import EmptyState from '@/Components/UI/EmptyState';
import FormField from '@/Components/UI/FormField';
import StatusBadge from '@/Components/UI/StatusBadge';
import Toolbar from '@/Components/UI/Toolbar';
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

function maskTelefone(value: string) {
    const digits = value.replace(/\D/g, '').slice(0, 11);
    if (digits.length <= 2) return digits;
    if (digits.length <= 6) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

export default function ClientesIndex({ clientes, filtros, resumo }: Props) {
    const [busca, setBusca] = useState(filtros.busca ?? '');
    const [selecionados, setSelecionados] = useState<number[]>([]);
    const [excluindo, setExcluindo] = useState(false);
    const [erroExclusao, setErroExclusao] = useState<string | null>(null);
    const [cadastroAberto, setCadastroAberto] = useState(false);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const cadastro = useForm({ nome: '', telefone: '', observacoes: '' });

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

    const abrirCadastro = () => {
        cadastro.reset();
        cadastro.clearErrors();
        setCadastroAberto(true);
    };

    const salvarCliente = (event: React.FormEvent) => {
        event.preventDefault();
        cadastro.post(route('tenant.clientes.store'), {
            onSuccess: () => {
                cadastro.reset();
                setCadastroAberto(false);
            },
        });
    };

    const segmentos = [
        { value: '', label: 'Todos', count: resumo.total },
        { value: 'recorrentes', label: 'Recorrentes', count: resumo.recorrentes },
        { value: 'sem_agendamento', label: 'Sem agendamento', count: resumo.sem_agendamento },
    ];

    return (
        <AppLayout title="Clientes" subtitle="Encontre, selecione e resolva ações sem abrir várias telas">
            <Head title="Clientes" />

            <Toolbar className="mb-4">
                <div className="min-w-0 flex-1 space-y-3">
                    <div className="flex gap-2 overflow-x-auto scroll-hidden pb-1">
                        {segmentos.map(item => {
                            const active = (filtros.segmento ?? '') === item.value;
                            return (
                                <button
                                    key={item.value}
                                    type="button"
                                    onClick={() => filtrarSegmento(item.value)}
                                    className="flex min-h-10 shrink-0 items-center gap-2 rounded-full px-3 text-xs font-medium transition-colors"
                                    style={{
                                        background: active ? 'var(--accent-light)' : 'var(--bg-surface-2)',
                                        color: active ? 'var(--accent)' : 'var(--text-2)',
                                        border: '1px solid ' + (active ? 'var(--accent)' : 'var(--border)'),
                                    }}
                                >
                                    {item.label}
                                    <span className="rounded-full px-1.5 py-0.5 text-[10px]" style={{ background: 'var(--bg-surface)', color: 'var(--text-3)' }}>{item.count}</span>
                                </button>
                            );
                        })}
                    </div>
                    <div className="relative">
                        <svg className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2" width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="search" value={busca} onChange={event => pesquisar(event.target.value)} aria-label="Buscar clientes por nome ou telefone" placeholder="Buscar por nome ou telefone…" className="input pl-9" />
                    </div>
                </div>
                <button type="button" onClick={abrirCadastro} className="btn-primary min-h-11 justify-center">
                    Novo cliente
                </button>
            </Toolbar>

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
                    <EmptyState
                        title={busca || filtros.segmento ? 'Nenhum cliente neste filtro' : 'Nenhum cliente cadastrado'}
                        description={busca || filtros.segmento ? 'Tente limpar a busca ou escolher outro grupo.' : 'Cadastre pela conversa ou deixe o WhatsApp criar o contato automaticamente.'}
                        action={busca || filtros.segmento ? (
                            <button type="button" onClick={() => { setBusca(''); filtrarSegmento(''); }} className="btn-secondary min-h-11">Limpar filtros</button>
                        ) : (
                            <button type="button" onClick={abrirCadastro} className="btn-primary min-h-11">Cadastrar cliente</button>
                        )}
                    />
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
                                                <StatusBadge tone={cliente.agendamentos_count > 0 ? 'brand' : 'neutral'}>
                                                    {cliente.agendamentos_count > 0 ? cliente.agendamentos_count + ' ag.' : 'Novo'}
                                                </StatusBadge>
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

            <Modal show={cadastroAberto} maxWidth="md" onClose={() => setCadastroAberto(false)}>
                    <form onSubmit={salvarCliente} className="p-5">
                        <div className="mb-5 flex items-start justify-between gap-3">
                            <div>
                                <h2 className="text-lg font-semibold text-primary">Novo cliente</h2>
                                <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>Cadastre somente o necessário para agendar ou iniciar uma conversa.</p>
                            </div>
                            <button type="button" onClick={() => setCadastroAberto(false)} className="flex h-10 w-10 items-center justify-center rounded-full hover:bg-[var(--bg-surface-2)]" style={{ color: 'var(--text-3)' }} aria-label="Fechar cadastro">
                                <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M18 6L6 18M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div className="space-y-4">
                            <FormField label="Nome" htmlFor="novo-cliente-nome" error={cadastro.errors.nome} required>
                                <input id="novo-cliente-nome" autoFocus value={cadastro.data.nome} onChange={event => cadastro.setData('nome', event.target.value)} className="input" maxLength={120} />
                            </FormField>
                            <FormField label="Telefone com DDD" htmlFor="novo-cliente-telefone" error={cadastro.errors.telefone} required>
                                <input id="novo-cliente-telefone" value={cadastro.data.telefone} onChange={event => cadastro.setData('telefone', maskTelefone(event.target.value))} className="input" inputMode="tel" autoComplete="tel" placeholder="(54) 99999-1234" />
                            </FormField>
                            <FormField label="Observação" htmlFor="novo-cliente-observacoes" hint="Opcional. Use para preferências ou informações úteis no atendimento.">
                                <textarea id="novo-cliente-observacoes" value={cadastro.data.observacoes} onChange={event => cadastro.setData('observacoes', event.target.value)} className="input min-h-20 resize-none" maxLength={2000} />
                            </FormField>
                        </div>
                        <div className="mt-5 flex gap-2">
                            <button type="button" onClick={() => setCadastroAberto(false)} className="btn-secondary min-h-11 flex-1 justify-center">Cancelar</button>
                            <button type="submit" disabled={cadastro.processing || !cadastro.data.nome || !cadastro.data.telefone} className="btn-primary min-h-11 flex-1 justify-center">
                                {cadastro.processing ? 'Salvando…' : 'Salvar cliente'}
                            </button>
                        </div>
                    </form>
            </Modal>
        </AppLayout>
    );
}
