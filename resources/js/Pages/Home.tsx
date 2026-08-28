import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import LandingLayout from '@/Layouts/LandingLayout';

// ── Paleta ────────────────────────────────────────────────────────────────────
const INDIGO = '#14b8a6';
const JADE   = '#5eead4';
const DARK   = '#0d1012';

// ── Planos ────────────────────────────────────────────────────────────────────
const PLANOS = [
    {
        slug: 'basico', nome: 'Básico', valor: 79.90, destaque: false,
        descricao: 'Até 3 recursos, 300 conversas/mês',
        features: ['Bot WhatsApp com IA', 'Até 3 recursos', '300 conversas por mês', 'Agenda visual', 'Reserva manual'],
    },
    {
        slug: 'profissional', nome: 'Profissional', valor: 129.90, destaque: true,
        descricao: 'Até 10 recursos, 1.500 conversas/mês',
        features: ['Bot WhatsApp com IA', 'Até 10 recursos', '1.500 conversas por mês', 'Relatórios avançados', 'Suporte prioritário'],
    },
    {
        slug: 'ilimitado', nome: 'Ilimitado', valor: 199.90, destaque: false,
        descricao: 'Recursos ilimitados, conversas ilimitadas',
        features: ['Bot WhatsApp com IA', 'Recursos ilimitados', 'Conversas ilimitadas', 'Histórico completo', 'Suporte via chat'],
    },
];

// ── Chat mockup ───────────────────────────────────────────────────────────────
const chatMessages = [
    { from: 'client', text: 'Oi! Quero marcar um horário com o João pra amanhã' },
    { from: 'bot',    text: 'Olá! Aqui estão os horários disponíveis amanhã:\n\n09:00 · 09:30 · 10:00 · 14:00 · 16:30' },
    { from: 'client', text: '10h ta bom' },
    { from: 'bot',    text: 'Horário confirmado.\nBarbeiro João — amanhã às 10:00\n\nAté lá!' },
];

