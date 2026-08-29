import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

interface MesStats {
    calls:       number;
    input:       number;
    output:      number;
    cache_write: number;
    cache_read:  number;
    custo_usd:   number;
}

interface TenantRow {
    id:          number;
    nome:        string;
    slug:        string;
    calls:       number;
    input:       number;
    output:      number;
    cache_write: number;
    cache_read:  number;
    custo_usd:   number;
}

interface DiaRow {
    dia:        string;
    calls:      number;
    input:      number;
    output:     number;
    cache_read: number;
    custo_usd:  number;
}

interface AiProvider {
    provider: string;
    label: string;
    model: string;
    configurado: boolean;
}

interface AiStatus {
    padrao: AiProvider;
    fallbacks: AiProvider[];
    ultima_chamada: {
        provider: string;
        label: string;
        model: string;
        em: string;
    } | null;
}

interface Props {
    ia:                AiStatus;
    mes:               MesStats;
    mesAnterior:       { custo_usd: number };
    cacheHitRate:      number;
    economiaCacheUsd:  number;
    porTenant:         TenantRow[];
    porDia:            DiaRow[];
}

function usd(valor: number) {
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', minimumFractionDigits: 4 }).format(valor);
}

function num(n: number) {
    return new Intl.NumberFormat('pt-BR').format(n);
}

function DiffBadge({ atual, anterior }: { atual: number; anterior: number }) {
    if (anterior === 0) return null;
    const diff = ((atual - anterior) / anterior) * 100;
    const up = diff > 0;
    return (
        <span
            className="ml-2 rounded px-1.5 py-0.5 text-[10px] font-semibold"
            style={{ background: up ? 'rgba(239,68,68,0.12)' : 'rgba(52,211,153,0.12)', color: up ? '#f87171' : '#34d399' }}
        >
            {up ? '▲' : '▼'} {Math.abs(diff).toFixed(0)}%
        </span>
    );
}

function StatCard({ label, value, sub }: { label: string; value: string; sub?: React.ReactNode }) {
    return (
        <div
            className="min-w-0 rounded-xl p-3 sm:p-5"
            style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
        >
            <p className="mb-1 text-[11px] uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>{label}</p>
            <p className="break-words text-xl font-semibold tabular-nums sm:text-2xl" style={{ color: 'var(--text-1)' }}>{value}</p>
            {sub && <p className="mt-0.5 text-[11px]" style={{ color: 'var(--text-3)' }}>{sub}</p>}
        </div>
    );
}

function MiniBarChart({ data }: { data: DiaRow[] }) {
    if (data.length === 0) return (
        <div className="flex h-32 items-center justify-center text-sm" style={{ color: 'var(--text-3)' }}>
            Sem dados ainda
        </div>
    );

    const max = Math.max(...data.map(d => d.custo_usd), 0.000001);

    return (
        <div className="flex h-32 items-end gap-[3px]">
            {data.map(d => (
                <div
                    key={d.dia}
                    className="group relative flex-1 rounded-sm transition-opacity hover:opacity-80"
                    style={{ height: `${Math.max((d.custo_usd / max) * 100, 2)}%`, background: 'var(--accent)', opacity: 0.75 }}
                    title={`${d.dia}: ${usd(d.custo_usd)} (${num(d.calls)} calls)`}
                />
            ))}
        </div>
    );
}

