import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PageProps } from '@/types';
import LandingLayout from '@/Layouts/LandingLayout';

const INDIGO = '#6366F1';
const DARK   = '#090E1A';

interface Plano {
    nome: string;
    valor: number;
    recursos: number | null;
    descricao: string;
    destaque: boolean;
    features: string[];
    nao_inclui: string[];
}

interface Props extends PageProps {
    planos: Record<string, Plano>;
}

const faqs = [
    {
        q: 'Preciso de cartão de crédito para o trial?',
        a: 'Não. Você cria sua conta e usa por 14 dias sem fornecer nenhum dado de pagamento. O cartão só é necessário ao escolher um plano.',
    },
    {
        q: 'Posso cancelar a qualquer momento?',
        a: 'Sim, sem multa ou fidelidade. Cancele quando quiser diretamente pelo painel.',
    },
    {
        q: 'Meus dados ficam salvos se eu cancelar?',
        a: 'Sim. Após o cancelamento, seus dados ficam disponíveis por 30 dias antes de serem removidos.',
    },
    {
        q: 'Como funciona o WhatsApp?',
        a: 'Você conecta o seu próprio número de WhatsApp Business. O AgendaBot cria uma instância dedicada para seu número.',
    },
];

export default function Precos({ planos }: Props) {
    const [anual, setAnual] = useState(false);
    const [faqOpen, setFaqOpen] = useState<number | null>(null);

    const planoList = Object.entries(planos);

    const valorFinal = (valor: number) =>
        anual ? (valor * 10).toFixed(2).replace('.', ',') : valor.toFixed(2).replace('.', ',');

    const descAnual = (valor: number) =>
        (valor * 2).toFixed(2).replace('.', ',');

    return (
        <LandingLayout currentPage="precos">
            <Head title="Planos — AgendaBot" />

            <div className="px-6 py-16">
                <div className="mx-auto max-w-5xl">
                    {/* Header */}
                    <div className="mb-12 text-center">
                        <h1
                            className="mb-3 text-4xl font-bold"
                            style={{ fontFamily: 'Syne, sans-serif' }}
                        >
                            Planos simples e transparentes
                        </h1>
                        <p className="text-base" style={{ color: 'rgba(255,255,255,0.5)' }}>
                            14 dias grátis em qualquer plano. Sem cartão para começar.
                        </p>

                        {/* Toggle mensal/anual */}
                        <div className="mt-6 inline-flex items-center gap-3 rounded-full p-1" style={{ background: 'rgba(255,255,255,0.06)', border: '1px solid rgba(255,255,255,0.1)' }}>
                            <button
                                onClick={() => setAnual(false)}
                                className="rounded-full px-4 py-1.5 text-sm font-medium transition-all"
                                style={{
                                    background: !anual ? INDIGO : 'transparent',
                                    color: !anual ? 'white' : 'rgba(255,255,255,0.5)',
                                }}
                            >
                                Mensal
                            </button>
                            <button
                                onClick={() => setAnual(true)}
                                className="flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium transition-all"
                                style={{
                                    background: anual ? INDIGO : 'transparent',
                                    color: anual ? 'white' : 'rgba(255,255,255,0.5)',
                                }}
                            >
                                Anual
                                <span className="rounded-full px-1.5 py-0.5 text-xs" style={{ background: 'rgba(99,102,241,0.3)', color: '#A5B4FC' }}>
                                    2 meses grátis
                                </span>
                            </button>
                        </div>
                    </div>

                    {/* Plans grid */}
                    <div className="grid gap-6 md:grid-cols-3">
                        {planoList.map(([slug, plano]) => (
                            <div
                                key={slug}
                                className={`flex flex-col rounded-2xl p-6 transition-all ${plano.destaque ? 'ring-2' : ''}`}
                                style={{
                                    background: plano.destaque ? 'rgba(99,102,241,0.1)' : 'rgba(255,255,255,0.04)',
                                    border: `2px solid ${plano.destaque ? INDIGO : 'rgba(255,255,255,0.1)'}`,
                                }}
                            >
                                {plano.destaque && (
                                    <div
                                        className="mb-3 self-start rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        style={{ background: INDIGO, color: 'white' }}
                                    >
                                        Mais popular
                                    </div>
                                )}

                                <h2 className="text-lg font-semibold" style={{ fontFamily: 'Syne, sans-serif' }}>
                                    {plano.nome}
                                </h2>

                                <div className="mt-3 mb-1">
                                    <span className="text-4xl font-bold">
                                        R${valorFinal(plano.valor)}
                                    </span>
                                    <span className="text-sm" style={{ color: 'rgba(255,255,255,0.45)' }}>
                                        /{anual ? 'ano' : 'mês'}
                                    </span>
                                </div>
                                {anual && (
                                    <p className="mb-4 text-xs" style={{ color: 'rgba(255,255,255,0.4)' }}>
                                        economize R${descAnual(plano.valor)}
                                    </p>
                                )}

                                <div className="mb-6 mt-2 h-px" style={{ background: 'rgba(255,255,255,0.1)' }} />

                                <ul className="mb-8 flex-1 space-y-2.5">
                                    {plano.features.map((f) => (
                                        <li key={f} className="flex items-start gap-2 text-sm">
                                            <span className="mt-0.5 shrink-0 text-emerald-400">✓</span>
                                            <span style={{ color: 'rgba(255,255,255,0.75)' }}>{f}</span>
                                        </li>
                                    ))}
                                    {plano.nao_inclui.map((f) => (
                                        <li key={f} className="flex items-start gap-2 text-sm">
                                            <span className="mt-0.5 shrink-0" style={{ color: 'rgba(255,255,255,0.2)' }}>—</span>
                                            <span style={{ color: 'rgba(255,255,255,0.3)' }}>{f}</span>
                                        </li>
                                    ))}
                                </ul>

                                <Link
                                    href={route('onboarding.step1')}
                                    className="w-full rounded-xl py-2.5 text-center text-sm font-semibold transition-all hover:brightness-110"
                                    style={
                                        plano.destaque
                                            ? { background: INDIGO, color: 'white' }
                                            : { background: 'rgba(255,255,255,0.08)', color: 'rgba(255,255,255,0.8)' }
                                    }
                                >
                                    Começar grátis
                                </Link>
                            </div>
                        ))}
                    </div>

                    {/* FAQ */}
                    <div className="mt-20">
                        <h2
                            className="mb-8 text-center text-2xl font-bold"
                            style={{ fontFamily: 'Syne, sans-serif' }}
                        >
                            Perguntas frequentes
                        </h2>
                        <div className="space-y-3">
                            {faqs.map((faq, i) => (
                                <div
                                    key={i}
                                    className="rounded-xl overflow-hidden"
                                    style={{ border: '1px solid rgba(255,255,255,0.08)' }}
                                >
                                    <button
                                        onClick={() => setFaqOpen(faqOpen === i ? null : i)}
                                        className="flex w-full items-center justify-between px-5 py-4 text-left text-sm font-medium transition-colors"
                                        style={{ background: 'rgba(255,255,255,0.04)' }}
                                    >
                                        {faq.q}
                                        <span
                                            className="ml-4 shrink-0 text-lg transition-transform duration-200"
                                            style={{
                                                color: 'rgba(255,255,255,0.4)',
                                                transform: faqOpen === i ? 'rotate(45deg)' : 'rotate(0)',
                                            }}
                                        >
                                            +
                                        </span>
                                    </button>
                                    {faqOpen === i && (
                                        <div
                                            className="px-5 pb-4 text-sm"
                                            style={{ color: 'rgba(255,255,255,0.55)' }}
                                        >
                                            {faq.a}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

        </LandingLayout>
    );
}
