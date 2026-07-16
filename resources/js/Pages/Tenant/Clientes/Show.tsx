import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Tenant } from '@/types';

interface Cliente {
    id: number;
    nome: string;
    telefone: string;
    created_at: string;
    observacoes: string | null;
}

interface AgendamentoCliente {
    id: number;
    data_hora: string | null;
    inicio: string | null;
    duracao_minutos: number;
    status: string;
    origem: string;
    profissional?: { nome: string } | null;
    servico?: { nome: string } | null;
}

interface ConversaCliente {
    id: number;
    status_v2: string;
    ultima_mensagem_em: string | null;
    updated_at: string;
}

interface Props extends PageProps {
    cliente: Cliente;
    agendamentos: AgendamentoCliente[];
    conversas: ConversaCliente[];
}

const AVATAR_COLORS = [
    ['#6366f1', 'rgba(99,102,241,0.15)'],
    ['#00a884', 'rgba(0,168,132,0.15)'],
    ['#f59e0b', 'rgba(245,158,11,0.15)'],
    ['#ef4444', 'rgba(239,68,68,0.15)'],
    ['#8b5cf6', 'rgba(139,92,246,0.15)'],
    ['#06b6d4', 'rgba(6,182,212,0.15)'],
];

function Avatar({ nome, size = 44 }: { nome: string; size?: number }) {
    const [fg, bg] = AVATAR_COLORS[(nome.charCodeAt(0) || 0) % AVATAR_COLORS.length];
    const initials = nome.trim().split(/\s+/).slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('');
    return (
        <div
            className="flex shrink-0 items-center justify-center rounded-full font-semibold"
            style={{ width: size, height: size, background: bg, color: fg, fontSize: size * 0.35 }}
        >
            {initials}
        </div>
    );
}

function fmtTelefone(tel: string): string {
    const digits = tel.replace(/\D/g, '');
    const local = digits.startsWith('55') && digits.length >= 12 ? digits.slice(2) : digits;
    if (local.length === 11) return `(${local.slice(0, 2)}) ${local.slice(2, 7)}-${local.slice(7)}`;
    if (local.length === 10) return `(${local.slice(0, 2)}) ${local.slice(2, 6)}-${local.slice(6)}`;
    return digits;
}

const STATUS_BADGE: Record<string, string> = {
    agendado:   'badge-green',
    confirmado: 'badge-green',
    cancelado:  'badge-red',
    concluido:  'badge-gray',
};
const STATUS_LABEL: Record<string, string> = {
    agendado:   'Agendado',
    confirmado: 'Confirmado',
    cancelado:  'Cancelado',
    concluido:  'Concluído',
};

const CONVERSA_STATUS_LABEL: Record<string, string> = {
    ativa:                    'Ativa',
    aguardando_humano:        'Aguardando equipe',
    em_atendimento_humano:    'Em atendimento',
    encerrada:                'Encerrada',
};

const CONVERSA_STATUS_BADGE: Record<string, string> = {
    ativa:                    'badge-green',
    aguardando_humano:        'badge-yellow',
    em_atendimento_humano:    'badge-blue',
    encerrada:                'badge-gray',
};

function fmtDt(iso: string | null) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', year: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function fmtRelativo(iso: string | null) {
    if (!iso) return '—';
    const diff = Date.now() - new Date(iso).getTime();
    const min = Math.floor(diff / 60000);
    if (min < 1) return 'agora';
    if (min < 60) return `${min}min atrás`;
    const h = Math.floor(min / 60);
    if (h < 24) return `${h}h atrás`;
    return `${Math.floor(h / 24)}d atrás`;
}

