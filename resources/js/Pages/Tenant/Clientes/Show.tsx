import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';

interface Cliente {
    id: number;
    nome: string;
    telefone: string;
    created_at: string;
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
    ativa:               'Ativa',
    aguardando_humano:   'Aguardando humano',
    em_atendimento_humano: 'Em atendimento',
    encerrada:           'Encerrada',
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

export default function ClienteShow({ cliente, agendamentos, conversas }: Props) {
    const totalAgendamentos = agendamentos.length;
    const confirmados = agendamentos.filter(a => ['agendado', 'confirmado', 'concluido'].includes(a.status)).length;

    return (
        <AppLayout title={cliente.nome} subtitle={`Cliente desde ${new Date(cliente.created_at).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}`}>
            <Head title={cliente.nome} />

            {/* Back */}
            <button
                onClick={() => router.visit(route('tenant.clientes.index'))}
                className="mb-5 flex items-center gap-1.5 text-xs transition-colors hover:text-[var(--text-1)]"
                style={{ color: 'var(--text-3)' }}
            >
                <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
                Voltar para clientes
            </button>

            {/* Header card */}
            <div className="card mb-5 flex items-center gap-5 p-6">
                <Avatar nome={cliente.nome} size={56} />
                <div className="flex-1 min-w-0">
                    <h2 className="text-xl font-semibold text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                        {cliente.nome}
                    </h2>
                    <p className="mt-0.5 text-sm" style={{ color: 'var(--text-3)' }}>{cliente.telefone}</p>
                </div>
                <div className="hidden sm:flex gap-6 text-center">
                    <div>
                        <p className="text-2xl font-bold text-primary">{totalAgendamentos}</p>
                        <p className="text-xs" style={{ color: 'var(--text-3)' }}>agendamentos</p>
                    </div>
                    <div>
                        <p className="text-2xl font-bold text-primary">{confirmados}</p>
                        <p className="text-xs" style={{ color: 'var(--text-3)' }}>realizados</p>
                    </div>
                </div>
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                {/* Agendamentos */}
                <div className="card overflow-hidden">
                    <div className="px-5 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                            Agendamentos recentes
                        </h3>
                    </div>

                    {agendamentos.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 py-10">
                            <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <p className="text-xs" style={{ color: 'var(--text-3)' }}>Nenhum agendamento</p>
                        </div>
                    ) : (
                        <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                            {agendamentos.map(a => (
                                <div key={a.id} className="flex items-start justify-between gap-3 px-5 py-3">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-primary truncate">
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
                </div>

                {/* Conversas */}
                <div className="card overflow-hidden">
                    <div className="px-5 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                        <h3 className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                            Conversas
                        </h3>
                    </div>

                    {conversas.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 py-10">
                            <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                            </svg>
                            <p className="text-xs" style={{ color: 'var(--text-3)' }}>Nenhuma conversa</p>
                        </div>
                    ) : (
                        <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                            {conversas.map(c => (
                                <div
                                    key={c.id}
                                    className="table-row-hover flex cursor-pointer items-center justify-between gap-3 px-5 py-3"
                                    onClick={() => router.visit(route('tenant.conversas.index', { conversa_id: c.id }))}
                                >
                                    <div>
                                        <p className="text-sm text-primary">
                                            {CONVERSA_STATUS_LABEL[c.status_v2] ?? c.status_v2}
                                        </p>
                                        <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                            {fmtRelativo(c.ultima_mensagem_em ?? c.updated_at)}
                                        </p>
                                    </div>
                                    <svg width={12} height={12} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)', flexShrink: 0 }}>
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
