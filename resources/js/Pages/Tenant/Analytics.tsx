import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout';

interface Stat {
    total_mes: number;
    receita_mes: number;
    conversas_mes: number;
    taxa_conversao: number;
    tempo_resposta_ms: number;
    receita_bot: number;
}

interface DiaData {
    data: string;
    label: string;
    total: number;
}

interface ItemRanking {
    nome: string;
    total: number;
}

interface Props extends PageProps {
    modo: 'agendamento' | 'triagem';
    filtros: { dias: number };
    periodo: { inicio: string; fim: string };
    stats: Stat;
    comparacao: {
        agendamentos: number;
        receita: number;
        conversas: number;
        conversao: number;
    };
    por_dia: DiaData[];
    top_servicos: ItemRanking[];
    pico_horario: ItemRanking[];
}

function StatCard({
    label,
    value,
    sub,
    change,
    suffix = '%',
}: {
    label: string;
    value: string;
    sub?: string;
    change?: number;
    suffix?: string;
}) {
    const hasChange = typeof change === 'number';
    const positive = (change ?? 0) > 0;
    const neutral = change === 0;

    return (
        <div className="card p-5">
            <p className="text-xs font-medium uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>{label}</p>
            <p className="mt-2 text-3xl font-bold text-primary">{value}</p>
            {sub && <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>{sub}</p>}
            {hasChange && (
                <p
                    className="mt-3 text-xs font-medium"
                    style={{ color: neutral ? 'var(--text-3)' : positive ? 'var(--success)' : 'var(--danger)' }}
                >
                    {neutral ? 'Sem variação' : `${positive ? '+' : ''}${change}${suffix}`} vs. período anterior
                </p>
            )}
        </div>
    );
}

