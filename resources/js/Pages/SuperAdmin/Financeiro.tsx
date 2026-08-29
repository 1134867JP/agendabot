import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';

interface Row {
    id: number;
    nome: string;
    plano: string | null;
    isento: boolean;
    mensalidade: number;
    receita_bot: number;
    receita_total: number;
    custo_ia_brl: number;
    lucro: number;
    margem: number | null;
    tokens: { input: number; output: number; cache_write: number; cache_read: number };
}

interface Totais {
    receita_total: number;
    custo_ia_brl: number;
    lucro: number;
}

interface Props extends PageProps {
    mes: string;
    rows: Row[];
    totais: Totais;
}

const R = (v: number) =>
    v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const pct = (v: number | null) =>
    v === null ? '—' : `${v.toFixed(1)}%`;

const PLANO_LABEL: Record<string, string> = {
    starter: 'Starter', pro: 'Pro', business: 'Business',
};

function MargemBadge({ margem }: { margem: number | null }) {
    if (margem === null) return <span style={{ color: 'var(--text-3)' }}>—</span>;
    const cor = margem >= 70 ? '#34d399' : margem >= 40 ? '#fbbf24' : '#f87171';
    return <span style={{ color: cor, fontWeight: 600 }}>{pct(margem)}</span>;
}

