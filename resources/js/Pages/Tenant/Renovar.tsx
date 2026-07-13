import { Head, router, usePage } from '@inertiajs/react';
import { PageProps, Tenant } from '@/types';
import { useMemo, useState } from 'react';

interface Plano {
    nome: string;
    valor: number;
    descricao: string;
}

interface Props extends PageProps {
    tenant: Tenant;
    planos: Record<string, Plano>;
    planoAtual: string;
}

type Ciclo = 'mensal' | 'anual';
type Metodo = 'CREDIT_CARD' | 'PIX';

export default function Renovar({ planos, planoAtual }: Props) {
    const flash = usePage<PageProps & { flash?: { erro?: string } }>().props.flash;
    const [plano, setPlano] = useState(planos[planoAtual] ? planoAtual : 'starter');
    const [ciclo, setCiclo] = useState<Ciclo>('mensal');
    const [metodo, setMetodo] = useState<Metodo>('CREDIT_CARD');
    const [processing, setProcessing] = useState(false);
    const selecionado = planos[plano];
    const valor = useMemo(() => ciclo === 'anual' ? selecionado.valor * 10 : selecionado.valor, [ciclo, selecionado]);
    const valorStr = valor.toFixed(2).replace('.', ',');

    const pagar = () => {
        setProcessing(true);
        router.post(route('tenant.renovar.store'), { plano, ciclo, metodo }, {
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div className="min-h-screen px-4 py-10" style={{ background: 'var(--bg-app)' }}>
            <Head title="Ativar assinatura" />
            <main className="mx-auto w-full max-w-3xl">
                <div className="text-center">
                    <div className="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl" style={{ background: 'rgba(99,102,241,.12)', border: '1px solid rgba(99,102,241,.25)' }}>
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="text-indigo-400"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h2"/></svg>
                    </div>
                    <h1 className="text-4xl text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>Ative sua assinatura</h1>
                    <p className="mt-2 text-sm" style={{ color: 'var(--text-3)' }}>Planos fixos, sem taxa por agendamento. Pague com cartão ou Pix.</p>
                </div>

                {flash?.erro && <div className="mt-6 rounded-xl border border-red-500/20 bg-red-500/10 px-4 py-3 text-sm text-red-300">{flash.erro}</div>}

                <Section title="1. Escolha o plano">
                    <div className="grid gap-3 sm:grid-cols-3">
                        {Object.entries(planos).map(([slug, item]) => (
                            <button key={slug} type="button" onClick={() => setPlano(slug)} className="rounded-xl p-4 text-left transition-all" style={{ background: 'var(--bg-surface)', border: `2px solid ${plano === slug ? 'var(--accent)' : 'var(--border)'}` }}>
                                <span className="font-semibold text-primary">{item.nome}</span>
                                <span className="mt-1 block text-xs" style={{ color: 'var(--text-3)' }}>{item.descricao}</span>
                                <span className="mt-3 block text-lg font-bold text-primary">R$ {item.valor.toFixed(2).replace('.', ',')}<small className="font-normal" style={{ color: 'var(--text-3)' }}>/mês</small></span>
                            </button>
                        ))}
                    </div>
                </Section>

                <div className="grid gap-6 sm:grid-cols-2">
                    <Section title="2. Período">
                        <Option active={ciclo === 'mensal'} onClick={() => setCiclo('mensal')} title="Mensal" description="Cobrança a cada mês" />
                        <Option active={ciclo === 'anual'} onClick={() => setCiclo('anual')} title="Anual" description="Pague 10 meses e use por 12" badge="2 meses grátis" />
                    </Section>
                    <Section title="3. Pagamento">
                        <Option active={metodo === 'CREDIT_CARD'} onClick={() => setMetodo('CREDIT_CARD')} title="Cartão de crédito" description="Renovação automática" />
                        <Option active={metodo === 'PIX'} onClick={() => setMetodo('PIX')} title="Pix" description="Renove ao final do período" />
                    </Section>
                </div>

                <div className="mt-7 rounded-2xl p-5" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}>
                    <div className="flex items-center justify-between gap-4">
                        <div><p className="font-semibold text-primary">{selecionado.nome} {ciclo}</p><p className="text-xs" style={{ color: 'var(--text-3)' }}>{metodo === 'CREDIT_CARD' ? 'Renovação automática no cartão' : 'Pagamento via Pix'}</p></div>
                        <p className="text-2xl font-bold text-primary">R$ {valorStr}</p>
                    </div>
                    <button onClick={pagar} disabled={processing} className="btn-primary mt-5 w-full justify-center py-3 text-base disabled:opacity-60">{processing ? 'Abrindo pagamento…' : `Ir para pagamento — R$ ${valorStr}`}</button>
                    <p className="mt-3 text-center text-[11px]" style={{ color: 'var(--text-3)' }}>Pagamento processado em ambiente seguro pelo Asaas.</p>
                </div>
            </main>
        </div>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return <section className="mt-7"><p className="mb-3 text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>{title}</p><div className="space-y-2">{children}</div></section>;
}

function Option({ active, onClick, title, description, badge }: { active: boolean; onClick: () => void; title: string; description: string; badge?: string }) {
    return <button type="button" onClick={onClick} className="flex w-full items-center gap-3 rounded-xl p-3 text-left" style={{ background: 'var(--bg-surface)', border: `2px solid ${active ? 'var(--accent)' : 'var(--border)'}` }}>
        <span className="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border" style={{ borderColor: active ? 'var(--accent)' : 'var(--border-strong)' }}>{active && <span className="h-2 w-2 rounded-full" style={{ background: 'var(--accent)' }} />}</span>
        <span className="flex-1"><span className="text-sm font-medium text-primary">{title}</span><span className="block text-xs" style={{ color: 'var(--text-3)' }}>{description}</span></span>
        {badge && <span className="rounded-full bg-emerald-500/10 px-2 py-1 text-[10px] font-semibold text-emerald-400">{badge}</span>}
    </button>;
}
