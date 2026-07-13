import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import ForceDark from '@/Components/ForceDark';

const INDIGO = '#6366F1';
const JADE   = '#00a884';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <ForceDark>
        <div className="flex min-h-[100dvh]" style={{ background: 'var(--bg-app)' }}>
            <Head title="Esqueci a senha" />

            {/* ── Painel esquerdo — marca ─────────────────────────────────────── */}
            <div
                className="relative hidden flex-1 flex-col justify-between overflow-hidden p-10 lg:flex"
                style={{ background: 'var(--bg-sidebar)', borderRight: '1px solid var(--border)' }}
            >
                <div className="pointer-events-none absolute -left-20 -top-20 h-64 w-64 rounded-full opacity-20 blur-3xl" style={{ background: INDIGO }} />
                <div className="pointer-events-none absolute bottom-10 right-0 h-48 w-48 rounded-full opacity-10 blur-3xl" style={{ background: JADE }} />

                <Link href={route('home')} className="relative flex items-center gap-2.5">
                    <span className="flex h-8 w-8 items-center justify-center rounded-xl text-sm font-bold text-white" style={{ background: JADE }}>A</span>
                    <span className="text-xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>Agendou</span>
                </Link>

                <div className="relative">
                    <div
                        className="mb-8 inline-flex items-center gap-3 rounded-2xl px-4 py-3"
                        style={{ background: 'rgba(255,255,255,0.04)', border: '1px solid rgba(255,255,255,0.08)' }}
                    >
                        <span className="text-xl">🔐</span>
                        <div>
                            <p className="text-[12px] font-semibold" style={{ color: 'var(--text-1)' }}>Recuperação segura</p>
                            <p className="text-[11px]" style={{ color: 'var(--text-3)' }}>Link enviado direto pro seu e-mail</p>
                        </div>
                    </div>

                    <h2 className="text-4xl leading-tight" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>
                        Esqueceu<br />
                        <span className="italic" style={{ color: 'var(--text-2)' }}>a senha?</span>
                    </h2>
                    <p className="mt-4 text-sm leading-relaxed" style={{ color: 'var(--text-3)' }}>
                        Sem problema. Informe seu e-mail e enviaremos um link para você criar uma nova senha.
                    </p>
                </div>

                <p className="relative text-[11px]" style={{ color: 'var(--text-3)' }}>© {new Date().getFullYear()} Agendou</p>
            </div>

            {/* ── Painel direito — formulário ─────────────────────────────────── */}
            <div className="flex w-full flex-col items-center justify-center px-6 py-12 lg:w-[420px] lg:flex-none">
                <div className="mb-6 w-full max-w-sm lg:hidden">
                    <Link href={route('login')} className="text-xs transition-colors hover:text-primary" style={{ color: 'var(--text-3)' }}>
                        ← Voltar ao login
                    </Link>
                </div>

                <div className="w-full max-w-sm">
                    {/* Header mobile */}
                    <div className="mb-8 lg:hidden text-center">
                        <div className="mb-3 flex items-center justify-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-xl text-sm font-bold text-white" style={{ background: JADE }}>A</span>
                            <span className="text-xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>Agendou</span>
                        </div>
                    </div>

                    <div className="mb-7">
                        <h1 className="text-2xl font-bold" style={{ color: 'var(--text-1)' }}>Recuperar senha</h1>
                        <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>
                            Digite seu e-mail e enviaremos um link de redefinição.
                        </p>
                    </div>

                    {status && (
                        <div
                            className="mb-5 rounded-xl px-4 py-3 text-sm"
                            style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)', color: '#6ee7b7' }}
                        >
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="label mb-1.5 block" htmlFor="email">E-mail</label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                className="input"
                                placeholder="seu@email.com"
                                autoComplete="email"
                                autoFocus
                                required
                            />
                            {errors.email && <p className="mt-1 text-xs text-red-400">{errors.email}</p>}
                        </div>

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
                                    Enviando…
                                </span>
                            ) : 'Enviar link de redefinição'}
                        </button>
                    </form>

                    <p className="mt-6 text-center text-sm" style={{ color: 'var(--text-3)' }}>
                        Lembrou a senha?{' '}
                        <Link href={route('login')} className="font-medium transition-colors hover:brightness-125" style={{ color: 'var(--accent)' }}>
                            Voltar ao login →
                        </Link>
                    </p>
                </div>
            </div>
        </div>
        </ForceDark>
    );
}
