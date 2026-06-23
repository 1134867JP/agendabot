import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';

interface Plano {
    nome: string;
    valor: number;
    recursos: number | null;
    destaque: boolean;
    features: string[];
    nao_inclui: string[];
}

interface Props extends PageProps {
    planos: Record<string, Plano>;
}

export default function OnboardingStep2({ planos }: Props) {
    const [loading, setLoading] = useState<string | null>(null);

    const escolherPlano = (slug: string) => {
        setLoading(slug);
        router.post(route('onboarding.checkout'), { plano: slug });
    };

    const pular = () => {
        setLoading('pular');
        router.post(route('onboarding.pular'));
    };

    const planoList = Object.entries(planos);

    return (
        <div className="flex min-h-screen flex-col" style={{ background: 'var(--bg-app)' }}>
            <Head title="Escolha seu plano" />

            <div className="h-0.5 w-full" style={{ background: 'var(--border)' }}>
                <div className="h-0.5 w-2/3 transition-all" style={{ background: 'var(--accent)' }} />
            </div>

            <div className="flex flex-1 flex-col items-center px-4 py-10">
                <div className="mb-10 text-center">
                    <p className="mb-1 text-[10px] font-medium uppercase tracking-widest" style={{ color: 'var(--accent)' }}>
                        Passo 2 de 3
                    </p>
                    <h1 className="text-3xl text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                        Escolha seu plano
                    </h1>
                    <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>Comece grátis por 14 dias. Cancele quando quiser.</p>
                </div>

                <div className="grid w-full max-w-4xl gap-5 sm:grid-cols-3">
                    {planoList.map(([slug, plano]) => (
                        <div
                            key={slug}
                            className="flex flex-col rounded-xl p-6 transition-all"
                            style={{
                                background: 'var(--bg-surface)',
                                border: `2px solid ${plano.destaque ? 'var(--accent)' : 'var(--border)'}`,
                            }}
                        >
                            {plano.destaque && (
                                <div
                                    className="mb-3 self-start rounded-full px-2.5 py-0.5 text-[11px] font-semibold text-primary"
                                    style={{ background: 'var(--accent)' }}
                                >
                                    Mais popular
                                </div>
                            )}
                            <h2 className="text-lg text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                                {plano.nome}
                            </h2>
                            <div className="mt-2 mb-5">
                                <span className="text-3xl font-bold text-primary">
                                    R${plano.valor.toFixed(2).replace('.', ',')}
                                </span>
                                <span className="text-sm" style={{ color: 'var(--text-3)' }}>/mês</span>
                            </div>

                            <ul className="mb-6 flex-1 space-y-2">
                                {plano.features.map(f => (
                                    <li key={f} className="flex items-start gap-2 text-sm">
                                        <span className="mt-0.5 shrink-0 text-emerald-400">✓</span>
                                        <span style={{ color: 'var(--text-2)' }}>{f}</span>
                                    </li>
                                ))}
                                {plano.nao_inclui.map(f => (
                                    <li key={f} className="flex items-start gap-2 text-sm">
                                        <span className="mt-0.5 shrink-0" style={{ color: 'var(--text-3)' }}>—</span>
                                        <span style={{ color: 'var(--text-3)' }}>{f}</span>
                                    </li>
                                ))}
                            </ul>

                            <button
                                onClick={() => escolherPlano(slug)}
                                disabled={loading !== null}
                                className="w-full rounded-lg py-2.5 text-sm font-medium transition-all hover:brightness-110 disabled:opacity-50"
                                style={
                                    plano.destaque
                                        ? { background: 'var(--accent)', color: 'white' }
                                        : { background: 'var(--bg-surface-2)', color: 'var(--text-2)', border: '1px solid var(--border-strong)' }
                                }
                            >
                                {loading === slug ? 'Aguarde…' : 'Começar grátis'}
                            </button>
                        </div>
                    ))}
                </div>

                <button
                    onClick={pular}
                    disabled={loading !== null}
                    className="mt-8 text-sm transition-colors disabled:opacity-50"
                    style={{ color: 'var(--text-3)' }}
                    onMouseEnter={e => (e.currentTarget.style.color = 'var(--text-2)')}
                    onMouseLeave={e => (e.currentTarget.style.color = 'var(--text-3)')}
                >
                    Continuar com o período de teste gratuito →
                </button>
                <p className="mt-2 text-xs" style={{ color: 'var(--text-3)' }}>
                    Sem taxa de cancelamento. Cancele a qualquer momento.
                </p>
            </div>
        </div>
    );
}
