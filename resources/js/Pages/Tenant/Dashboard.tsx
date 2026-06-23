import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Agendamento } from '@/types';

interface Stats {
    agendamentos_hoje: number;
    agendamentos_semana: number;
    receita_mes: number;
    whatsapp_conectado: boolean;
}

interface SetupCompleto {
    profissionais: boolean;
    servicos: boolean;
    whatsapp: boolean;
    bot_config: boolean;
    horario: boolean;
}

interface Props extends PageProps {
    stats: Stats;
    proximos_agendamentos: Agendamento[];
    setup_completo: SetupCompleto;
}

function StatCard({ label, value, sub, accent = false }: {
    label: string; value: string | number; sub?: string; accent?: boolean;
}) {
    return (
        <div
            className="rounded-xl p-5"
            style={{
                background: accent ? 'var(--accent)' : 'var(--bg-surface)',
                border: accent ? 'none' : '1px solid var(--border)',
            }}
        >
            <p className="text-[10px] font-medium uppercase tracking-widest" style={{ color: accent ? 'rgba(255,255,255,0.6)' : 'var(--text-3)' }}>
                {label}
            </p>
            <p
                className="mt-2 text-3xl font-bold leading-none text-primary"
                style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}
            >
                {value}
            </p>
            {sub && <p className="mt-1 text-xs" style={{ color: accent ? 'rgba(255,255,255,0.5)' : 'var(--text-3)' }}>{sub}</p>}
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

export default function TenantDashboard({ stats, proximos_agendamentos, setup_completo }: Props) {
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
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Setup progress banner */}
                {!todoConfigurado ? (
                    <div className="rounded-xl border border-yellow-200 bg-yellow-50 p-4">
                        <h3 className="mb-2 font-semibold text-yellow-800">
                            Complete a configuração do seu negócio
                        </h3>
                        <div className="space-y-1.5">
                            {setupItems.map((item) => (
                                <a
                                    key={item.href}
                                    href={item.href}
                                    className="flex items-center gap-2 text-sm text-yellow-700 hover:underline"
                                >
                                    <span className="shrink-0 text-base leading-none">
                                        {item.done ? '✅' : '⬜'}
                                    </span>
                                    <span style={{ textDecoration: item.done ? 'line-through' : 'none', opacity: item.done ? 0.6 : 1 }}>
                                        {item.label}
                                    </span>
                                </a>
                            ))}
                        </div>
                    </div>
                ) : (
                    <div className="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                        ✅ Tudo configurado! O bot está pronto para receber agendamentos.
                    </div>
                )}

                {/* Stats grid */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Agendamentos hoje" value={stats.agendamentos_hoje} />
                    <StatCard label="Esta semana"       value={stats.agendamentos_semana} />
                    <StatCard label="Receita no mês"    value={formatBrl(Number(stats.receita_mes))} accent />
                    <div className="rounded-xl p-5" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
                        <p className="text-[10px] font-medium uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>WhatsApp</p>
                        <div className="mt-3 flex items-center gap-2">
                            <span
                                className="h-3 w-3 rounded-full"
                                style={{
                                    background: stats.whatsapp_conectado ? 'var(--emerald)' : 'var(--text-3)',
                                    boxShadow: stats.whatsapp_conectado ? '0 0 0 3px rgba(110,231,183,0.15)' : 'none',
                                }}
                            />
                            <span className="text-sm font-medium" style={{ color: stats.whatsapp_conectado ? 'var(--emerald)' : 'var(--text-3)' }}>
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

                {/* Próximos agendamentos */}
                <div className="card overflow-hidden">
                    <div className="flex items-center justify-between px-6 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                        <h2 className="text-sm font-semibold text-primary">Próximos agendamentos</h2>
                        <Link href={route('tenant.agendamentos.index')} className="text-xs transition-colors" style={{ color: 'var(--accent)' }}>
                            Ver todos →
                        </Link>
                    </div>

                    {proximos_agendamentos.length === 0 ? (
                        <div className="px-6 py-10 text-center">
                            <p className="text-sm" style={{ color: 'var(--text-3)' }}>Nenhum agendamento futuro confirmado.</p>
                            <Link href={route('tenant.agenda')} className="mt-3 inline-block text-sm transition-colors" style={{ color: 'var(--accent)' }}>
                                Abrir agenda →
                            </Link>
                        </div>
                    ) : (
                        <ul>
                            {proximos_agendamentos.map(a => (
                                <li
                                    key={a.id}
                                    className="flex items-center justify-between px-6 py-3.5 transition-colors"
                                    style={{ borderBottom: '1px solid var(--border)' }}
                                    onMouseEnter={e => (e.currentTarget.style.background = 'var(--bg-surface-2)')}
                                    onMouseLeave={e => (e.currentTarget.style.background = 'transparent')}
                                >
                                    <div className="flex items-center gap-3">
                                        <div
                                            className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-primary"
                                            style={{ background: 'var(--accent)' }}
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
                        { href: route('tenant.agenda'),             label: 'Abrir Agenda',   desc: 'Veja os horários da semana' },
                        { href: route('tenant.agendamentos.index'), label: 'Agendamentos',   desc: 'Gerencie reservas e clientes' },
                        { href: route('tenant.recursos.index'),     label: 'Recursos',       desc: 'Barbeiros, quadras e horários' },
                    ].map(link => (
                        <Link
                            key={link.href}
                            href={link.href}
                            className="card flex flex-col gap-1 p-5 transition-all"
                            onMouseEnter={e => { (e.currentTarget as HTMLElement).style.borderColor = 'var(--border-strong)'; (e.currentTarget as HTMLElement).style.background = 'var(--bg-surface2)'; }}
                            onMouseLeave={e => { (e.currentTarget as HTMLElement).style.borderColor = 'var(--border)'; (e.currentTarget as HTMLElement).style.background = 'var(--bg-surface)'; }}
                        >
                            <p className="text-sm font-medium text-primary">{link.label}</p>
                            <p className="text-xs" style={{ color: 'var(--text-3)' }}>{link.desc}</p>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
