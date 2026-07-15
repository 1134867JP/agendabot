import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Agendamento, Tenant } from '@/types';
import { usaProfissionais } from '@/lib/tenantNav';

interface Stats {
    agendamentos_hoje: number;
    agendamentos_semana: number;
    receita_mes: number;
    whatsapp_conectado: boolean;
    bot_agendamentos_mes: number;
    bot_taxa: number;
    conversas_aguardando: number;
    conversas_nao_lidas: number;
    clientes_total: number;
    falhas_recentes: number;
}

interface SetupCompleto {
    profissionais: boolean;
    servicos: boolean;
    recursos: boolean;
    whatsapp: boolean;
    bot_config: boolean;
    horario: boolean;
}

interface Pendencia {
    id: string;
    tone: 'danger' | 'warning' | 'neutral';
    title: string;
    description: string;
    action: string;
    href: string;
}

interface UltimaCobranca {
    periodo: string;
    quantidade_agendamentos: number;
    valor_total: string;
    status: 'pendente' | 'pago' | 'falhou';
}

interface Props extends PageProps {
    tenant: Tenant;
    stats: Stats;
    pendencias: Pendencia[];
    proximos_agendamentos: Agendamento[];
    setup_completo: SetupCompleto;
    ultima_cobranca_bot: UltimaCobranca | null;
}

const Icon = ({ path, size = 16 }: { path: string; size?: number }) => (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <path d={path} />
    </svg>
);

function StatCard({ label, value, sub, href, icon }: {
    label: string;
    value: string | number;
    sub: string;
    href: string;
    icon: string;
}) {
    return (
        <Link href={href} className="card-hover group flex min-h-28 flex-col justify-between p-4">
            <div className="flex items-start justify-between gap-3">
                <p className="text-[10px] font-semibold uppercase tracking-[0.1em]" style={{ color: 'var(--text-3)' }}>{label}</p>
                <span className="transition-colors group-hover:text-[var(--accent)]" style={{ color: 'var(--text-3)' }}><Icon path={icon} /></span>
            </div>
            <div>
                <p className="text-2xl font-semibold leading-none text-primary">{value}</p>
                <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>{sub}</p>
            </div>
        </Link>
    );
}

function formatDt(iso: string) {
    const d = new Date(iso);
    const hoje = new Date();
    const amanha = new Date(hoje);
    amanha.setDate(hoje.getDate() + 1);
    const hora = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    if (d.toDateString() === hoje.toDateString()) return 'Hoje, ' + hora;
    if (d.toDateString() === amanha.toDateString()) return 'Amanhã, ' + hora;
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }) + ', ' + hora;
}

