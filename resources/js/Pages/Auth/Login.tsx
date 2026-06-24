import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const INDIGO = '#6366F1';
const JADE   = '#00a884';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email:    '',
        password: '',
        remember: false as boolean,
    });

    const [showPass, setShowPass] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <div className="flex min-h-screen" style={{ background: 'var(--bg-app)' }}>
            <Head title="Entrar" />

            {/* ── Painel esquerdo — marca ─────────────────────────────────────── */}
            <div
                className="relative hidden flex-1 flex-col justify-between overflow-hidden p-10 lg:flex"
                style={{ background: 'var(--bg-sidebar)', borderRight: '1px solid var(--border)' }}
            >
                {/* Glows decorativos */}
                <div
                    className="pointer-events-none absolute -left-20 -top-20 h-64 w-64 rounded-full opacity-20 blur-3xl"
                    style={{ background: INDIGO }}
                />
                <div
                    className="pointer-events-none absolute bottom-10 right-0 h-48 w-48 rounded-full opacity-10 blur-3xl"
                    style={{ background: JADE }}
                />

                {/* Logo topo */}
                <Link href={route('home')} className="relative flex items-center gap-2.5">
                    <span
                        className="flex h-8 w-8 items-center justify-center rounded-xl text-sm font-bold text-white"
                        style={{ background: JADE }}
                    >
                        A
                    </span>
                    <span
                        className="text-xl"
                        style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}
                    >
                        AgendaBot
                    </span>
                </Link>

                {/* Conteúdo central */}
                <div className="relative">
                    {/* Card de notificação decorativo */}
                    <div
                        className="mb-8 inline-flex items-center gap-3 rounded-2xl px-4 py-3"
                        style={{
                            background: 'rgba(255,255,255,0.04)',
                            border:     '1px solid rgba(255,255,255,0.08)',
                        }}
                    >
                        <span className="text-xl">✅</span>
                        <div>
                            <p className="text-[12px] font-semibold" style={{ color: 'var(--text-1)' }}>
                                Novo agendamento confirmado
                            </p>
                            <p className="text-[11px]" style={{ color: 'var(--text-3)' }}>
                                Kauê · amanhã às 10:00
                            </p>
                        </div>
                    </div>

                    <h2
                        className="text-4xl leading-tight"
                        style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}
                    >
                        Seu WhatsApp<br />
                        <span className="italic" style={{ color: 'var(--text-2)' }}>
                            agenda enquanto<br />você trabalha.
                        </span>
                    </h2>

                    <p className="mt-4 text-sm leading-relaxed" style={{ color: 'var(--text-3)' }}>
                        Clientes mandam mensagem, a IA entende e confirma o horário — automaticamente.
                    </p>

                    {/* Mini stats */}
                    <div className="mt-8 flex gap-6">
                        {[
                            { n: '24h',  label: 'atendimento' },
                            { n: '5min', label: 'para configurar' },
                            { n: '0',    label: 'planilha' },
                        ].map(s => (
                            <div key={s.n}>
                                <p
                                    className="text-xl font-bold"
                                    style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}
                                >
                                    {s.n}
                                </p>
                                <p className="text-[11px]" style={{ color: 'var(--text-3)' }}>{s.label}</p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Rodapé esquerdo */}
                <p className="relative text-[11px]" style={{ color: 'var(--text-3)' }}>
                    © {new Date().getFullYear()} AgendaBot
                </p>
            </div>

            {/* ── Painel direito — formulário ─────────────────────────────────── */}
            <div className="flex w-full flex-col items-center justify-center px-6 py-12 lg:w-[420px] lg:flex-none">
                {/* Back link (mobile) */}
                <div className="mb-6 w-full max-w-sm lg:hidden">
                    <Link
                        href={route('home')}
                        className="text-xs transition-colors hover:text-primary"
                        style={{ color: 'var(--text-3)' }}
                    >
                        ← Voltar ao início
                    </Link>
                </div>

                <div className="w-full max-w-sm">
                    {/* Header mobile */}
                    <div className="mb-8 lg:hidden text-center">
                        <div className="mb-3 flex items-center justify-center gap-2">
                            <span
                                className="flex h-8 w-8 items-center justify-center rounded-xl text-sm font-bold text-white"
                                style={{ background: JADE }}
                            >
                                A
                            </span>
                            <span className="text-xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>
                                AgendaBot
                            </span>
                        </div>
                    </div>

                    {/* Título */}
                    <div className="mb-7">
                        <h1 className="text-2xl font-bold" style={{ color: 'var(--text-1)' }}>
                            Bem-vindo de volta
                        </h1>
                        <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>
                            Entre com seu e-mail e senha para continuar.
                        </p>
                    </div>

                    {/* Status message */}
                    {status && (
                        <div
                            className="mb-5 rounded-xl px-4 py-3 text-sm"
                            style={{
                                background: 'rgba(110,231,183,0.08)',
                                border:     '1px solid rgba(110,231,183,0.2)',
                                color:      '#6ee7b7',
                            }}
                        >
                            {status}
                        </div>
                    )}

                    {/* Form */}
                    <form onSubmit={submit} className="space-y-4">
                        {/* E-mail */}
                        <div>
                            <label className="label mb-1.5 block" htmlFor="email">E-mail</label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                className="input"
                                placeholder="seu@email.com"
                                autoComplete="username"
                                autoFocus
                                required
                            />
                            {errors.email && (
                                <p className="mt-1 text-xs text-red-400">{errors.email}</p>
                            )}
                        </div>

                        {/* Senha */}
                        <div>
                            <div className="mb-1.5 flex items-center justify-between">
                                <label className="label" htmlFor="password">Senha</label>
                                {canResetPassword && (
                                    <Link
                                        href={route('password.request')}
                                        className="text-[11px] transition-colors hover:brightness-125"
                                        style={{ color: 'var(--accent)' }}
                                    >
                                        Esqueci a senha
                                    </Link>
                                )}
                            </div>
                            <div className="relative">
                                <input
                                    id="password"
                                    type={showPass ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={e => setData('password', e.target.value)}
                                    className="input pr-10"
                                    placeholder="••••••••"
                                    autoComplete="current-password"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPass(s => !s)}
                                    className="absolute inset-y-0 right-0 flex items-center px-3 transition-colors"
                                    style={{ color: 'var(--text-3)' }}
                                    tabIndex={-1}
                                >
                                    {showPass ? (
                                        <svg width={15} height={15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                                            <line x1="1" y1="1" x2="23" y2="23"/>
                                        </svg>
                                    ) : (
                                        <svg width={15} height={15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    )}
                                </button>
                            </div>
                            {errors.password && (
                                <p className="mt-1 text-xs text-red-400">{errors.password}</p>
                            )}
                        </div>

                        {/* Lembrar de mim */}
                        <label
                            className="flex cursor-pointer items-center gap-2.5 text-sm"
                            style={{ color: 'var(--text-2)' }}
                        >
                            <input
                                type="checkbox"
                                checked={data.remember}
                                onChange={e => setData('remember', e.target.checked as false)}
                                className="rounded"
                                style={{ accentColor: INDIGO }}
                            />
                            Lembrar de mim
                        </label>

                        {/* Submit */}
                        <button
                            type="submit"
                            disabled={processing}
                            className="relative mt-2 w-full overflow-hidden rounded-xl py-3 text-sm font-semibold text-white transition-all hover:brightness-110 disabled:opacity-60"
                            style={{ background: INDIGO }}
                        >
                            {processing ? (
                                <span className="flex items-center justify-center gap-2">
                                    <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    Entrando…
                                </span>
                            ) : (
                                'Entrar'
                            )}
                        </button>
                    </form>

                    {/* Sign up link */}
                    <p className="mt-6 text-center text-sm" style={{ color: 'var(--text-3)' }}>
                        Ainda não tem conta?{' '}
                        <Link
                            href={route('onboarding.step1')}
                            className="font-medium transition-colors hover:brightness-125"
                            style={{ color: 'var(--accent)' }}
                        >
                            Começar grátis →
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