function ChatMockup() {
    const [visible, setVisible] = useState(0);

    useEffect(() => {
        if (visible >= chatMessages.length) return;
        const t = setTimeout(() => setVisible(v => v + 1), 900 + visible * 500);
        return () => clearTimeout(t);
    }, [visible]);

    return (
        <div
            className="relative mx-auto w-64 overflow-hidden rounded-[28px] shadow-2xl"
            style={{ background: '#ECE5DD', border: '7px solid #1a1a2e' }}
        >
            {/* Status bar */}
            <div className="flex justify-between px-4 pt-2 text-[9px] font-medium text-white" style={{ background: '#075E54' }}>
                <span>9:41</span>
                <span>▮▮▮</span>
            </div>
            {/* Chat header */}
            <div className="flex items-center gap-2.5 px-3 pb-2.5 pt-1" style={{ background: '#075E54' }}>
                <div className="h-8 w-8 rounded-full bg-emerald-400 flex items-center justify-center text-[11px] font-bold text-emerald-900 shrink-0">B</div>
                <div>
                    <p className="text-[12px] font-semibold leading-tight text-white">Barbearia do João</p>
                    <div className="flex items-center gap-1">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                        <p className="text-[10px] text-emerald-200">online</p>
                    </div>
                </div>
            </div>

            {/* Messages */}
            <div className="min-h-60 space-y-2 px-2.5 py-3">
                {chatMessages.slice(0, visible).map((m, i) => (
                    <div
                        key={i}
                        className={`max-w-[88%] rounded-lg px-3 py-2 text-[11px] leading-relaxed shadow-sm ${
                            m.from === 'client'
                                ? 'ml-auto rounded-tr-none bg-[#DCF8C6] text-gray-800'
                                : 'rounded-tl-none bg-white text-gray-800'
                        }`}
                        style={{ whiteSpace: 'pre-line', animation: 'fadeUp 0.25s ease-out' }}
                    >
                        {m.text}
                        <span className="ml-1 text-[8px] text-gray-400">{i % 2 === 0 ? '10:32' : '10:33'}</span>
                    </div>
                ))}
                {visible > 0 && visible < chatMessages.length && (
                    <div className="flex gap-1 px-2 py-1">
                        {[0,1,2].map(i => (
                            <span
                                key={i}
                                className="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce"
                                style={{ animationDelay: `${i * 0.15}s` }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Input bar */}
            <div className="flex items-center gap-2 px-3 py-2" style={{ background: '#f0f2f5' }}>
                <div className="flex-1 rounded-full bg-white px-3 py-1.5 text-[10px] text-gray-400">Mensagem</div>
                <div className="h-7 w-7 rounded-full flex items-center justify-center" style={{ background: '#075E54' }}>
                    <svg width={12} height={12} viewBox="0 0 24 24" fill="white" stroke="none">
                        <path d="M22 2L11 13M22 2L15 22l-4-9-9-4 20-7z" stroke="white" fill="none" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                    </svg>
                </div>
            </div>
        </div>
    );
}

// ── Floating notification cards ────────────────────────────────────────────────
function FloatCard({ text, sub, style, className = '' }: { text: string; sub: string; style: React.CSSProperties; className?: string }) {
    return (
        <div
            className={`absolute flex items-center gap-2.5 rounded-2xl px-3.5 py-2.5 shadow-xl ${className}`}
            style={{
                background: 'rgba(17,19,32,0.92)',
                border: '1px solid rgba(255,255,255,0.1)',
                backdropFilter: 'blur(12px)',
                animation: 'floatCard 0.4s ease-out forwards',
                opacity: 0,
                ...style,
            }}
        >
            <div>
                <p className="text-[11px] font-semibold text-white leading-tight">{text}</p>
                <p className="text-[10px] leading-tight" style={{ color: 'rgba(255,255,255,0.45)' }}>{sub}</p>
            </div>
        </div>
    );
}

// ── Feature icons (SVG) ───────────────────────────────────────────────────────
function FeatureIcon({ path }: { path: string }) {
    return (
        <svg width={22} height={22} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
            <path d={path} />
        </svg>
    );
}

const features = [
    {
        icon: 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
        title: 'Conversa natural no WhatsApp',
        desc: 'O cliente conversa normalmente e recebe horários disponíveis, confirmação e lembretes no mesmo canal.',
        color: INDIGO,
    },
    {
        icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        title: 'Agenda visual completa',
        desc: 'Veja todos os horários do dia em uma grade clara. Crie reservas manuais em segundos quando o cliente ligar.',
        color: JADE,
    },
    {
        icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        title: 'Atendimento humano quando precisar',
        desc: 'Assuma a conversa com um clique. O bot reconhece quando passar para você e avisa na hora.',
        color: '#f59e0b',
    },
];

const segments = [
    { icon: '', title: 'Barbearias e salões', desc: 'Gerencie seus barbeiros, coloristas e horários — tudo sem planilha.' },
    { icon: '', title: 'Quadras esportivas', desc: 'Futsal, beach tennis, padel — reservas automáticas 24h por dia.' },
    { icon: '', title: 'Clínicas e consultórios', desc: 'Dentistas, psicólogos, fisioterapeutas: confirmação automática de consultas.' },
    { icon: '', title: 'Estéticas e spas', desc: 'Manicures, depilação, massagens — agendadas pelo WhatsApp sem te interromper.' },
];

const steps = [
    {
        n: '01',
        title: 'Conecte seu WhatsApp',
        desc: 'Escaneie o QR Code no painel. Em menos de 2 minutos sua instância está ativa.',
        color: JADE,
    },
    {
        n: '02',
        title: 'Cadastre seus profissionais',
        desc: 'Adicione quem atende, os serviços e os horários disponíveis. Simples assim.',
        color: INDIGO,
    },
    {
        n: '03',
        title: 'Clientes agendam sozinhos',
        desc: 'O bot conversa, entende e confirma automaticamente. Você só aparece no final.',
        color: '#f59e0b',
    },
];

// ── Main component ─────────────────────────────────────────────────────────────
export default function Home() {
    const [cardsVisible, setCardsVisible] = useState(false);

    useEffect(() => {
        const t = setTimeout(() => setCardsVisible(true), 1800);
        return () => clearTimeout(t);
    }, []);

    return (
        <LandingLayout currentPage="home">
            <Head title="Agendou — Agendamento automático via WhatsApp" />

            <style>{`
                @keyframes fadeUp {
                    from { opacity: 0; transform: translateY(6px); }
                    to   { opacity: 1; transform: translateY(0); }
                }
                @keyframes floatCard {
                    from { opacity: 0; transform: translateY(8px) scale(0.97); }
                    to   { opacity: 1; transform: translateY(0) scale(1); }
                }
                @keyframes float {
                    0%, 100% { transform: translateY(0px); }
                    50%       { transform: translateY(-6px); }
                }
            `}</style>

            {/* ── Hero ──────────────────────────────────────────────────────── */}
            <section className="relative flex flex-col items-center justify-center overflow-hidden px-5 pb-14 pt-24 sm:min-h-screen sm:px-6 sm:pb-16">
                {/* Background glows */}
                <div className="pointer-events-none absolute left-1/3 top-1/4 h-[500px] w-[500px] -translate-x-1/2 -translate-y-1/2 rounded-full opacity-[0.07] blur-[80px]" style={{ background: INDIGO }} />
                <div className="pointer-events-none absolute right-1/4 bottom-1/3 h-80 w-80 rounded-full opacity-[0.05] blur-[60px]" style={{ background: JADE }} />

                <div className="relative z-10 mx-auto grid max-w-6xl gap-8 sm:gap-12 lg:grid-cols-2 lg:items-center">
                    {/* Copy */}
                    <div>
                        {/* Eyebrow */}
                        <div
                            className="mb-6 inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-medium"
                            style={{ borderColor: `${JADE}40`, color: JADE }}
                        >
                            <span className="h-1.5 w-1.5 rounded-full animate-pulse" style={{ background: JADE }} />
                            Agendamento automático via WhatsApp
                        </div>

                        {/* Headline — risco: duas fontes na mesma frase */}
                        <h1 className="text-[42px] leading-[1.08] tracking-tight sm:text-5xl lg:text-[62px]">
                            <span
                                className="block font-bold"
                                style={{ fontFamily: "'DM Sans', sans-serif", color: 'rgba(232,230,225,0.95)' }}
                            >
                                Seu WhatsApp
                            </span>
                            <span
                                className="block italic"
                                style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(232,230,225,0.95)' }}
                            >
                                agenda enquanto
                            </span>
                            <span
                                className="block font-bold"
                                style={{ fontFamily: "'DM Sans', sans-serif", color: JADE }}
                            >
                                você trabalha.
                            </span>
                        </h1>

                        <p className="mt-6 max-w-sm text-[17px] leading-relaxed" style={{ color: 'rgba(232,230,225,0.5)' }}>
                            Clientes mandam mensagem, encontram um horário e recebem a confirmação — sem planilha, sem "anota aí".
                        </p>

                        <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                            <Link
                                href={route('onboarding.step1')}
                                className="inline-flex w-full items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-semibold text-white shadow-lg transition-all hover:brightness-110 hover:shadow-indigo-500/20 hover:-translate-y-0.5 sm:w-auto"
                                style={{ background: INDIGO }}
                            >
                                Começar 14 dias grátis
                                <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </Link>
                            <a
                                href="#como-funciona"
                                className="w-full rounded-xl px-6 py-3.5 text-center text-sm font-medium transition-all hover:bg-white/5 sm:w-auto"
                                style={{ color: 'rgba(232,230,225,0.65)', border: '1px solid rgba(255,255,255,0.12)' }}
                            >
                                Ver como funciona
                            </a>
                        </div>

                        {/* Trust line */}
                        <div className="mt-5 flex flex-col items-start gap-2 text-[12px] sm:flex-row sm:flex-wrap sm:items-center sm:gap-4" style={{ color: 'rgba(232,230,225,0.3)' }}>
                            {['Sem cartão para começar', 'Setup em 5 minutos', 'Cancele quando quiser'].map((t, i) => (
                                <span key={i} className="flex items-center gap-1.5">
                                    <svg width={10} height={10} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: JADE }}>
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    {t}
                                </span>
                            ))}
                        </div>
                    </div>

                    {/* Mockup + floating cards */}
                    <div className="flex justify-center lg:justify-end">
                        <div className="relative px-3 py-8 sm:px-10 sm:py-12 sm:pr-[60px]">
                            {/* Phone glow */}
                            <div
                                className="absolute inset-8 rounded-[40px] blur-3xl opacity-20"
                                style={{ background: `radial-gradient(circle, ${INDIGO}, transparent)` }}
                            />

                            {/* Phone with subtle float */}
                            <div style={{ animation: 'float 6s ease-in-out infinite' }}>
                                <ChatMockup />
                            </div>

                            {/* Floating notification cards */}
                            {cardsVisible && (
                                <>
                                    <FloatCard
                                                                                text="Novo agendamento"
                                        sub="Kauê — amanhã às 10:00"
                                        style={{ top: '8px', right: '0', animationDelay: '0s' }}
                                        className="hidden sm:flex"
                                    />
                                    <FloatCard
                                                                                text="14 reservas confirmadas"
                                        sub="hoje via WhatsApp"
                                        style={{ bottom: '40px', left: '0', animationDelay: '0.2s' }}
                                        className="hidden sm:flex"
                                    />
                                    <FloatCard
                                                                                text="Cliente respondeu"
                                        sub="às 15h fica ótimo"
                                        style={{ top: '50%', right: '0', animationDelay: '0.4s' }}
                                        className="hidden sm:flex"
                                    />
                                </>
                            )}
                        </div>
                    </div>
                </div>

                {/* Scroll hint */}
                <div className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-1.5 opacity-30">
                    <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="animate-bounce">
                        <path d="M12 5v14M5 12l7 7 7-7"/>
                    </svg>
                </div>
            </section>

            {/* ── Stats bar ─────────────────────────────────────────────────── */}
            <div style={{ background: 'rgba(99,102,241,0.06)', borderTop: '1px solid rgba(99,102,241,0.12)', borderBottom: '1px solid rgba(99,102,241,0.12)' }}>
                <div className="mx-auto flex max-w-4xl flex-wrap items-center justify-center gap-10 px-6 py-7">
                    {[
                        { n: '14 dias', label: 'trial sem cartão' },
                        { n: '5 min', label: 'para configurar' },
                        { n: '24 h', label: 'atendimento automático' },
                        { n: '0', label: 'planilhas necessárias' },
                    ].map(s => (
                        <div key={s.n} className="flex flex-col items-center gap-0.5">
                            <span
                                className="text-2xl font-bold"
                                style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(232,230,225,0.9)' }}
                            >
                                {s.n}
                            </span>
                            <span className="text-xs" style={{ color: 'rgba(232,230,225,0.4)' }}>{s.label}</span>
                        </div>
                    ))}
                </div>
            </div>

            {/* ── Como funciona ─────────────────────────────────────────────── */}
            <section id="como-funciona" className="px-6 py-28">
                <div className="mx-auto max-w-4xl">
                    <div className="mb-16 text-center">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-[0.15em]" style={{ color: INDIGO }}>Como funciona</p>
                        <h2
                            className="text-4xl leading-tight"
                            style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(232,230,225,0.95)' }}
                        >
                            Pronto para receber agendamentos<br />
                            <span style={{ color: 'rgba(232,230,225,0.5)' }}>em menos de uma tarde.</span>
                        </h2>
                    </div>

                    <div className="relative grid gap-0 md:grid-cols-3">
                        {steps.map((s, i) => (
                            <div key={s.n} className="relative flex flex-col items-center text-center px-6">
                                {/* Connector line */}
                                {i < steps.length - 1 && (
                                    <div
                                        className="absolute top-7 left-[calc(50%+32px)] right-0 hidden h-px md:block"
                                        style={{ background: `linear-gradient(to right, ${s.color}50, transparent)` }}
                                    />
                                )}

                                {/* Number circle */}
                                <div
                                    className="relative z-10 mb-6 flex h-14 w-14 items-center justify-center rounded-2xl text-sm font-bold"
                                    style={{
                                        background: `${s.color}15`,
                                        border: `1.5px solid ${s.color}40`,
                                        color: s.color,
                                        fontFamily: 'Instrument Serif, Georgia, serif',
                                        fontSize: '20px',
                                    }}
                                >
                                    {s.n}
                                </div>

                                <h3 className="mb-2.5 text-[15px] font-semibold" style={{ color: 'rgba(232,230,225,0.9)' }}>
                                    {s.title}
                                </h3>
                                <p className="text-sm leading-relaxed" style={{ color: 'rgba(232,230,225,0.45)' }}>
                                    {s.desc}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── Para quem ─────────────────────────────────────────────────── */}
            <section id="para-quem" className="px-6 py-20" style={{ borderTop: '1px solid rgba(255,255,255,0.05)' }}>
                <div className="mx-auto max-w-4xl">
                    <div className="mb-14 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="mb-2 text-xs font-semibold uppercase tracking-[0.15em]" style={{ color: JADE }}>Para quem é</p>
                            <h2
                                className="text-4xl"
                                style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(232,230,225,0.9)' }}
                            >
                                Qualquer negócio que agenda.
                            </h2>
                        </div>
                        <p className="max-w-xs text-sm leading-relaxed" style={{ color: 'rgba(232,230,225,0.35)' }}>
                            Se o seu cliente precisa de horário, o Agendou resolve.
                        </p>
                    </div>

                    {/* Tabela editorial — linha por segmento */}
                    <div style={{ border: '1px solid rgba(255,255,255,0.07)', borderRadius: '16px', overflow: 'hidden' }}>
                        {segments.map((s, i) => (
                            <div
                                key={s.title}
                                className="group flex flex-col items-start gap-2 px-5 py-5 transition-colors duration-150 sm:flex-row sm:items-center sm:gap-6 sm:px-6"
                                style={{
                                    borderTop: i > 0 ? '1px solid rgba(255,255,255,0.06)' : 'none',
                                    background: 'rgba(255,255,255,0.02)',
                                    cursor: 'default',
                                }}
                                onMouseEnter={e => { (e.currentTarget as HTMLElement).style.background = `${JADE}07`; }}
                                onMouseLeave={e => { (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.02)'; }}
                            >
                                {/* Index marker */}
                                <span
                                    className="hidden sm:block shrink-0 text-[11px] font-semibold tabular-nums"
                                    style={{ color: 'rgba(232,230,225,0.15)', fontFamily: 'Instrument Serif, Georgia, serif', fontSize: '13px', width: '20px' }}
                                >
                                    {String(i + 1).padStart(2, '0')}
                                </span>

                                {/* Title */}
                                <h3
                                    className="w-auto shrink-0 text-[15px] font-semibold leading-tight sm:w-40"
                                    style={{ color: 'rgba(232,230,225,0.88)' }}
                                >
                                    {s.title}
                                </h3>

                                {/* Divider */}
                                <div className="hidden h-px flex-1 sm:block" style={{ background: 'rgba(255,255,255,0.06)' }} />

                                {/* Desc */}
                                <p className="flex-1 text-left text-sm leading-relaxed sm:text-right" style={{ color: 'rgba(232,230,225,0.42)' }}>
                                    {s.desc}
                                </p>
                            </div>
                        ))}
                    </div>

                    {/* Marquee de tipos de negócio */}
                    <div className="mt-10 overflow-hidden" style={{ maskImage: 'linear-gradient(to right, transparent 0%, black 8%, black 92%, transparent 100%)', WebkitMaskImage: 'linear-gradient(to right, transparent 0%, black 8%, black 92%, transparent 100%)' }}>
                        <div className="marquee-track flex w-max gap-2.5">
                            {[
                                'Barbearias', 'Salões', 'Clínicas odontológicas', 'Quadras de futsal',
                                'Beach tennis', 'Padel', 'Psicólogos', 'Fisioterapeutas',
                                'Manicures', 'Depilação', 'Massagem', 'Pilates', 'Personal trainer',
                                'Tatuagem', 'Nutricionistas', 'Esteticistas',
                                'Barbearias', 'Salões', 'Clínicas odontológicas', 'Quadras de futsal',
                                'Beach tennis', 'Padel', 'Psicólogos', 'Fisioterapeutas',
                                'Manicures', 'Depilação', 'Massagem', 'Pilates', 'Personal trainer',
                                'Tatuagem', 'Nutricionistas', 'Esteticistas',
                            ].map((item, i) => (
                                <span
                                    key={i}
                                    className="flex-none whitespace-nowrap rounded-full px-3.5 py-1.5 text-[12px] font-medium"
                                    style={{
                                        color: 'rgba(232,230,225,0.45)',
                                        border: '1px solid rgba(255,255,255,0.07)',
                                        background: 'rgba(255,255,255,0.025)',
                                    }}
                                >
                                    {item}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* ── Features ──────────────────────────────────────────────────── */}
            <section id="funcionalidades" className="px-6 py-24">
                <div className="mx-auto max-w-4xl">
                    <div className="mb-14 text-center">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-[0.15em]" style={{ color: INDIGO }}>Funcionalidades</p>
                        <h2
                            className="text-4xl"
                            style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(232,230,225,0.9)' }}
                        >
                            Tudo o que você precisa,<br />
                            <span className="italic" style={{ color: 'rgba(232,230,225,0.45)' }}>nada do que não precisa.</span>
                        </h2>
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                        {features.map(f => (
                            <div
                                key={f.title}
                                className="rounded-2xl p-6 transition-all duration-200"
                                style={{
                                    background: 'rgba(255,255,255,0.03)',
                                    border: '1px solid rgba(255,255,255,0.07)',
                                    borderTop: `2px solid ${f.color}`,
                                }}
                                onMouseEnter={e => {
                                    (e.currentTarget as HTMLElement).style.background = `${f.color}06`;
                                    (e.currentTarget as HTMLElement).style.boxShadow = `0 8px 32px -8px ${f.color}20`;
                                }}
                                onMouseLeave={e => {
                                    (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.03)';
                                    (e.currentTarget as HTMLElement).style.boxShadow = 'none';
                                }}
                            >
                                <div className="mb-5" style={{ color: f.color }}>
                                    <FeatureIcon path={f.icon} />
                                </div>
                                <h3 className="mb-2 text-[15px] font-semibold leading-snug" style={{ color: 'rgba(232,230,225,0.9)' }}>
                                    {f.title}
                                </h3>
                                <p className="text-sm leading-relaxed" style={{ color: 'rgba(232,230,225,0.45)' }}>
                                    {f.desc}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── CTA final ─────────────────────────────────────────────────── */}
            <section className="relative px-6 py-28 overflow-hidden" style={{ borderTop: '1px solid rgba(255,255,255,0.05)' }}>
                {/* Mesh gradient background */}
                <div className="pointer-events-none absolute inset-0" style={{ background: `radial-gradient(ellipse 60% 70% at 50% 50%, ${INDIGO}0d 0%, transparent 70%)` }} />
                <div className="pointer-events-none absolute left-1/4 top-0 h-64 w-64 -translate-y-1/2 rounded-full blur-3xl" style={{ background: `${JADE}08` }} />
                <div className="pointer-events-none absolute right-1/4 bottom-0 h-48 w-48 translate-y-1/2 rounded-full blur-3xl" style={{ background: `${INDIGO}0a` }} />
                <div className="relative mx-auto max-w-2xl text-center">
                    <p className="mb-4 text-xs font-semibold uppercase tracking-[0.15em]" style={{ color: JADE }}>Comece hoje</p>
                    <h2
                        className="mb-4 text-4xl leading-tight sm:text-5xl"
                        style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(232,230,225,0.95)' }}
                    >
                        Chega de anotar horário<br />
                        <span className="italic" style={{ color: 'rgba(232,230,225,0.5)' }}>no bloco de notas.</span>
                    </h2>
                    <p className="mb-10 text-base" style={{ color: 'rgba(232,230,225,0.45)' }}>
                        14 dias gratuitos. Sem cartão. Cancele quando quiser.
                    </p>

                    <Link
                        href={route('onboarding.step1')}
                        className="inline-flex items-center gap-2.5 rounded-2xl px-8 py-4 text-base font-semibold text-white transition-all hover:brightness-110 hover:-translate-y-0.5"
                        style={{ background: INDIGO, boxShadow: `0 18px 36px -12px ${INDIGO}55` }}
                    >
                        Criar minha conta grátis
                        <svg width={16} height={16} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </Link>

                    <div className="mt-6 flex flex-wrap items-center justify-center gap-5 text-[12px]" style={{ color: 'rgba(232,230,225,0.3)' }}>
                        {['Sem cartão de crédito', 'Setup em 5 minutos', '14 dias grátis'].map((t, i) => (
                            <span key={i} className="flex items-center gap-1.5">
                                <svg width={10} height={10} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" style={{ color: JADE }}>
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                {t}
                            </span>
                        ))}
                    </div>
                </div>
            </section>

        </LandingLayout>
    );
}