function formatBrl(value: number) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function TenantDashboard({
    tenant,
    stats,
    pendencias,
    proximos_agendamentos,
    setup_completo,
    ultima_cobranca_bot,
}: Props) {
    const modoAgendamento = (tenant.modo_bot ?? 'agendamento') === 'agendamento';
    const comProfissionais = usaProfissionais(tenant.tipo_servico);

    const catalogoItems = modoAgendamento
        ? comProfissionais
            ? [
                { done: setup_completo.profissionais && setup_completo.horario, label: 'Cadastre profissionais e horários', description: 'Defina quem atende e quando.', href: route('tenant.profissionais.index') },
                { done: setup_completo.servicos, label: 'Cadastre seus serviços', description: 'Informe duração e valor de cada atendimento.', href: route('tenant.servicos.index') },
            ]
            : [
                { done: setup_completo.recursos && setup_completo.horario, label: 'Cadastre recursos e horários', description: 'Configure quadras, salas ou outros espaços.', href: route('tenant.recursos.index') },
            ]
        : [];

    const setupItems = [
        { done: setup_completo.bot_config, label: 'Configure o atendimento', description: modoAgendamento ? 'Personalize como o bot conversa e agenda.' : 'Personalize como o bot coleta dados e transfere.', href: route('tenant.configuracoes.index') },
        ...catalogoItems,
        { done: setup_completo.whatsapp, label: 'Conecte o WhatsApp', description: 'Ative o canal usado pelos seus clientes.', href: route('tenant.whatsapp') },
        { done: true, label: 'Faça um teste', description: 'Valide a experiência antes de divulgar.', href: route('tenant.bot.simulador') },
    ];

    const concluidos = setupItems.filter(item => item.done).length;
    const progresso = Math.round((concluidos / setupItems.length) * 100);
    const todoConfigurado = concluidos === setupItems.length;

    const statsCards = modoAgendamento
        ? [
            { label: 'Hoje', value: stats.agendamentos_hoje, sub: 'agendamentos confirmados', href: route('tenant.agenda'), icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
            { label: 'Esta semana', value: stats.agendamentos_semana, sub: 'reservas programadas', href: route('tenant.agendamentos.index'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2' },
            { label: 'Receita no mês', value: formatBrl(Number(stats.receita_mes)), sub: 'valor dos atendimentos', href: route('tenant.analytics'), icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8v1m0 8v1M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
            { label: 'Precisa de atenção', value: stats.conversas_aguardando + stats.falhas_recentes, sub: 'pendências operacionais', href: pendencias[0]?.href ?? route('tenant.conversas.index'), icon: 'M12 9v4m0 4h.01M10.3 3.7L2.2 18a2 2 0 001.7 3h16.2a2 2 0 001.7-3L13.7 3.7a2 2 0 00-3.4 0z' },
        ]
        : [
            { label: 'Aguardando equipe', value: stats.conversas_aguardando, sub: 'transferências do bot', href: route('tenant.conversas.index', { status_v2: 'aguardando_humano' }), icon: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z' },
            { label: 'Não lidas', value: stats.conversas_nao_lidas, sub: 'novas mensagens', href: route('tenant.conversas.index'), icon: 'M4 4h16v12H7l-3 3V4z' },
            { label: 'Clientes', value: stats.clientes_total, sub: 'contatos cadastrados', href: route('tenant.clientes.index'), icon: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z' },
            { label: 'Falhas recentes', value: stats.falhas_recentes, sub: 'últimas 24 horas', href: route('tenant.analytics'), icon: 'M12 9v4m0 4h.01M10.3 3.7L2.2 18a2 2 0 001.7 3h16.2a2 2 0 001.7-3L13.7 3.7a2 2 0 00-3.4 0z' },
        ];

    return (
        <AppLayout
            title="Visão geral"
            subtitle={modoAgendamento ? 'Sua operação de agenda em um só lugar' : 'Atendimentos e transferências que precisam da sua equipe'}
        >
            <Head title="Visão geral" />

            <div className="space-y-6 pb-20 lg:pb-0">
                <div className="flex flex-col gap-3 rounded-xl p-4 sm:flex-row sm:items-center sm:justify-between" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-sm font-semibold text-primary">Modo de operação</p>
                            <span className="badge badge-brand">{modoAgendamento ? 'Agendamento' : 'Triagem'}</span>
                        </div>
                        <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                            {modoAgendamento
                                ? 'O bot atende, consulta disponibilidade e conclui reservas.'
                                : 'O bot coleta informações e transfere a conversa quando necessário.'}
                        </p>
                    </div>
                    <Link href={route('tenant.configuracoes.index')} className="btn-secondary min-h-11 justify-center">Ajustar funcionamento</Link>
                </div>

                {!todoConfigurado && (
                    <section className="card overflow-hidden">
                        <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5" style={{ borderBottom: '1px solid var(--border)' }}>
                            <div>
                                <p className="text-sm font-semibold text-primary">Preparar o sistema</p>
                                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>{concluidos} de {setupItems.length} etapas concluídas</p>
                            </div>
                            <div className="w-full sm:w-44">
                                <div className="mb-1 flex justify-between text-[10px]" style={{ color: 'var(--text-3)' }}><span>Progresso</span><span>{progresso}%</span></div>
                                <div className="h-1.5 overflow-hidden rounded-full" style={{ background: 'var(--bg-surface-2)' }}>
                                    <div className="h-full rounded-full transition-all" style={{ width: progresso + '%', background: 'var(--jade)' }} />
                                </div>
                            </div>
                        </div>
                        <div className="grid md:grid-cols-2">
                            {setupItems.map((item, index) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    className="flex min-h-20 items-start gap-3 px-4 py-3.5 transition-colors hover:bg-[var(--bg-surface-2)] sm:px-5"
                                    style={{ borderBottom: '1px solid var(--border)' }}
                                >
                                    <span
                                        className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                        style={{ background: item.done ? 'var(--jade-light)' : 'var(--bg-surface-2)', color: item.done ? 'var(--jade)' : 'var(--text-3)' }}
                                    >
                                        {item.done ? <Icon path="M5 12l4 4L19 6" size={14} /> : index + 1}
                                    </span>
                                    <span>
                                        <span className="block text-sm font-medium text-primary">{item.label}</span>
                                        <span className="mt-0.5 block text-xs" style={{ color: 'var(--text-3)' }}>{item.description}</span>
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                <section>
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-semibold text-primary">Precisa da sua atenção</h2>
                            <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>Prioridades da operação agora</p>
                        </div>
                    </div>

                    {pendencias.length === 0 ? (
                        <div className="flex items-center gap-3 rounded-xl p-4" style={{ background: 'var(--jade-light)', border: '1px solid rgba(0,168,132,.22)' }}>
                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" style={{ background: 'rgba(0,168,132,.14)', color: 'var(--jade)' }}><Icon path="M5 12l4 4L19 6" /></span>
                            <div>
                                <p className="text-sm font-medium" style={{ color: 'var(--jade)' }}>Tudo em ordem</p>
                                <p className="text-xs" style={{ color: 'var(--text-3)' }}>Nenhuma pendência operacional foi encontrada.</p>
                            </div>
                        </div>
                    ) : (
                        <div className="grid gap-3 lg:grid-cols-2">
                            {pendencias.map(item => {
                                const colors = item.tone === 'danger'
                                    ? { bg: 'rgba(239,68,68,.07)', border: 'rgba(239,68,68,.2)', color: '#f87171' }
                                    : item.tone === 'warning'
                                    ? { bg: 'var(--amber-btn-bg)', border: 'var(--amber-btn-bdr)', color: 'var(--amber-text)' }
                                    : { bg: 'var(--bg-surface)', border: 'var(--border)', color: 'var(--accent)' };
                                return (
                                    <Link key={item.id} href={item.href} className="flex min-h-24 items-center justify-between gap-4 rounded-xl p-4 transition-all hover:brightness-110" style={{ background: colors.bg, border: '1px solid ' + colors.border }}>
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold text-primary">{item.title}</p>
                                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>{item.description}</p>
                                        </div>
                                        <span className="shrink-0 text-xs font-semibold" style={{ color: colors.color }}>{item.action} →</span>
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </section>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {statsCards.map(card => <StatCard key={card.label} {...card} />)}
                </div>

                {modoAgendamento && (
                    <section className="card overflow-hidden">
                        <div className="flex items-center justify-between gap-3 px-4 py-4 sm:px-6" style={{ borderBottom: '1px solid var(--border)' }}>
                            <div>
                                <h2 className="text-sm font-semibold text-primary">Próximos agendamentos</h2>
                                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>O que vem a seguir na sua agenda</p>
                            </div>
                            <Link href={route('tenant.agendamentos.index')} className="text-xs font-medium" style={{ color: 'var(--accent)' }}>Ver todos →</Link>
                        </div>

                        {proximos_agendamentos.length === 0 ? (
                            <div className="flex flex-col items-center gap-3 px-4 py-10 text-center sm:px-6">
                                <span className="flex h-11 w-11 items-center justify-center rounded-xl" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)' }}><Icon path="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></span>
                                <div>
                                    <p className="text-sm font-medium text-primary">Sua agenda está livre</p>
                                    <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>Crie uma reserva manual ou aguarde os agendamentos pelo WhatsApp.</p>
                                </div>
                                <Link href={route('tenant.agendamentos.index', { novo: 1 })} className="btn-primary min-h-11">Criar primeiro agendamento</Link>
                            </div>
                        ) : (
                            <ul>
                                {proximos_agendamentos.map(appointment => (
                                    <li key={appointment.id} className="table-row-hover flex items-start justify-between gap-3 px-4 py-3.5 sm:items-center sm:px-6" style={{ borderBottom: '1px solid var(--border)' }}>
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold" style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}>{appointment.cliente_nome.charAt(0).toUpperCase()}</span>
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-primary">{appointment.cliente_nome}</p>
                                                <p className="truncate text-xs" style={{ color: 'var(--text-3)' }}>{appointment.recurso?.nome ?? 'Atendimento'}</p>
                                            </div>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="whitespace-nowrap text-xs text-primary sm:text-sm">{formatDt(appointment.inicio)}</p>
                                            {appointment.valor_total && <p className="text-xs" style={{ color: 'var(--text-3)' }}>{formatBrl(Number(appointment.valor_total))}</p>}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}

                {modoAgendamento && (
                    <section className="rounded-xl p-4 sm:p-5" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p className="text-[10px] font-semibold uppercase tracking-[0.1em]" style={{ color: 'var(--text-3)' }}>Uso do agendamento automático</p>
                                <p className="mt-2 text-xl font-semibold text-primary">{stats.bot_agendamentos_mes} reserva{stats.bot_agendamentos_mes === 1 ? '' : 's'} pelo bot neste mês</p>
                                <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>Estimativa de cobrança: <strong style={{ color: 'var(--text-2)' }}>{formatBrl(stats.bot_agendamentos_mes * stats.bot_taxa)}</strong></p>
                            </div>
                            <Link href={route('tenant.analytics')} className="btn-secondary min-h-11">Ver desempenho</Link>
                        </div>
                        {ultima_cobranca_bot && (
                            <p className="mt-3 rounded-lg px-3 py-2 text-xs" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)' }}>
                                Última cobrança ({ultima_cobranca_bot.periodo}): {formatBrl(Number(ultima_cobranca_bot.valor_total))} — {ultima_cobranca_bot.status === 'pago' ? 'paga' : ultima_cobranca_bot.status === 'falhou' ? 'falhou' : 'pendente'}
                            </p>
                        )}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
