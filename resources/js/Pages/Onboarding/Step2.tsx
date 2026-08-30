import { Head, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import { useState } from 'react';
import ForceDark from '@/Components/ForceDark';

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
        router.post(route('onboarding.checkout'), { plano: slug }, {
            onFinish: () => setLoading(null),
        });
    };

    const pular = () => {
        setLoading('pular');
        router.post(route('onboarding.pular'), {}, {
            onFinish: () => setLoading(null),
        });
    };

    const planoList = Object.entries(planos);

    return (
        <ForceDark>
        <div className="flex min-h-screen flex-col" style={{ background: 'var(--bg-app)' }}>
            <Head title="Escolha seu plano" />

            {/* Step indicator */}
            <div className="flex items-center justify-center gap-2 px-6 py-3" style={{ borderBottom: '1px solid var(--border)' }}>
                {[{ n: 1, label: 'Conta' }, { n: 2, label: 'Plano' }, { n: 3, label: 'Agenda' }].map((s, i) => (
                    <div key={s.n} className="flex items-center gap-2">
                        {i > 0 && <div className="h-px w-8" style={{ background: s.n <= 2 ? 'var(--accent)' : 'var(--border)' }} />}
                        <div className="flex items-center gap-1.5">
                            <div className="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold" style={{ background: s.n <= 2 ? 'var(--accent)' : 'var(--border-strong)', color: s.n <= 2 ? 'white' : 'var(--text-3)' }}>
                                {s.n < 2 ? '✓' : s.n}
                            </div>
                            <span className="hidden text-[11px] sm:block" style={{ color: s.n <= 2 ? 'var(--text-1)' : 'var(--text-3)' }}>{s.label}</span>
                        </div>
                    </div>
                ))}
            </div>

            <div className="flex flex-1 flex-col items-center px-4 py-6 sm:py-10">
                <div className="mb-7 text-center sm:mb-10">
                    <h1 className="text-2xl text-primary sm:text-3xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                        Comece seu teste grátis
                    </h1>
                    <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>Use todos os recursos essenciais por 14 dias, sem informar cartão.</p>
                </div>

                <div className="mb-7 flex w-full max-w-4xl flex-col gap-4 rounded-xl p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5" style={{ background: 'var(--accent-light)', border: '1px solid var(--accent)' }}>
                    <div>
                        <p className="text-sm font-semibold text-primary">Configure sua agenda agora</p>
                        <p className="mt-1 text-xs leading-5" style={{ color: 'var(--text-2)' }}>Você poderá escolher ou trocar de plano depois, dentro do painel.</p>
                    </div>
                    <button
                        type="button"
                        onClick={pular}
                        disabled={loading !== null}
                        className="btn-primary min-h-11 shrink-0 justify-center sm:min-w-48"
                    >
                        {loading === 'pular' ? 'Preparando…' : 'Começar teste grátis'}
                    </button>
                </div>

                <div className="mb-4 flex w-full max-w-4xl items-center gap-3">
                    <span className="h-px flex-1" style={{ background: 'var(--border)' }} />
                    <p className="shrink-0 text-xs" style={{ color: 'var(--text-3)' }}>Ou indique o plano que pretende usar</p>
                    <span className="h-px flex-1" style={{ background: 'var(--border)' }} />
                </div>

                <div className="grid w-full max-w-4xl gap-5 sm:grid-cols-3">
                    {planoList.map(([slug, plano]) => (
                        <div
                            key={slug}
                            className="flex flex-col rounded-xl p-4 transition-all sm:p-6"
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
                                type="button"
                                onClick={() => escolherPlano(slug)}
                                disabled={loading !== null}
                                className="w-full rounded-lg py-2.5 text-sm font-medium transition-all hover:brightness-110 disabled:opacity-50"
                                style={
                                    plano.destaque
                                        ? { background: 'var(--accent)', color: 'white' }
                                        : { background: 'var(--bg-surface-2)', color: 'var(--text-2)', border: '1px solid var(--border-strong)' }
                                }
                            >
                                {loading === slug ? 'Aguarde…' : 'Usar este plano depois'}
                            </button>
                        </div>
                    ))}
                </div>

                <p className="mt-6 text-center text-xs" style={{ color: 'var(--text-3)' }}>Nenhuma cobrança será criada nesta etapa.</p>
            </div>
        </div>
        </ForceDark>
    );
}