export default function TokenUsage({ ia, mes, mesAnterior, cacheHitRate, economiaCacheUsd, porTenant, porDia }: Props) {
    const totalTokensMes = mes.input + mes.output + mes.cache_read;

    return (
        <AppLayout title="Tokens IA">
            <Head title="Tokens IA" />

            <div
                className="mb-6 rounded-xl p-5"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
            >
                <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                            IA configurada
                        </p>
                        <div className="mt-2 flex min-w-0 flex-wrap items-center gap-2">
                            <span className={`h-2.5 w-2.5 rounded-full ${ia.padrao.configurado ? 'bg-emerald-400' : 'bg-red-400'}`} />
                            <p className="text-xl font-semibold" style={{ color: 'var(--text-1)' }}>{ia.padrao.label}</p>
                            <span className={`badge ${ia.padrao.configurado ? 'badge-green' : 'badge-red'}`}>
                                {ia.padrao.configurado ? 'Disponível' : 'Não configurada'}
                            </span>
                        </div>
                        <p className="mt-1 break-all text-xs font-mono" style={{ color: 'var(--text-3)' }}>{ia.padrao.model}</p>
                    </div>

                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                            Fallbacks
                        </p>
                        <div className="mt-2 flex flex-wrap gap-2">
                            {ia.fallbacks.length === 0 ? (
                                <span className="text-sm" style={{ color: 'var(--text-3)' }}>Nenhum</span>
                            ) : ia.fallbacks.map(provider => (
                                <span key={provider.provider} className={`badge ${provider.configurado ? 'badge-green' : 'badge-amber'}`}>
                                    {provider.label} · {provider.configurado ? 'disponível' : 'sem chave'}
                                </span>
                            ))}
                        </div>
                    </div>

                    <div className="lg:text-right">
                        <p className="text-[11px] font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                            Última chamada real
                        </p>
                        {ia.ultima_chamada ? (
                            <>
                                <p className="mt-2 text-sm font-semibold" style={{ color: 'var(--text-1)' }}>
                                    {ia.ultima_chamada.label}
                                </p>
                                <p className="text-xs font-mono" style={{ color: 'var(--text-3)' }}>
                                    {ia.ultima_chamada.model}
                                </p>
                                <p className="mt-1 text-[11px]" style={{ color: 'var(--text-3)' }}>
                                    {new Intl.DateTimeFormat('pt-BR', {
                                        dateStyle: 'short',
                                        timeStyle: 'short',
                                    }).format(new Date(ia.ultima_chamada.em))}
                                </p>
                            </>
                        ) : (
                            <p className="mt-2 text-sm" style={{ color: 'var(--text-3)' }}>Nenhuma chamada registrada</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Stat cards */}
            <div className="mb-6 grid grid-cols-2 gap-2 sm:gap-4 lg:grid-cols-4">
                <StatCard
                    label="Custo do mês"
                    value={usd(mes.custo_usd)}
                    sub={<>mês anterior: {usd(mesAnterior.custo_usd)} <DiffBadge atual={mes.custo_usd} anterior={mesAnterior.custo_usd} /></>}
                />
                <StatCard
                    label="Chamadas API"
                    value={num(mes.calls)}
                    sub="mensagens processadas"
                />
                <StatCard
                    label="Cache hit rate"
                    value={`${cacheHitRate}%`}
                    sub="tokens servidos do cache"
                />
                <StatCard
                    label="Economia de cache"
                    value={usd(economiaCacheUsd)}
                    sub="vs. sem prompt caching"
                />
            </div>

            {/* Gráfico últimos 30 dias */}
            <div
                className="mb-6 rounded-xl p-5"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
            >
                <p className="mb-4 text-[11px] font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                    Custo por dia — últimos 30 dias
                </p>
                <MiniBarChart data={porDia} />
                <div className="mt-2 flex justify-between text-[10px]" style={{ color: 'var(--text-3)' }}>
                    <span>{porDia[0]?.dia ?? ''}</span>
                    <span>{porDia[porDia.length - 1]?.dia ?? ''}</span>
                </div>
            </div>

            {/* Tokens do mês: breakdown */}
            <div
                className="mb-6 rounded-xl p-5"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
            >
                <p className="mb-4 text-[11px] font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                    Breakdown de tokens — mês atual
                </p>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    {[
                        { label: 'Input', value: mes.input,       custo: mes.input * 0.000001,    cor: '#a5b4fc' },
                        { label: 'Output', value: mes.output,     custo: mes.output * 0.000005,   cor: '#34d399' },
                        { label: 'Cache write', value: mes.cache_write, custo: mes.cache_write * 0.00000125, cor: '#fbbf24' },
                        { label: 'Cache read', value: mes.cache_read,   custo: mes.cache_read * 0.0000001,  cor: '#60a5fa' },
                    ].map(item => (
                        <div key={item.label}>
                            <div className="flex items-center gap-1.5 mb-1">
                                <span className="h-2 w-2 rounded-full" style={{ background: item.cor }} />
                                <span className="text-[11px]" style={{ color: 'var(--text-3)' }}>{item.label}</span>
                            </div>
                            <p className="text-base font-semibold tabular-nums" style={{ color: 'var(--text-1)' }}>
                                {num(item.value)}
                            </p>
                            <p className="text-[11px]" style={{ color: 'var(--text-3)' }}>{usd(item.custo)}</p>
                        </div>
                    ))}
                </div>
            </div>

            {/* Tabela por tenant */}
            <div
                className="overflow-hidden rounded-xl"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
            >
                <div className="px-5 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                    <p className="text-[11px] font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                        Consumo por empresa — mês atual
                    </p>
                </div>

                {porTenant.length === 0 ? (
                    <div className="py-12 text-center text-sm" style={{ color: 'var(--text-3)' }}>
                        Nenhum registro ainda
                    </div>
                ) : (
                    <>
                    <div className="divide-y lg:hidden" style={{ borderColor: 'var(--border)' }}>
                        {porTenant.map(t => (
                            <article key={t.id} className="px-4 py-4">
                                <div className="flex min-w-0 items-start justify-between gap-3">
                                    <h2 className="min-w-0 truncate font-medium" style={{ color: 'var(--text-1)' }}>{t.nome}</h2>
                                    <span className="shrink-0 font-semibold tabular-nums" style={{ color: 'var(--jade)' }}>{usd(t.custo_usd)}</span>
                                </div>
                                <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <div>
                                        <dt className="text-xs" style={{ color: 'var(--text-3)' }}>Chamadas</dt>
                                        <dd className="tabular-nums" style={{ color: 'var(--text-2)' }}>{num(t.calls)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-xs" style={{ color: 'var(--text-3)' }}>Input / output</dt>
                                        <dd className="tabular-nums" style={{ color: 'var(--text-2)' }}>{num(t.input)} / {num(t.output)}</dd>
                                    </div>
                                    <div className="col-span-2">
                                        <dt className="text-xs" style={{ color: 'var(--text-3)' }}>Tokens lidos do cache</dt>
                                        <dd className="tabular-nums" style={{ color: 'var(--text-2)' }}>{num(t.cache_read)}</dd>
                                    </div>
                                </dl>
                            </article>
                        ))}
                    </div>
                    <div className="hidden max-w-full overflow-x-auto overscroll-x-contain lg:block">
                    <table className="w-full min-w-[760px] text-[13px]">
                        <thead>
                            <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                {['Empresa', 'Calls', 'Input', 'Output', 'Cache read', 'Custo USD'].map(h => (
                                    <th
                                        key={h}
                                        className="px-5 py-3 text-left text-[11px] font-medium uppercase tracking-wider"
                                        style={{ color: 'var(--text-3)' }}
                                    >
                                        {h}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y" style={{ borderColor: 'var(--border)' }}>
                            {porTenant.map(t => (
                                <tr key={t.id} className="transition-colors hover:bg-white/[0.02]">
                                    <td className="px-5 py-3 font-medium" style={{ color: 'var(--text-1)' }}>{t.nome}</td>
                                    <td className="px-5 py-3 tabular-nums" style={{ color: 'var(--text-2)' }}>{num(t.calls)}</td>
                                    <td className="px-5 py-3 tabular-nums" style={{ color: 'var(--text-2)' }}>{num(t.input)}</td>
                                    <td className="px-5 py-3 tabular-nums" style={{ color: 'var(--text-2)' }}>{num(t.output)}</td>
                                    <td className="px-5 py-3 tabular-nums" style={{ color: 'var(--text-2)' }}>{num(t.cache_read)}</td>
                                    <td className="px-5 py-3 tabular-nums font-semibold" style={{ color: 'var(--jade)' }}>
                                        {usd(t.custo_usd)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr style={{ borderTop: '1px solid var(--border)', background: 'rgba(255,255,255,0.02)' }}>
                                <td className="px-5 py-3 text-[11px] font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>Total</td>
                                <td className="px-5 py-3 tabular-nums font-semibold" style={{ color: 'var(--text-2)' }}>{num(mes.calls)}</td>
                                <td className="px-5 py-3 tabular-nums font-semibold" style={{ color: 'var(--text-2)' }}>{num(mes.input)}</td>
                                <td className="px-5 py-3 tabular-nums font-semibold" style={{ color: 'var(--text-2)' }}>{num(mes.output)}</td>
                                <td className="px-5 py-3 tabular-nums font-semibold" style={{ color: 'var(--text-2)' }}>{num(mes.cache_read)}</td>
                                <td className="px-5 py-3 tabular-nums font-bold" style={{ color: 'var(--jade)' }}>{usd(mes.custo_usd)}</td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>
                    </>
                )}
            </div>

            <p className="mt-3 text-center text-[11px]" style={{ color: 'var(--text-3)' }}>
                Preços: Haiku 4.5 · Input $1/MTok · Output $5/MTok · Cache write $1.25/MTok · Cache read $0.10/MTok
            </p>
        </AppLayout>
    );
}
