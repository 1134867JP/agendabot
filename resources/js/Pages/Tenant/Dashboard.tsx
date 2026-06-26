import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Agendamento } from '@/types';

interface Stats {
    agendamentos_hoje: number;
    agendamentos_semana: number;
    receita_mes: number;
    whatsapp_conectado: boolean;
    bot_agendamentos_mes: number;
    bot_taxa: number;
}

interface SetupCompleto {
    profissionais: boolean;
    servicos: boolean;
    whatsapp: boolean;
    bot_config: boolean;
    horario: boolean;
}

interface UltimaCobranca {
    periodo: string;
    quantidade_agendamentos: number;
    valor_total: string;
    status: 'pendente' | 'pago' | 'falhou';
}

interface Props extends PageProps {
    stats: Stats;
    proximos_agendamentos: Agendamento[];
    setup_completo: SetupCompleto;
    ultima_cobranca_bot: UltimaCobranca | null;
}

function StatCard({ label, value, sub, accent = false, icon }: {
    label: string; value: string | number; sub?: string; accent?: boolean; icon?: string;
}) {
    return (
        <div
            className="rounded-xl p-5"
            style={{
                background: accent ? 'var(--accent)' : 'var(--bg-surface)',
                border: accent ? 'none' : '1px solid var(--border)',
            }}
        >
            <div className="flex items-start justify-between">
                <p className="text-[10px] font-semibold uppercase tracking-[0.1em]" style={{ color: accent ? 'rgba(255,255,255,0.6)' : 'var(--text-3)' }}>
                    {label}
                </p>
                {icon && (
                    <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round"
                        style={{ color: accent ? 'rgba(255,255,255,0.4)' : 'var(--text-3)', flexShrink: 0 }}>
                        <path d={icon} />
                    </svg>
                )}
            </div>
            <p
                className="mt-2.5 text-3xl font-bold leading-none"
                style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: accent ? '#fff' : 'var(--text-1)' }}
            >
                {value}
            </p>
            {sub && <p className="mt-1.5 text-xs" style={{ color: accent ? 'rgba(255,255,255,0.55)' : 'var(--text-3)' }}>{sub}</p>}
        </div>
    );
}

function formatDt(iso: string) {
    const d    = new Date(iso);
    const hoje = new Date();
    const isHoje   = d.toDateString() === hoje.toDateString();
    const amanha   = new Date(hoje); amanha.setDate(hoje.getDate() + 1);
    const isAmanha = d.toDateString() === amanha.toDateString();
    const hora = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    if (isHoje)   return `Hoje, ${hora}`;
    if (isAmanha) return `Amanhã, ${hora}`;
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' }) + ', ' + hora;
}

