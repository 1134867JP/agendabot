import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import LandingLayout from '@/Layouts/LandingLayout';

const INDIGO = '#6366F1';
const DARK   = '#090E1A';

const chatMessages = [
    { from: 'client', text: 'Oi! Quero marcar um horário com o João pra amanhã' },
    { from: 'bot',    text: 'Olá! 😊 Aqui estão os horários disponíveis amanhã com o Barbeiro João:\n\n09:00 · 09:30 · 10:00 · 14:00 · 16:30' },
    { from: 'client', text: '10:00 ta bom' },
    { from: 'bot',    text: '✅ Agendamento confirmado!\n\nBarbeiro João\nAmanhã às 10:00\n\nAté lá!' },
];

function ChatMockup() {
    const [visible, setVisible] = useState(0);

    useEffect(() => {
        if (visible >= chatMessages.length) return;
        const t = setTimeout(() => setVisible(v => v + 1), 900 + visible * 400);
        return () => clearTimeout(t);
    }, [visible]);

    return (
        <div
            className="relative mx-auto w-64 rounded-3xl shadow-2xl overflow-hidden"
            style={{ background: '#ECE5DD', border: '8px solid #111827' }}
        >
            {/* Phone header */}
            <div className="flex items-center gap-2 px-4 py-3" style={{ background: '#075E54' }}>
                <div className="h-8 w-8 rounded-full bg-emerald-300 flex items-center justify-center text-xs font-bold text-emerald-900">B</div>
                <div>
                    <p className="text-xs font-semibold text-primary">Barbearia do João</p>
                    <p className="text-xs text-emerald-200">online</p>
                </div>
            </div>

            {/* Messages */}
            <div className="min-h-56 space-y-2 p-3">
                {chatMessages.slice(0, visible).map((m, i) => (
                    <div
                        key={i}
                        className={`max-w-[85%] rounded-lg px-3 py-1.5 text-xs leading-relaxed transition-all ${
                            m.from === 'client'
                                ? 'ml-auto rounded-tr-none bg-[#DCF8C6] text-gray-800'
                                : 'rounded-tl-none bg-white text-gray-800'
                        }`}
                        style={{ whiteSpace: 'pre-line', opacity: 1, transform: 'translateY(0)' }}
                    >
                        {m.text}
                    </div>
                ))}
                {visible < chatMessages.length && (
                    <div className="flex gap-1 p-2">
                        {[0,1,2].map(i => (
                            <span key={i} className="h-1.5 w-1.5 rounded-full bg-gray-400 animate-bounce" style={{ animationDelay: `${i * 0.15}s` }} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

const features = [
    {
        icon: '🧠',
        title: 'IA que entende linguagem natural',
        desc: 'Sem menus rígidos. O cliente escreve como quiser e o bot entende o pedido.',
    },
    {
        icon: '✏️',
        title: 'Reserva manual pelo painel',
        desc: 'Ligou direto? Cadastre você mesmo em segundos pela agenda visual.',
    },
    {
        icon: '📅',
        title: 'Agenda visual completa',
        desc: 'Veja todos os horários do dia em uma grade clara, com filtros por recurso.',
    },
];

const segments = [
    { emoji: '✂️', title: 'Barbearias', desc: 'Gerencie seus barbeiros e horários sem planilhas.' },
    { emoji: '🏟️', title: 'Quadras Esportivas', desc: 'Reservas de futsal, beach tennis, padel e mais.' },
    { emoji: '💆', title: 'Estéticas', desc: 'Manicures, depilação, massagens — tudo no WhatsApp.' },
    { emoji: '⚙️', title: 'Qualquer Serviço', desc: 'Consultórios, estúdios, estacionamentos, o que precisar.' },
];

const steps = [
    { n: 1, icon: '📱', title: 'Conecte seu WhatsApp', desc: 'Escaneie o QR Code na plataforma. Pronto, a instância está ativa.' },
    { n: 2, icon: '🛠️', title: 'Cadastre seus serviços', desc: 'Adicione recursos (barbeiros, quadras…) com horários de funcionamento.' },
    { n: 3, icon: '💬', title: 'Clientes agendam pelo chat', desc: 'O bot conversa, entende e confirma automaticamente.' },
];

export default function Home() {
    return (
        <LandingLayout currentPage="home">
            <Head title="AgendaBot — Agendamento automático via WhatsApp" />

            {/* ── Hero ── */}
            <section className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden pt-20 px-6 pb-16">
                {/* Glow effects */}
                <div className="pointer-events-none absolute left-1/2 top-1/3 h-96 w-96 -translate-x-1/2 -translate-y-1/2 rounded-full opacity-20 blur-3xl" style={{ background: INDIGO }} />
                <div className="pointer-events-none absolute right-1/4 bottom-1/3 h-64 w-64 rounded-full opacity-10 blur-3xl" style={{ background: '#C2692A' }} />

                <div className="relative z-10 mx-auto max-w-6xl grid gap-12 lg:grid-cols-2 lg:items-center">
                    <div>
                        <div
                            className="mb-6 inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs"
                            style={{ borderColor: 'rgba(99,102,241,0.4)', color: INDIGO }}
                        >
                            ✦ Bot com IA · Zero configuração complexa
                        </div>
                        <h1
                            className="text-5xl font-bold leading-tight lg:text-6xl"
                            style={{ fontFamily: 'Syne, sans-serif' }}
                        >
                            Seu WhatsApp{' '}
                            <span style={{ color: INDIGO }}>agenda</span>
                            {' '}por você.
                        </h1>
                        <p className="mt-5 text-lg leading-relaxed" style={{ color: 'rgba(255,255,255,0.6)' }}>
                            Clientes mandam mensagem, o bot entende e confirma o horário.
                            Funciona para barbearias, quadras, estéticas e mais.
                        </p>

                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={route('onboarding.step1')}
                                className="rounded-xl px-6 py-3 font-semibold text-primary transition-all hover:brightness-110 hover:scale-105"
                                style={{ background: INDIGO }}
                            >
                                Começar 14 dias grátis
                            </Link>
                            <a
                                href="#como-funciona"
                                className="rounded-xl border px-6 py-3 font-semibold transition-all hover:bg-white/5"
                                style={{ borderColor: 'rgba(255,255,255,0.2)', color: 'rgba(255,255,255,0.8)' }}
                            >
                                Ver como funciona →
                            </a>
                        </div>

                        <p className="mt-4 text-xs" style={{ color: 'rgba(255,255,255,0.35)' }}>
                            Sem cartão de crédito para começar.
                        </p>
                    </div>

                    {/* WhatsApp mockup */}
                    <div className="flex justify-center lg:justify-end">
                        <div className="relative" style={{ transform: 'rotate(2deg)' }}>
                            <div className="absolute -inset-4 rounded-3xl opacity-30 blur-2xl" style={{ background: INDIGO }} />
                            <ChatMockup />
                        </div>
                    </div>
                </div>
            </section>

            {/* ── Como funciona ── */}
            <section id="como-funciona" className="px-6 py-24" style={{ background: 'var(--bg-surface-2)' }}>
                <div className="mx-auto max-w-4xl">
                    <h2
                        className="mb-2 text-center text-3xl font-bold"
                        style={{ fontFamily: 'Syne, sans-serif' }}
                    >
                        Simples assim
                    </h2>
                    <p className="mb-12 text-center text-sm" style={{ color: 'rgba(255,255,255,0.45)' }}>
                        Três passos para começar a receber agendamentos automáticos.
                    </p>

                    <div className="relative grid gap-8 md:grid-cols-3">
                        {/* Connecting line */}
                        <div
                            className="absolute top-10 left-1/6 right-1/6 hidden h-px md:block"
                            style={{ background: 'linear-gradient(to right, transparent, rgba(99,102,241,0.4), transparent)' }}
                        />

                        {steps.map((s) => (
                            <div key={s.n} className="relative flex flex-col items-center text-center">
                                <div
                                    className="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl text-2xl"
                                    style={{ background: 'rgba(99,102,241,0.12)', border: `1px solid rgba(99,102,241,0.3)` }}
                                >
                                    {s.icon}
                                </div>
                                <p
                                    className="mb-2 text-xs font-bold uppercase tracking-widest"
                                    style={{ color: INDIGO }}
                                >
                                    Passo {s.n}
                                </p>
                                <h3 className="mb-2 font-semibold">{s.title}</h3>
                                <p className="text-sm leading-relaxed" style={{ color: 'rgba(255,255,255,0.5)' }}>{s.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── Para quem ── */}
            <section id="para-quem" className="px-6 py-24">
                <div className="mx-auto max-w-4xl">
                    <h2
                        className="mb-12 text-center text-3xl font-bold"
                        style={{ fontFamily: 'Syne, sans-serif' }}
                    >
                        Para quem é
                    </h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {segments.map(s => (
                            <div
                                key={s.title}
                                className="flex items-start gap-4 rounded-2xl p-5 transition-all"
                                style={{ background: 'var(--bg-surface-2)', border: '1px solid rgba(255,255,255,0.08)' }}
                                onMouseEnter={e => { (e.currentTarget as HTMLElement).style.background = 'rgba(99,102,241,0.07)'; (e.currentTarget as HTMLElement).style.borderColor = 'rgba(99,102,241,0.25)'; }}
                                onMouseLeave={e => { (e.currentTarget as HTMLElement).style.background = 'var(--bg-surface-2)'; (e.currentTarget as HTMLElement).style.borderColor = 'rgba(255,255,255,0.08)'; }}
                            >
                                <span className="text-3xl">{s.emoji}</span>
                                <div>
                                    <h3 className="font-semibold">{s.title}</h3>
                                    <p className="mt-1 text-sm" style={{ color: 'rgba(255,255,255,0.5)' }}>{s.desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── Features ── */}
            <section id="funcionalidades" className="px-6 py-24" style={{ background: 'var(--bg-surface-2)' }}>
                <div className="mx-auto max-w-4xl">
                    <h2
                        className="mb-12 text-center text-3xl font-bold"
                        style={{ fontFamily: 'Syne, sans-serif' }}
                    >
                        Funcionalidades
                    </h2>
                    <div className="grid gap-6 md:grid-cols-3">
                        {features.map(f => (
                            <div
                                key={f.title}
                                className="rounded-2xl p-6"
                                style={{ background: 'var(--bg-surface-2)', border: '1px solid rgba(255,255,255,0.08)' }}
                            >
                                <div
                                    className="mb-4 flex h-12 w-12 items-center justify-center rounded-xl text-2xl"
                                    style={{ background: 'rgba(99,102,241,0.1)' }}
                                >
                                    {f.icon}
                                </div>
                                <h3 className="mb-2 font-semibold">{f.title}</h3>
                                <p className="text-sm leading-relaxed" style={{ color: 'rgba(255,255,255,0.5)' }}>{f.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ── CTA final ── */}
            <section className="px-6 py-24">
                <div
                    className="mx-auto max-w-2xl rounded-3xl p-10 text-center"
                    style={{ background: 'linear-gradient(135deg, rgba(99,102,241,0.15) 0%, rgba(9,14,26,0) 100%)', border: '1px solid rgba(99,102,241,0.25)' }}
                >
                    <h2
                        className="mb-3 text-3xl font-bold"
                        style={{ fontFamily: 'Syne, sans-serif' }}
                    >
                        Pronto para automatizar?
                    </h2>
                    <p className="mb-8 text-sm" style={{ color: 'rgba(255,255,255,0.55)' }}>
                        Comece grátis por 14 dias. Sem cartão. Cancele quando quiser.
                    </p>
                    <Link
                        href={route('onboarding.step1')}
                        className="inline-flex rounded-xl px-8 py-3 font-semibold text-primary transition-all hover:brightness-110"
                        style={{ background: INDIGO }}
                    >
                        Criar conta grátis
                    </Link>
                </div>
            </section>

        </LandingLayout>
    );
}
