import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout';

interface Stat {
    total_mes: number;
    receita_mes: number;
    conversas_mes: number;
    taxa_conversao: number;
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
    stats: Stat;
    por_dia: DiaData[];
    top_servicos: ItemRanking[];
    pico_horario: ItemRanking[];
}

function StatCard({ label, value, sub }: { label: string; value: string; sub?: string }) {
    return (
        <div className="card p-5">
            <p className="text-xs font-medium uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>{label}</p>
            <p className="mt-2 text-3xl font-bold text-primary">{value}</p>
            {sub && <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>{sub}</p>}
        </div>
    );
}

function BarChart({ data }: { data: DiaData[] }) {
    const max = Math.max(...data.map(d => d.total), 1);
    const stride = Math.max(1, Math.floor(data.length / 6));

    return (
        <div className="flex h-32 items-end gap-px">
            {data.map((d, i) => (
                <div key={d.data} className="group relative flex flex-1 flex-col items-center justify-end">
                    <div
                        className="w-full rounded-sm transition-all"
                        style={{
                            height: `${Math.max(2, (d.total / max) * 100)}%`,
                            background: d.total > 0 ? 'var(--accent)' : 'var(--border)',
                            opacity: d.total > 0 ? 1 : 0.4,
                        }}
                        title={`${d.data}: ${d.total} agendamento${d.total !== 1 ? 's' : ''}`}
                    />
                    {i % stride === 0 && (
                        <span className="mt-1 text-[9px]" style={{ color: 'var(--text-3)' }}>{d.label}</span>
                    )}
                    {/* Tooltip */}
                    {d.total > 0 && (
                        <div
                            className="pointer-events-none absolute bottom-full mb-1 hidden rounded px-1.5 py-0.5 text-[10px] text-white group-hover:block"
                            style={{ background: 'var(--text-1)', whiteSpace: 'nowrap' }}
                        >
                            {d.total}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
}

function RankingList({ items, emptyText }: { items: ItemRanking[]; emptyText: string }) {
    if (items.length === 0) {
        return <p className="text-sm" style={{ color: 'var(--text-3)' }}>{emptyText}</p>;
    }
    const max = items[0].total;
    return (
        <ul className="space-y-3">
            {items.map((item, i) => (
                <li key={i}>
                    <div className="mb-1 flex items-center justify-between">
                        <span className="text-sm text-primary">{item.nome}</span>
                        <span className="text-xs font-medium" style={{ color: 'var(--text-3)' }}>{item.total}</span>
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

export default function Analytics({ stats, por_dia, top_servicos, pico_horario }: Props) {
    const receita = stats.receita_mes.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    return (
        <AppLayout>
            <Head title="Analytics" />
            <div className="mx-auto max-w-5xl space-y-6 p-6">

                <div>
                    <h1 className="text-xl font-semibold text-primary">Analytics</h1>
                    <p className="mt-0.5 text-sm" style={{ color: 'var(--text-3)' }}>Desempenho do mês atual</p>
                </div>

                {/* Stat cards */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Agendamentos" value={String(stats.total_mes)} sub="no mês atual" />
                    <StatCard label="Receita" value={receita} sub="no mês atual" />
                    <StatCard label="Conversas bot" value={String(stats.conversas_mes)} sub="iniciadas no mês" />
                    <StatCard
                        label="Taxa de conversão"
                        value={`${stats.taxa_conversao}%`}
                        sub="conversas → agendamentos"
                    />
                </div>

                {/* Gráfico de barras */}
                <div className="card p-5">
                    <p className="mb-4 text-sm font-medium text-primary">Agendamentos — últimos 30 dias</p>
                    <BarChart data={por_dia} />
                </div>

                {/* Rankings */}
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="card p-5">
                        <p className="mb-4 text-sm font-medium text-primary">Serviços mais agendados</p>
                        <RankingList items={top_servicos} emptyText="Nenhum agendamento com serviço registrado." />
                    </div>
                    <div className="card p-5">
                        <p className="mb-4 text-sm font-medium text-primary">Horários de pico</p>
                        <RankingList items={pico_horario} emptyText="Sem dados suficientes." />
                    </div>
                </div>

            </div>
        </AppLayout>
    );
}