function formatBrl(value: number) {
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function TenantDashboard({ stats, proximos_agendamentos, setup_completo, ultima_cobranca_bot }: Props) {
    const todoConfigurado =
        setup_completo.bot_config &&
        setup_completo.profissionais &&
        setup_completo.servicos &&
        setup_completo.whatsapp &&
        setup_completo.horario;

    const setupItems = [
        {
            done: setup_completo.bot_config,
            label: 'Configure o bot (nome, tom de voz, descrição)',
            href: route('tenant.configuracoes.index'),
        },
        {
            done: setup_completo.profissionais && setup_completo.horario,
            label: 'Adicione profissionais com horários',
            href: route('tenant.profissionais.index'),
        },
        {
            done: setup_completo.servicos,
            label: 'Cadastre seus serviços',
            href: route('tenant.servicos.index'),
        },
        {
            done: setup_completo.whatsapp,
            label: 'Conecte o WhatsApp',
            href: route('tenant.whatsapp'),
        },
    ];

    return (
        <AppLayout title="Dashboard" subtitle="Resumo das atividades do seu estabelecimento">
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Setup progress banner */}
                {!todoConfigurado ? (
                    <div className="rounded-xl p-4" style={{ background: 'var(--amber-btn-bg)', border: '1px solid var(--amber-btn-bdr)' }}>
                        <h3 className="mb-2.5 text-sm font-semibold" style={{ color: 'var(--amber-text)' }}>
                            Complete a configuração do seu negócio
                        </h3>
                        <div className="space-y-1.5">
                            {setupItems.map((item) => (
                                <a
                                    key={item.href}
                                    href={item.href}
                                    className="flex items-center gap-2 text-sm transition-opacity hover:opacity-80"
                                    style={{ color: 'var(--amber-text)', opacity: item.done ? 0.5 : 1 }}
                                >
                                    <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ flexShrink: 0 }}>
                                        {item.done
                                            ? <><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></>
                                            : <circle cx="12" cy="12" r="10"/>
                                        }
                                    </svg>
                                    <span style={{ textDecoration: item.done ? 'line-through' : 'none' }}>
                                        {item.label}
                                    </span>
                                </a>
                            ))}
                        </div>
                    </div>
                ) : (
                    <div className="flex items-center gap-2.5 rounded-xl p-3.5" style={{ background: 'var(--jade-light)', border: '1px solid rgba(0,168,132,0.2)' }}>
                        <svg width={15} height={15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--jade)', flexShrink: 0 }}>
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        <p className="text-sm font-medium" style={{ color: 'var(--jade)' }}>
                            Tudo configurado — o bot está pronto para receber agendamentos.
                        </p>
                    </div>
                )}

                {/* Stats grid */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Agendamentos hoje"
                        value={stats.agendamentos_hoje}
                        icon="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                    <StatCard
                        label="Esta semana"
                        value={stats.agendamentos_semana}
                        icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                    />
                    <StatCard
                        label="Receita no mês"
                        value={formatBrl(Number(stats.receita_mes))}
                        icon="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        accent
                    />
                    <div className="rounded-xl p-5" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
                        <div className="flex items-start justify-between">
                            <p className="text-[10px] font-semibold uppercase tracking-[0.1em]" style={{ color: 'var(--text-3)' }}>WhatsApp</p>
                            <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)', flexShrink: 0 }}>
                                <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                            </svg>
                        </div>
                        <div className="mt-3 flex items-center gap-2">
                            <span
                                className="h-2.5 w-2.5 rounded-full"
                                style={{
                                    background: stats.whatsapp_conectado ? 'var(--jade)' : 'var(--text-3)',
                                    boxShadow: stats.whatsapp_conectado ? '0 0 0 3px var(--jade-light)' : 'none',
                                }}
                            />
                            <span className="text-sm font-medium" style={{ color: stats.whatsapp_conectado ? 'var(--jade)' : 'var(--text-3)' }}>
                                {stats.whatsapp_conectado ? 'Conectado' : 'Desconectado'}
                            </span>
                        </div>
                        {!stats.whatsapp_conectado && (
                            <Link href={route('tenant.whatsapp')} className="mt-3 inline-block text-xs transition-colors" style={{ color: 'var(--accent)' }}>
                                Conectar agora →
                            </Link>
                        )}
                    </div>
                </div>

                {/* Card de cobrança variável bot */}
                <div className="rounded-xl p-5" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
                    <div className="flex items-start justify-between">
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-[0.1em]" style={{ color: 'var(--text-3)' }}>
                                Taxa bot — {new Date().toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })}
                            </p>
                            <p className="mt-2 text-2xl font-bold leading-none" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>
                                {stats.bot_agendamentos_mes} agendamento{stats.bot_agendamentos_mes !== 1 ? 's' : ''} via bot
                            </p>
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                Estimativa:{' '}
                                <span style={{ color: 'var(--text-2)', fontWeight: 600 }}>
                                    {(stats.bot_agendamentos_mes * stats.bot_taxa).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                                </span>
                                {' '}(R$ {stats.bot_taxa.toFixed(2).replace('.', ',')} por agendamento — cobrado no dia 1)
                            </p>
                        </div>
                        <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)', flexShrink: 0, marginTop: 2 }}>
                            <path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    {ultima_cobranca_bot && (
                        <div className="mt-3 flex items-center gap-2 rounded-lg px-3 py-2" style={{ background: 'var(--bg-surface-2)' }}>
                            <span
                                className="h-1.5 w-1.5 rounded-full flex-shrink-0"
                                style={{
                                    background: ultima_cobranca_bot.status === 'pago'
                                        ? 'var(--jade)'
                                        : ultima_cobranca_bot.status === 'falhou'
                                        ? 'var(--red)'
                                        : 'var(--amber)',
                                }}
                            />
                            <p className="text-xs" style={{ color: 'var(--text-3)' }}>
                                Última cobrança ({ultima_cobranca_bot.periodo}):{' '}
                                <span style={{ color: 'var(--text-2)' }}>
                                    {Number(ultima_cobranca_bot.valor_total).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                                </span>
                                {' — '}
                                {ultima_cobranca_bot.status === 'pago' ? 'Pago' : ultima_cobranca_bot.status === 'falhou' ? 'Falhou' : 'Pendente'}
                            </p>
                        </div>
                    )}
                </div>

                {/* Próximos agendamentos */}
                <div className="card overflow-hidden">
                    <div className="flex items-center justify-between px-6 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                        <h2 className="text-sm font-semibold text-primary">Próximos agendamentos</h2>
                        <Link href={route('tenant.agendamentos.index')} className="text-xs transition-colors" style={{ color: 'var(--accent)' }}>
                            Ver todos →
                        </Link>
                    </div>

                    {proximos_agendamentos.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 px-6 py-12 text-center">
                            <div
                                className="flex h-12 w-12 items-center justify-center rounded-2xl"
                                style={{ background: 'var(--bg-surface-2)' }}
                            >
                                <svg width={20} height={20} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--text-3)' }}>
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-primary">Nenhum agendamento confirmado</p>
                                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>Os próximos horários aparecerão aqui.</p>
                            </div>
                            <Link
                                href={route('tenant.agenda')}
                                className="mt-1 text-xs font-medium transition-colors hover:brightness-125"
                                style={{ color: 'var(--accent)' }}
                            >
                                Abrir agenda →
                            </Link>
                        </div>
                    ) : (
                        <ul>
                            {proximos_agendamentos.map(a => (
                                <li
                                    key={a.id}
                                    className="table-row-hover flex items-center justify-between px-6 py-3.5"
                                    style={{ borderBottom: '1px solid var(--border)' }}
                                >
                                    <div className="flex items-center gap-3">
                                        <div
                                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[13px] font-semibold"
                                            style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
                                        >
                                            {a.cliente_nome.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium text-primary">{a.cliente_nome}</p>
                                            <p className="text-xs" style={{ color: 'var(--text-3)' }}>{a.recurso?.nome}</p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm text-primary">{formatDt(a.inicio)}</p>
                                        {a.valor_total && (
                                            <p className="text-xs" style={{ color: 'var(--text-3)' }}>{formatBrl(Number(a.valor_total))}</p>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {/* Quick links */}
                <div className="grid gap-3 sm:grid-cols-3">
                    {[
                        { href: route('tenant.agenda'),             label: 'Abrir Agenda',   desc: 'Veja os horários da semana',     icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
                        { href: route('tenant.agendamentos.index'), label: 'Agendamentos',   desc: 'Gerencie reservas e clientes',   icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
                        { href: route('tenant.recursos.index'),     label: 'Recursos',       desc: 'Barbeiros, quadras e horários',  icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
                    ].map(link => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className="card-hover flex items-start gap-3.5 p-5"
                        >
                            <span
                                className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg"
                                style={{ background: 'var(--accent-light)' }}
                            >
                                <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round" style={{ color: 'var(--accent)' }}>
                                    <path d={link.icon} />
                                </svg>
                            </span>
                            <div>
                                <p className="text-sm font-medium text-primary">{link.label}</p>
                                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>{link.desc}</p>
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