function BarChart({ data, itemLabel }: { data: DiaData[]; itemLabel: string }) {
    const max = Math.max(...data.map(d => d.total), 1);
    const stride = Math.max(1, Math.ceil(data.length / 6));

    if (!data.some(d => d.total > 0)) {
        return (
            <div className="flex min-h-40 items-center justify-center rounded-xl border border-dashed px-6 text-center" style={{ borderColor: 'var(--border)' }}>
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>Ainda não há dados neste período.</p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto pb-1">
            <div className="flex h-44 min-w-[520px] items-end gap-1" role="img" aria-label={`Quantidade de ${itemLabel} por dia`}>
                {data.map((d, i) => (
                    <div
                        key={d.data}
                        className="group relative flex h-full flex-1 flex-col items-center justify-end"
                        tabIndex={d.total > 0 ? 0 : -1}
                        aria-label={`${d.label}: ${d.total} ${itemLabel}`}
                    >
                        {d.total > 0 && (
                            <span className="mb-1 text-[10px] font-semibold" style={{ color: 'var(--text-2)' }}>{d.total}</span>
                        )}
                        <div
                            className="w-full min-w-1 rounded-t transition-all group-focus:opacity-80 group-hover:opacity-80"
                            style={{
                                height: `${Math.max(3, (d.total / max) * 105)}px`,
                                background: d.total > 0 ? 'var(--accent)' : 'var(--border)',
                                opacity: d.total > 0 ? 1 : 0.45,
                            }}
                        />
                        {i % stride === 0 && (
                            <span className="mt-2 whitespace-nowrap text-[10px]" style={{ color: 'var(--text-3)' }}>{d.label}</span>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

function RankingList({ items, emptyText }: { items: ItemRanking[]; emptyText: string }) {
    if (items.length === 0) {
        return <p className="text-sm" style={{ color: 'var(--text-3)' }}>{emptyText}</p>;
    }

    const max = Math.max(items[0]?.total ?? 0, 1);

    return (
        <ul className="space-y-4">
            {items.map((item, i) => (
                <li key={`${item.nome}-${i}`}>
                    <div className="mb-1.5 flex items-center justify-between gap-4">
                        <span className="truncate text-sm text-primary">{item.nome}</span>
                        <span className="text-xs font-semibold" style={{ color: 'var(--text-2)' }}>{item.total}</span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full" style={{ background: 'var(--border)' }}>
                        <div
                            className="h-full rounded-full"
                            style={{ width: `${(item.total / max) * 100}%`, background: 'var(--accent)' }}
                        />
                    </div>
                </li>
            ))}
        </ul>
    );
}

export default function Analytics({
    modo,
    filtros,
    periodo,
    stats,
    comparacao,
    por_dia,
    top_servicos,
    pico_horario,
}: Props) {
    const agendamento = modo === 'agendamento';
    const receita = stats.receita_mes.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const receitaBot = stats.receita_bot.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    const tempoResposta = stats.tempo_resposta_ms > 0 ? `${(stats.tempo_resposta_ms / 1000).toFixed(1)}s` : '—';
    const inicio = new Date(`${periodo.inicio}T12:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
    const fim = new Date(`${periodo.fim}T12:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });

    const changePeriod = (dias: number) => {
        router.get(route('tenant.analytics'), { dias }, { preserveScroll: true, preserveState: true });
    };

    return (
        <AppLayout
            title="Desempenho"
            subtitle={agendamento ? 'Entenda a operação e encontre onde agir' : 'Acompanhe o volume e a saúde dos atendimentos'}
        >
            <Head title="Desempenho" />

            <div className="space-y-5 sm:space-y-6">
                <div className="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div>
                        <p className="text-sm font-semibold text-primary">Período analisado</p>
                        <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>{inicio} a {fim}</p>
                    </div>
                    <div className="grid grid-cols-3 gap-2" aria-label="Selecionar período">
                        {[7, 30, 90].map(dias => (
                            <button
                                key={dias}
                                type="button"
                                onClick={() => changePeriod(dias)}
                                className="min-h-11 rounded-lg border px-3 text-sm font-medium transition-colors"
                                style={{
                                    borderColor: filtros.dias === dias ? 'var(--accent)' : 'var(--border)',
                                    background: filtros.dias === dias ? 'var(--accent-soft)' : 'transparent',
                                    color: filtros.dias === dias ? 'var(--accent)' : 'var(--text-2)',
                                }}
                            >
                                {dias} dias
                            </button>
                        ))}
                    </div>
                </div>

                <div className={`grid gap-4 sm:grid-cols-2 ${agendamento ? 'xl:grid-cols-4' : 'lg:grid-cols-3'}`}>
                    {agendamento ? (
                        <>
                            <StatCard label="Agendamentos" value={String(stats.total_mes)} sub="confirmados e concluídos" change={comparacao.agendamentos} />
                            <StatCard label="Receita prevista" value={receita} sub="valor dos agendamentos" change={comparacao.receita} />
                            <StatCard label="Conversas" value={String(stats.conversas_mes)} sub="atendimentos iniciados" change={comparacao.conversas} />
                            <StatCard label="Conversão" value={`${stats.taxa_conversao}%`} sub="conversas que viraram agenda" change={comparacao.conversao} suffix=" p.p." />
                        </>
                    ) : (
                        <>
                            <StatCard label="Conversas" value={String(stats.conversas_mes)} sub="atendimentos iniciados" change={comparacao.conversas} />
                            <StatCard label="Tempo de resposta" value={tempoResposta} sub="média do processamento" />
                        </>
                    )}
                </div>

                {agendamento && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <StatCard label="Receita pelo WhatsApp" value={receitaBot} sub="retorno atribuído ao atendimento" />
                        <StatCard label="Tempo de resposta" value={tempoResposta} sub="média do processamento" />
                    </div>
                )}

                <div className="card p-4 sm:p-5">
                    <div className="mb-5">
                        <p className="text-sm font-semibold text-primary">{agendamento ? 'Agendamentos por dia' : 'Conversas por dia'}</p>
                        <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>Use os números para identificar dias de pico ou queda.</p>
                    </div>
                    <BarChart data={por_dia} itemLabel={agendamento ? 'agendamentos' : 'conversas'} />
                </div>

                {agendamento && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <div className="card p-4 sm:p-5">
                            <p className="mb-4 text-sm font-semibold text-primary">Serviços mais procurados</p>
                            <RankingList items={top_servicos} emptyText="Os serviços aparecerão aqui após os primeiros agendamentos." />
                        </div>
                        <div className="card p-4 sm:p-5">
                            <p className="mb-4 text-sm font-semibold text-primary">Horários com mais movimento</p>
                            <RankingList items={pico_horario} emptyText="Os horários de pico aparecerão quando houver dados suficientes." />
                        </div>
                    </div>
                )}

                <div className="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div>
                        <p className="text-sm font-semibold text-primary">
                            {agendamento ? 'Transforme os dados em rotina' : 'Continue o atendimento de onde parou'}
                        </p>
                        <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>
                            {agendamento
                                ? 'Revise a agenda e antecipe conflitos, confirmações e horários ociosos.'
                                : 'Priorize as conversas que estão aguardando uma pessoa da equipe.'}
                        </p>
                    </div>
                    <Link
                        href={route(agendamento ? 'tenant.agenda' : 'tenant.conversas.index')}
                        className="btn-primary min-h-11 shrink-0 text-center"
                    >
                        {agendamento ? 'Abrir agenda' : 'Ver conversas'}
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
