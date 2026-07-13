import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PageProps } from '@/types';
import LandingLayout from '@/Layouts/LandingLayout';

const INDIGO = '#6366F1';
const JADE   = '#00a884';

interface Plano {
    nome: string;
    valor: number;
    profissionais: number | null;
    limite_bot_mes: number | null;
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
        q: 'Existe taxa por agendamento?',
        a: 'Não. O valor do plano é fixo e os agendamentos incluídos não geram cobranças adicionais.',
    },
    {
        q: 'Quais formas de pagamento são aceitas?',
        a: 'Você pode pagar com cartão de crédito, com renovação automática, ou via Pix. No plano anual, paga o equivalente a 10 meses e usa por 12.',
    },
    {
        q: 'O que acontece quando atinjo o limite de agendamentos?',
        a: 'Você recebe um aviso antes de atingir o limite e pode fazer upgrade pelo painel. Seus agendamentos existentes continuam disponíveis.',
    },
    {
        q: 'Preciso de cartão de crédito para o trial?',
        a: 'Não. Você usa o sistema por 14 dias e escolhe a forma de pagamento somente quando decidir ativar a assinatura.',
    },
    {
        q: 'Posso cancelar a qualquer momento?',
        a: 'Sim, sem multa ou fidelidade. O cancelamento pode ser feito diretamente pelo painel.',
    },
    {
        q: 'Como funciona o WhatsApp?',
        a: 'Você conecta o número do seu estabelecimento e o assistente passa a atender e organizar agendamentos automaticamente.',
    },
];

function CheckIcon() {
    return (
        <svg width={13} height={13} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"
            strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 shrink-0" style={{ color: JADE }}>
            <polyline points="20 6 9 17 4 12" />
        </svg>
    );
}

