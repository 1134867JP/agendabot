import { Head, router, useForm } from '@inertiajs/react';
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
    if (local.length === 11) return `(${local.slice(0, 2)}) ${local.slice(2, 7)}-${local.slice(7)}`;
    if (local.length === 10) return `(${local.slice(0, 2)}) ${local.slice(2, 6)}-${local.slice(6)}`;
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

    const excluirSelecionados = async () => {
        if (!selecionados.length || !window.confirm(`Excluir os dados pessoais de ${selecionados.length} cliente(s)? O histórico será preservado de forma anonimizada.`)) return;
        setExcluindo(true);
        try {
            for (const id of selecionados) await axios.delete(route('tenant.clientes.destroy', id));
            setSelecionados([]);
            router.reload({ only: ['clientes', 'resumo'] });
        } finally {
            setExcluindo(false);
        }
    };

    const salvarCliente = (event: React.FormEvent) => {
        event.preventDefault();
        cadastro.post(route('tenant.clientes.store'));
    };

    const segmentos = [
        { value: '', label: 'Todos', count: resumo.total },
        { value: 'recorrentes', label: 'Recorrentes', count: resumo.recorrentes },
        { value: 'sem_agendamento', label: 'Sem agendamento', count: resumo.sem_agendamento },
    ];

    return (
        <AppLayout title="Clientes" subtitle="Encontre, cadastre e resolva ações sem sair da tela">
            <Head title="Clientes" />

            <div className="mb-4 flex gap-2 overflow-x-auto pb-1">
                {segmentos.map(item => {
                    const active = (filtros.segmento ?? '') === item.value;
                    return <button key={item.value} type="button" onClick={() => navegar({ ...(busca ? { busca } : {}), ...(item.value ? { segmento: item.value } : {}) })} className="flex min-h-10 shrink-0 items-center gap-2 rounded-full px-3 text-xs font-medium" style={{ background: active ? 'var(--accent-light)' : 'var(--bg-surface)', color: active ? 'var(--accent)' : 'var(--text-2)', border: `1px solid ${active ? 'var(--accent)' : 'var(--border)'}` }}>{item.label}<span className="rounded-full px-1.5 py-0.5 text-[10px]" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)' }}>{item.count}</span></button>;
                })}
            </div>

            <div className="mb-4 flex flex-col gap-3 sm:flex-row">
                <input type="text" value={busca} onChange={event => pesquisar(event.target.value)} placeholder="Buscar por nome ou telefone…" className="input flex-1" />
                <button type="button" onClick={() => { cadastro.reset(); cadastro.clearErrors(); setCadastroAberto(true); }} className="btn-primary min-h-11 justify-center">Novo cliente</button>
            </div>

            {selecionados.length > 0 && <div className="mb-3 flex flex-col gap-3 rounded-xl px-4 py-3 sm:flex-row sm:items-center sm:justify-between" style={{ background: 'var(--accent-light)', border: '1px solid var(--accent)' }}><div><p className="text-sm font-medium" style={{ color: 'var(--accent)' }}>{selecionados.length} selecionado(s)</p><p className="text-xs" style={{ color: 'var(--text-3)' }}>Os dados pessoais serão removidos e o histórico ficará anonimizado.</p></div><div className="flex gap-2"><button type="button" onClick={() => setSelecionados([])} className="btn-secondary min-h-10">Cancelar</button><button type="button" onClick={excluirSelecionados} disabled={excluindo} className="min-h-10 rounded-lg bg-red-600 px-4 text-sm font-medium text-white disabled:opacity-50">{excluindo ? 'Excluindo…' : 'Excluir'}</button></div></div>}

            <div className="card overflow-hidden">
                {clientes.data.length === 0 ? <div className="flex flex-col items-center gap-3 px-5 py-12 text-center"><div><p className="text-sm font-medium text-primary">{busca || filtros.segmento ? 'Nenhum cliente neste filtro' : 'Nenhum cliente cadastrado'}</p><p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>{busca || filtros.segmento ? 'Tente limpar a busca ou escolher outro grupo.' : 'Cadastre em poucos segundos ou deixe o WhatsApp criar o contato automaticamente.'}</p></div><button type="button" onClick={() => setCadastroAberto(true)} className="btn-primary min-h-11">Cadastrar cliente</button></div> : <>
                    <div className="flex min-h-12 items-center gap-3 px-4" style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface)' }}><input type="checkbox" checked={todosSelecionados} onChange={() => setSelecionados(todosSelecionados ? [] : idsPagina)} className="h-4 w-4 rounded" /><span className="text-xs font-medium" style={{ color: 'var(--text-3)' }}>Selecionar página</span></div>
                    <div className="divide-y" style={{ borderColor: 'var(--border)' }}>{clientes.data.map(cliente => { const selecionado = selecionados.includes(cliente.id); return <div key={cliente.id} className="table-row-hover flex min-h-16 items-center gap-3.5 px-4 py-3.5" style={selecionado ? { background: 'var(--accent-light)' } : undefined}><input type="checkbox" checked={selecionado} onChange={() => setSelecionados(current => current.includes(cliente.id) ? current.filter(id => id !== cliente.id) : [...current, cliente.id])} className="h-4 w-4 shrink-0 rounded" /><button type="button" onClick={() => router.visit(route('tenant.clientes.show', cliente.id))} className="flex min-w-0 flex-1 items-center gap-3.5 text-left"><Avatar nome={cliente.nome} /><div className="min-w-0 flex-1"><p className="truncate text-sm font-medium text-primary">{cliente.nome}</p><p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>{fmtTelefone(cliente.telefone)}</p></div><span className="rounded-full px-2 py-0.5 text-[11px] font-medium" style={{ background: cliente.agendamentos_count > 0 ? 'var(--accent-light)' : 'var(--bg-surface-2)', color: cliente.agendamentos_count > 0 ? 'var(--accent)' : 'var(--text-3)' }}>{cliente.agendamentos_count > 0 ? `${cliente.agendamentos_count} ag.` : 'Novo'}</span></button></div>; })}</div>
                </>}
            </div>

            {cadastroAberto && <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"><form onSubmit={salvarCliente} className="w-full rounded-t-2xl p-5 shadow-2xl sm:max-w-md sm:rounded-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}><div className="mb-5 flex items-start justify-between gap-3"><div><h2 className="text-base font-semibold text-primary">Novo cliente</h2><p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>Só o necessário. Depois você pode agendar ou iniciar uma conversa.</p></div><button type="button" onClick={() => setCadastroAberto(false)} className="text-xl" style={{ color: 'var(--text-3)' }}>×</button></div><div className="space-y-4"><div><label className="label">Nome</label><input autoFocus value={cadastro.data.nome} onChange={event => cadastro.setData('nome', event.target.value)} className="input" maxLength={120} />{cadastro.errors.nome && <p className="mt-1 text-xs text-red-400">{cadastro.errors.nome}</p>}</div><div><label className="label">Telefone com DDD</label><input value={cadastro.data.telefone} onChange={event => cadastro.setData('telefone', maskTelefone(event.target.value))} className="input" inputMode="tel" placeholder="(54) 99999-1234" />{cadastro.errors.telefone && <p className="mt-1 text-xs text-red-400">{cadastro.errors.telefone}</p>}</div><div><label className="label">Observação <span style={{ color: 'var(--text-3)' }}>(opcional)</span></label><textarea value={cadastro.data.observacoes} onChange={event => cadastro.setData('observacoes', event.target.value)} className="input min-h-20 resize-none" maxLength={2000} /></div></div><div className="mt-5 flex gap-2"><button type="button" onClick={() => setCadastroAberto(false)} className="btn-secondary min-h-11 flex-1 justify-center">Cancelar</button><button type="submit" disabled={cadastro.processing} className="btn-primary min-h-11 flex-1 justify-center">{cadastro.processing ? 'Salvando…' : 'Salvar cliente'}</button></div></form></div>}
        </AppLayout>
    );
}