function DeleteModal({ nome, onConfirm, onCancel }: { nome: string; onConfirm: () => void; onCancel: () => void }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 backdrop-blur-sm">
            <div className="w-full max-w-sm rounded-2xl p-6 shadow-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                <div className="mb-4 flex h-11 w-11 items-center justify-center rounded-full" style={{ background: 'rgba(239,68,68,0.1)' }}>
                    <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" className="text-red-400">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/>
                    </svg>
                </div>
                <h3 className="text-base font-semibold text-primary">Excluir cliente?</h3>
                <p className="mt-1.5 text-sm" style={{ color: 'var(--text-3)' }}>
                    <strong className="text-primary">{nome}</strong> e todas as suas conversas serão excluídos permanentemente. Esta ação não pode ser desfeita.
                </p>
                <div className="mt-5 flex gap-2.5">
                    <button
                        onClick={onConfirm}
                        className="flex-1 rounded-xl py-2.5 text-sm font-medium text-white transition-opacity hover:opacity-90"
                        style={{ background: '#ef4444' }}
                    >
                        Excluir
                    </button>
                    <button
                        onClick={onCancel}
                        className="flex-1 rounded-xl py-2.5 text-sm font-medium transition-colors"
                        style={{ background: 'var(--bg-surface-2)', border: '1px solid var(--border-strong)', color: 'var(--text-2)' }}
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function ClienteShow({ cliente, agendamentos, conversas }: Props) {
    const [showDelete, setShowDelete] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const { currentTenant } = usePage<PageProps<{ currentTenant?: Tenant | null }>>().props;
    const modoAgendamento = (currentTenant?.modo_bot ?? 'agendamento') === 'agendamento';
    const notesForm = useForm({
        nome: cliente.nome,
        observacoes: cliente.observacoes ?? '',
    });

    const salvarObservacoes = (event: React.FormEvent) => {
        event.preventDefault();
        notesForm.patch(route('tenant.clientes.update', cliente.id), { preserveScroll: true });
    };

    const totalAgendamentos = agendamentos.length;
    const realizados = agendamentos.filter(a => ['agendado', 'confirmado', 'concluido'].includes(a.status)).length;

    const handleDelete = () => {
        setDeleting(true);
        router.delete(route('tenant.clientes.destroy', cliente.id), {
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <AppLayout title={cliente.nome} subtitle={`Cliente desde ${new Date(cliente.created_at).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}`}>
            <Head title={cliente.nome} />

            {/* Back + Delete row */}
            <div className="mb-5 flex items-center justify-between">
                <button
                    onClick={() => router.visit(route('tenant.clientes.index'))}
                    className="flex items-center gap-1.5 text-xs transition-colors hover:text-[var(--text-1)]"
                    style={{ color: 'var(--text-3)' }}
                >
                    <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Voltar
                </button>

                <button
                    onClick={() => setShowDelete(true)}
                    className="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium text-red-400 transition-colors hover:bg-red-400/10"
                >
                    <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                    </svg>
                    Excluir
                </button>
            </div>

            {/* Header card */}
            <div className="card mb-5 p-5">
                <div className="flex items-center gap-4">
                    <Avatar nome={cliente.nome} size={52} />
                    <div className="min-w-0 flex-1">
                        <h2 className="truncate text-lg font-semibold text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                            {cliente.nome}
                        </h2>
                        <p className="mt-0.5 text-sm" style={{ color: 'var(--text-3)' }}>{fmtTelefone(cliente.telefone)}</p>
                    </div>
                </div>

                <div className="mt-4 grid gap-2 sm:grid-cols-2">
                    {modoAgendamento && (
                        <button type="button" onClick={() => router.visit(route('tenant.agendamentos.index', { novo: 1, cliente: cliente.telefone }))} className="btn-primary min-h-11">
                            Novo agendamento
                        </button>
                    )}
                    <button type="button" onClick={() => router.visit(route('tenant.conversas.index', { nova: 1, telefone: cliente.telefone }))} className="btn-secondary min-h-11">
                        Iniciar conversa
                    </button>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-3">
                    <div className="rounded-xl p-3 text-center" style={{ background: 'var(--bg-surface-2)' }}>
                        <p className="text-2xl font-bold text-primary">{totalAgendamentos}</p>
                        <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>agendamentos</p>
                    </div>
                    <div className="rounded-xl p-3 text-center" style={{ background: 'var(--bg-surface-2)' }}>
                        <p className="text-2xl font-bold text-primary">{realizados}</p>
                        <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>realizados</p>
                    </div>
                </div>
            </div>

            <form onSubmit={salvarObservacoes} className="card mb-4 p-4 sm:p-5">
                <div className="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 className="text-sm font-semibold text-primary">Anotações internas</h3>
                        <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>Preferências e contexto para o próximo atendimento.</p>
                    </div>
                    {notesForm.wasSuccessful && <span className="text-xs" style={{ color: 'var(--jade)' }}>Salvo</span>}
                </div>
                <textarea
                    value={notesForm.data.observacoes}
                    onChange={event => notesForm.setData('observacoes', event.target.value)}
                    className="input min-h-24 resize-y"
                    maxLength={2000}
                    placeholder="Ex.: prefere horários pela manhã, serviço habitual, observações importantes…"
                />
                <div className="mt-3 flex items-center justify-between gap-3">
                    <span className="text-[10px]" style={{ color: 'var(--text-3)' }}>{notesForm.data.observacoes.length}/2000</span>
                    <button type="submit" disabled={notesForm.processing} className="btn-primary min-h-11">
                        {notesForm.processing ? 'Salvando…' : 'Salvar anotações'}
                    </button>
                </div>
            </form>

            {/* Agendamentos */}
            {modoAgendamento && <div className="card mb-4 overflow-hidden">
                <div className="flex items-center justify-between px-5 py-3.5" style={{ borderBottom: '1px solid var(--border)' }}>
                    <h3 className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                        Agendamentos
                    </h3>
                    <span className="rounded-full px-2 py-0.5 text-xs font-medium" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)' }}>
                        {totalAgendamentos}
                    </span>
                </div>

                {agendamentos.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-10">
                        <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <p className="text-xs" style={{ color: 'var(--text-3)' }}>Nenhum agendamento</p>
                        <button type="button" onClick={() => router.visit(route('tenant.agendamentos.index', { novo: 1, cliente: cliente.telefone }))} className="btn-primary mt-2 min-h-11 text-xs">Criar agendamento</button>
                    </div>
                ) : (
                    <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                        {agendamentos.map(a => (
                            <div key={a.id} className="flex items-center justify-between gap-3 px-5 py-3">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium text-primary">
                                        {a.servico?.nome ?? a.profissional?.nome ?? 'Serviço'}
                                    </p>
                                    <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                        {fmtDt(a.data_hora ?? a.inicio)}
                                        {a.duracao_minutos ? ` · ${a.duracao_minutos}min` : ''}
                                    </p>
                                </div>
                                <span className={`badge shrink-0 ${STATUS_BADGE[a.status] ?? 'badge-gray'}`}>
                                    {STATUS_LABEL[a.status] ?? a.status}
                                </span>
                            </div>
                        ))}
                    </div>
                )}
            </div>}

            {/* Conversas */}
            <div className="card overflow-hidden">
                <div className="flex items-center justify-between px-5 py-3.5" style={{ borderBottom: '1px solid var(--border)' }}>
                    <h3 className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                        Conversas
                    </h3>
                    <span className="rounded-full px-2 py-0.5 text-xs font-medium" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)' }}>
                        {conversas.length}
                    </span>
                </div>

                {conversas.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-10">
                        <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                        </svg>
                        <p className="text-xs" style={{ color: 'var(--text-3)' }}>Nenhuma conversa</p>
                        <button type="button" onClick={() => router.visit(route('tenant.conversas.index', { nova: 1, telefone: cliente.telefone }))} className="btn-primary mt-2 min-h-11 text-xs">Iniciar conversa</button>
                    </div>
                ) : (
                    <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                        {conversas.map(c => (
                            <button
                                key={c.id}
                                className="table-row-hover flex w-full items-center justify-between gap-3 px-5 py-3 text-left"
                                onClick={() => router.visit(route('tenant.conversas.index', { conversa_id: c.id }))}
                            >
                                <div className="min-w-0">
                                    <span className={`badge ${CONVERSA_STATUS_BADGE[c.status_v2] ?? 'badge-gray'}`}>
                                        {CONVERSA_STATUS_LABEL[c.status_v2] ?? c.status_v2}
                                    </span>
                                    <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                        {fmtRelativo(c.ultima_mensagem_em ?? c.updated_at)}
                                    </p>
                                </div>
                                <svg width={12} height={12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)', flexShrink: 0 }}>
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {showDelete && (
                <DeleteModal
                    nome={cliente.nome}
                    onConfirm={handleDelete}
                    onCancel={() => setShowDelete(false)}
                />
            )}

            {deleting && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/30 backdrop-blur-sm">
                    <div className="rounded-2xl p-6" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                        <svg className="mx-auto h-6 w-6 animate-spin" fill="none" viewBox="0 0 24 24" style={{ color: 'var(--accent)' }}>
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        <p className="mt-3 text-sm" style={{ color: 'var(--text-3)' }}>Excluindo…</p>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