export default function Precos({ planos }: Props) {
    const [anual, setAnual] = useState(false);
    const [faqOpen, setFaqOpen] = useState<number | null>(null);

    const planoList = Object.entries(planos);

    const valorMensal = (valor: number) => valor.toFixed(2).replace('.', ',');
    const valorAnual  = (valor: number) => (valor * 10).toFixed(2).replace('.', ',');
    const economiaAnual = (valor: number) => (valor * 2).toFixed(2).replace('.', ',');

    return (
        <LandingLayout currentPage="precos">
            <Head title="Planos — AgendaBot" />

            {/* ── Hero ────────────────────────────────────────────────────────── */}
            <div className="px-6 pb-4 pt-28 text-center">
                <p className="mb-3 text-xs font-semibold uppercase tracking-[0.15em]" style={{ color: JADE }}>
                    Planos e preços
                </p>
                <h1
                    className="mb-3 text-5xl leading-tight"
                    style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(232,230,225,0.95)' }}
                >
                    Um preço claro,<br />
                    <span className="italic" style={{ color: 'rgba(232,230,225,0.45)' }}>
                        sem taxas escondidas.
                    </span>
                </h1>
                <p className="mx-auto mt-4 max-w-md text-[16px]" style={{ color: 'rgba(232,230,225,0.45)' }}>
                    Mensalidade fixa, sem cobrança por agendamento. Escolha entre pagamento mensal ou anual.
                </p>

                {/* Toggle mensal/anual */}
                <div
                    className="mt-8 inline-flex items-center gap-1 rounded-full p-1"
                    style={{ background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.1)' }}
                >
                    <button
                        onClick={() => setAnual(false)}
                        className="rounded-full px-5 py-2 text-[13px] font-medium transition-all"
                        style={{
                            background: !anual ? 'rgba(255,255,255,0.12)' : 'transparent',
                            color: !anual ? 'white' : 'rgba(255,255,255,0.45)',
                        }}
                    >
                        Mensal
                    </button>
                    <button
                        onClick={() => setAnual(true)}
                        className="flex items-center gap-2 rounded-full px-5 py-2 text-[13px] font-medium transition-all"
                        style={{
                            background: anual ? INDIGO : 'transparent',
                            color: anual ? 'white' : 'rgba(255,255,255,0.45)',
                        }}
                    >
                        Anual
                        <span
                            className="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                            style={{ background: `${JADE}25`, color: JADE }}
                        >
                            2 meses grátis
                        </span>
                    </button>
                </div>
            </div>

            {/* ── Plans grid ──────────────────────────────────────────────────── */}
            <div className="px-6 py-12">
                <div className="mx-auto grid max-w-4xl gap-4 md:grid-cols-3">
                    {planoList.map(([slug, plano]) => (
                        <div
                            key={slug}
                            className="relative flex flex-col rounded-2xl p-6"
                            style={{
                                background: plano.destaque
                                    ? `linear-gradient(135deg, ${INDIGO}18 0%, ${INDIGO}08 100%)`
                                    : 'rgba(255,255,255,0.03)',
                                border: `1.5px solid ${plano.destaque ? INDIGO + '60' : 'rgba(255,255,255,0.08)'}`,
                            }}
                        >
                            {plano.destaque && (
                                <div
                                    className="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full px-3.5 py-1 text-[11px] font-semibold text-white"
                                    style={{ background: INDIGO, boxShadow: `0 4px 16px ${INDIGO}60` }}
                                >
                                    Mais popular
                                </div>
                            )}

                            {/* Plan header */}
                            <div className="mb-5">
                                <h2 className="text-[15px] font-semibold" style={{ color: 'rgba(232,230,225,0.9)' }}>
                                    {plano.nome}
                                </h2>
                                <p className="mt-0.5 text-xs" style={{ color: 'rgba(232,230,225,0.4)' }}>
                                    {plano.descricao}
                                </p>
                            </div>

                            {/* Base price */}
                            <div className="mb-1 flex items-baseline gap-1">
                                <span className="text-[13px] font-medium" style={{ color: 'rgba(232,230,225,0.5)' }}>R$</span>
                                <span
                                    className="text-4xl font-bold leading-none"
                                    style={{
                                        fontFamily: 'Instrument Serif, Georgia, serif',
                                        color: plano.destaque ? 'white' : 'rgba(232,230,225,0.9)',
                                    }}
                                >
                                    {anual ? valorAnual(plano.valor) : valorMensal(plano.valor)}
                                </span>
                                <span className="text-sm" style={{ color: 'rgba(232,230,225,0.35)' }}>
                                    /{anual ? 'ano' : 'mês'}
                                </span>
                            </div>

                            {anual && (
                                <p className="mb-1 text-[11px]" style={{ color: JADE }}>
                                    economia de R$ {economiaAnual(plano.valor)}/ano
                                </p>
                            )}

                            {/* Limite incluído no plano */}
                            <div
                                className="mb-4 mt-3 rounded-lg px-3 py-2 text-center"
                                style={{ background: 'rgba(255,255,255,0.05)', border: '1px solid rgba(255,255,255,0.09)' }}
                            >
                                <span className="text-[12px] font-semibold" style={{ color: JADE }}>
                                    {plano.limite_bot_mes === null
                                        ? 'Agendamentos via bot ilimitados'
                                        : `Até ${plano.limite_bot_mes} agendamentos via bot/mês`}
                                </span>
                                <span className="mt-0.5 block text-[11px]" style={{ color: 'rgba(232,230,225,0.4)' }}>
                                    sem cobrança por agendamento
                                </span>
                            </div>

                            <div className="mb-5 h-px" style={{ background: 'rgba(255,255,255,0.07)' }} />

                            {/* Features */}
                            <ul className="mb-7 flex-1 space-y-2.5">
                                {plano.features.map((f) => (
                                    <li key={f} className="flex items-start gap-2.5 text-[13px]">
                                        <CheckIcon />
                                        <span style={{ color: 'rgba(232,230,225,0.75)' }}>{f}</span>
                                    </li>
                                ))}
                                {plano.nao_inclui.map((f) => (
                                    <li key={f} className="flex items-start gap-2.5 text-[13px]">
                                        <span className="mt-0.5 w-[13px] shrink-0 text-center text-[10px]" style={{ color: 'rgba(232,230,225,0.2)' }}>—</span>
                                        <span style={{ color: 'rgba(232,230,225,0.25)' }}>{f}</span>
                                    </li>
                                ))}
                            </ul>

                            <Link
                                href={route('onboarding.step1')}
                                className="block w-full rounded-xl py-3 text-center text-[13px] font-semibold transition-all hover:brightness-110 hover:-translate-y-0.5"
                                style={
                                    plano.destaque
                                        ? { background: INDIGO, color: 'white', boxShadow: `0 8px 24px ${INDIGO}40` }
                                        : { background: 'rgba(255,255,255,0.07)', color: 'rgba(232,230,225,0.75)', border: '1px solid rgba(255,255,255,0.1)' }
                                }
                            >
                                Começar grátis
                            </Link>
                        </div>
                    ))}
                </div>

                <div
                    className="mx-auto mt-8 max-w-4xl rounded-2xl p-6 text-center"
                    style={{ background: 'rgba(255,255,255,0.02)', border: '1px solid rgba(255,255,255,0.07)' }}
                >
                    <p className="text-sm font-semibold" style={{ color: 'rgba(232,230,225,0.8)' }}>
                        O valor exibido é o valor da sua assinatura.
                    </p>
                    <p className="mt-1 text-xs" style={{ color: 'rgba(232,230,225,0.4)' }}>
                        Sem taxa por agendamento. No anual, você paga 10 mensalidades e recebe 12 meses de acesso.
                    </p>
                </div>

                {/* Trial notice */}
                <div className="mx-auto mt-6 max-w-4xl text-center">
                    <p className="text-xs" style={{ color: 'rgba(232,230,225,0.3)' }}>
                        Todos os planos incluem 14 dias de trial gratuito · Sem cartão de crédito · Cancele quando quiser
                    </p>
                </div>
            </div>

            {/* ── FAQ ─────────────────────────────────────────────────────────── */}
            <div className="px-6 py-16" style={{ borderTop: '1px solid rgba(255,255,255,0.05)' }}>
                <div className="mx-auto max-w-2xl">
                    <div className="mb-10 text-center">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-[0.15em]" style={{ color: INDIGO }}>FAQ</p>
                        <h2 className="text-4xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(232,230,225,0.9)' }}>
                            Perguntas frequentes
                        </h2>
                    </div>

                    <div className="space-y-2">
                        {faqs.map((faq, i) => (
                            <div
                                key={i}
                                className="overflow-hidden rounded-xl transition-colors"
                                style={{
                                    background: faqOpen === i ? 'rgba(255,255,255,0.04)' : 'rgba(255,255,255,0.02)',
                                    border: `1px solid ${faqOpen === i ? 'rgba(255,255,255,0.12)' : 'rgba(255,255,255,0.06)'}`,
                                }}
                            >
                                <button
                                    onClick={() => setFaqOpen(faqOpen === i ? null : i)}
                                    className="flex w-full items-center justify-between px-5 py-4 text-left text-[14px] font-medium transition-colors"
                                    style={{ color: 'rgba(232,230,225,0.85)' }}
                                >
                                    {faq.q}
                                    <svg
                                        width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
                                        className="ml-4 shrink-0 transition-transform duration-200"
                                        style={{ color: INDIGO, transform: faqOpen === i ? 'rotate(45deg)' : 'rotate(0)' }}
                                    >
                                        <line x1="12" y1="5" x2="12" y2="19" />
                                        <line x1="5" y1="12" x2="19" y2="12" />
                                    </svg>
                                </button>
                                {faqOpen === i && (
                                    <div className="px-5 pb-5 text-[13px] leading-relaxed" style={{ color: 'rgba(232,230,225,0.5)' }}>
                                        {faq.a}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    <div className="mt-14 text-center">
                        <p className="mb-4 text-sm" style={{ color: 'rgba(232,230,225,0.45)' }}>
                            Ainda tem dúvidas? Fale com a gente.
                        </p>
                        <Link
                            href={route('onboarding.step1')}
                            className="inline-flex items-center gap-2 rounded-xl px-6 py-3 text-sm font-semibold text-white transition-all hover:brightness-110"
                            style={{ background: INDIGO }}
                        >
                            Criar conta grátis
                            <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>

        </LandingLayout>
    );
}