export default function Financeiro({ mes, rows, totais }: Props) {
    const meses = Array.from({ length: 12 }, (_, i) => {
        const d = new Date();
        d.setDate(1);
        d.setMonth(d.getMonth() - i);
        return d.toISOString().slice(0, 7);
    });

    return (
        <AppLayout title="Financeiro">
            <Head title="Financeiro" />

            {/* Header */}
            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-lg font-semibold" style={{ color: 'var(--text-1)' }}>
                        Receita vs Custo de IA
                    </h1>
                    <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                        Comparativo mensal por tenant
                    </p>
                </div>
                <select
                    aria-label="Mês de referência"
                    value={mes}
                    onChange={e => router.get(route('superadmin.financeiro'), { mes: e.target.value })}
                    className="rounded-lg border px-3 py-1.5 text-sm"
                    style={{ background: 'var(--bg-surface-2)', borderColor: 'var(--border)', color: 'var(--text-1)' }}
                >
                    {meses.map(m => (
                        <option key={m} value={m}>{m}</option>
                    ))}
                </select>
            </div>

            {/* Cards totais */}
            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                {[
                    { label: 'Receita total', value: R(totais.receita_total), color: '#34d399' },
                    { label: 'Custo IA (BRL)', value: R(totais.custo_ia_brl), color: '#f87171' },
                    { label: 'Lucro estimado', value: R(totais.lucro), color: totais.lucro >= 0 ? '#34d399' : '#f87171' },
                ].map(c => (
                    <div key={c.label} className="card p-4">
                        <p className="text-xs font-medium uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>{c.label}</p>
                        <p className="mt-1 text-2xl font-bold" style={{ color: c.color }}>{c.value}</p>
                    </div>
                ))}
            </div>

            {/* Resumo por tenant no mobile */}
            <div className="space-y-3 lg:hidden">
                {rows.length === 0 ? (
                    <div className="card px-4 py-10 text-center text-sm" style={{ color: 'var(--text-3)' }}>
                        Nenhum tenant ativo neste período.
                    </div>
                ) : rows.map(r => (
                    <article key={r.id} className="card p-4">
                        <div className="flex min-w-0 items-start justify-between gap-3">
                            <div className="min-w-0">
                                <h2 className="truncate font-semibold" style={{ color: 'var(--text-1)' }}>{r.nome}</h2>
                                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                    {r.plano ? PLANO_LABEL[r.plano] ?? r.plano : 'Sem plano'}
                                </p>
                            </div>
                            {r.isento && <span className="badge badge-yellow shrink-0 text-[10px]">Isento</span>}
                        </div>
                        <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                            <div>
                                <dt className="text-xs" style={{ color: 'var(--text-3)' }}>Receita</dt>
                                <dd className="mt-0.5 font-semibold" style={{ color: '#34d399' }}>{R(r.receita_total)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs" style={{ color: 'var(--text-3)' }}>Custo IA</dt>
                                <dd className="mt-0.5" style={{ color: r.custo_ia_brl > 0 ? '#f87171' : 'var(--text-3)' }}>
                                    {r.custo_ia_brl > 0 ? R(r.custo_ia_brl) : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs" style={{ color: 'var(--text-3)' }}>Lucro estimado</dt>
                                <dd className="mt-0.5 font-semibold" style={{ color: r.lucro >= 0 ? '#34d399' : '#f87171' }}>{R(r.lucro)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs" style={{ color: 'var(--text-3)' }}>Margem</dt>
                                <dd className="mt-0.5"><MargemBadge margem={r.margem} /></dd>
                            </div>
                        </dl>
                    </article>
                ))}
            </div>

            {/* Tabela para telas amplas */}
            <div className="card hidden overflow-hidden lg:block">
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead>
                            <tr style={{ borderBottom: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
                                {['Tenant', 'Plano', 'Mensalidade', 'Taxa bot', 'Receita', 'Custo IA', 'Lucro', 'Margem'].map(h => (
                                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr><td colSpan={8} className="px-4 py-10 text-center" style={{ color: 'var(--text-3)' }}>Nenhum tenant ativo.</td></tr>
                            )}
                            {rows.map(r => (
                                <tr key={r.id} className="table-row-hover" style={{ borderBottom: '1px solid var(--border)' }}>
                                    <td className="px-4 py-3">
                                        <span className="font-medium" style={{ color: 'var(--text-1)' }}>{r.nome}</span>
                                        {r.isento && (
                                            <span className="ml-2 badge badge-yellow text-[10px]">Isento</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>
                                        {r.plano ? PLANO_LABEL[r.plano] ?? r.plano : '—'}
                                    </td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>
                                        {r.isento ? <span style={{ color: 'var(--text-3)' }}>—</span> : R(r.mensalidade)}
                                    </td>
                                    <td className="px-4 py-3" style={{ color: 'var(--text-2)' }}>
                                        {r.receita_bot > 0 ? R(r.receita_bot) : <span style={{ color: 'var(--text-3)' }}>—</span>}
                                    </td>
                                    <td className="px-4 py-3 font-semibold" style={{ color: '#34d399' }}>
                                        {R(r.receita_total)}
                                    </td>
                                    <td className="px-4 py-3" style={{ color: r.custo_ia_brl > 0 ? '#f87171' : 'var(--text-3)' }}>
                                        {r.custo_ia_brl > 0 ? R(r.custo_ia_brl) : '—'}
                                    </td>
                                    <td className="px-4 py-3 font-semibold" style={{ color: r.lucro >= 0 ? '#34d399' : '#f87171' }}>
                                        {R(r.lucro)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <MargemBadge margem={r.margem} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        {rows.length > 1 && (
                            <tfoot>
                                <tr style={{ borderTop: '2px solid var(--border)', background: 'var(--bg-surface-2)' }}>
                                    <td colSpan={4} className="px-4 py-3 text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>Total</td>
                                    <td className="px-4 py-3 font-bold" style={{ color: '#34d399' }}>{R(totais.receita_total)}</td>
                                    <td className="px-4 py-3 font-bold" style={{ color: '#f87171' }}>{R(totais.custo_ia_brl)}</td>
                                    <td className="px-4 py-3 font-bold" style={{ color: totais.lucro >= 0 ? '#34d399' : '#f87171' }}>{R(totais.lucro)}</td>
                                    <td />
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>

            {/* Nota cotação */}
            <p className="mt-3 text-right text-xs" style={{ color: 'var(--text-3)' }}>
                * Custo IA convertido com cotação aproximada USD R$ 5,70. Preços Haiku 4.5: $1,00/MTok input · $5,00/MTok output · $1,25/MTok cache write · $0,10/MTok cache read.
            </p>
        </AppLayout>
    );
}
