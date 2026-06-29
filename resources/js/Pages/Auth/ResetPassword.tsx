import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import ForceDark from '@/Components/ForceDark';

const INDIGO = '#6366F1';
const JADE   = '#00a884';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    const [showPass, setShowPass] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('password.store'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    const EyeIcon = ({ visible }: { visible: boolean }) => visible ? (
        <svg width={15} height={15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
            <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
            <line x1="1" y1="1" x2="23" y2="23"/>
        </svg>
    ) : (
        <svg width={15} height={15} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
    );

    return (
        <ForceDark>
        <div className="flex min-h-[100dvh]" style={{ background: 'var(--bg-app)' }}>
            <Head title="Nova senha" />

            {/* ── Painel esquerdo — marca ─────────────────────────────────────── */}
            <div
                className="relative hidden flex-1 flex-col justify-between overflow-hidden p-10 lg:flex"
                style={{ background: 'var(--bg-sidebar)', borderRight: '1px solid var(--border)' }}
            >
                <div className="pointer-events-none absolute -left-20 -top-20 h-64 w-64 rounded-full opacity-20 blur-3xl" style={{ background: INDIGO }} />
                <div className="pointer-events-none absolute bottom-10 right-0 h-48 w-48 rounded-full opacity-10 blur-3xl" style={{ background: JADE }} />

                <Link href={route('home')} className="relative flex items-center gap-2.5">
                    <span className="flex h-8 w-8 items-center justify-center rounded-xl text-sm font-bold text-white" style={{ background: JADE }}>A</span>
                    <span className="text-xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>AgendaBot</span>
                </Link>

                <div className="relative">
                    <div
                        className="mb-8 inline-flex items-center gap-3 rounded-2xl px-4 py-3"
                        style={{ background: 'rgba(255,255,255,0.04)', border: '1px solid rgba(255,255,255,0.08)' }}
                    >
                        <span className="text-xl">✅</span>
                        <div>
                            <p className="text-[12px] font-semibold" style={{ color: 'var(--text-1)' }}>Quase lá!</p>
                            <p className="text-[11px]" style={{ color: 'var(--text-3)' }}>Defina sua nova senha</p>
                        </div>
                    </div>

                    <h2 className="text-4xl leading-tight" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>
                        Nova senha,<br />
                        <span className="italic" style={{ color: 'var(--text-2)' }}>novo começo.</span>
                    </h2>
                    <p className="mt-4 text-sm leading-relaxed" style={{ color: 'var(--text-3)' }}>
                        Escolha uma senha forte com pelo menos 8 caracteres.
                    </p>
                </div>

                <p className="relative text-[11px]" style={{ color: 'var(--text-3)' }}>© {new Date().getFullYear()} AgendaBot</p>
            </div>

            {/* ── Painel direito — formulário ─────────────────────────────────── */}
            <div className="flex w-full flex-col items-center justify-center px-6 py-12 lg:w-[420px] lg:flex-none">
                <div className="w-full max-w-sm">
                    <div className="mb-8 lg:hidden text-center">
                        <div className="mb-3 flex items-center justify-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-xl text-sm font-bold text-white" style={{ background: JADE }}>A</span>
                            <span className="text-xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>AgendaBot</span>
                        </div>
                    </div>

                    <div className="mb-7">
                        <h1 className="text-2xl font-bold" style={{ color: 'var(--text-1)' }}>Redefinir senha</h1>
                        <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>
                            Crie uma nova senha para a conta <span style={{ color: 'var(--text-2)' }}>{email}</span>.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        {/* Senha nova */}
                        <div>
                            <label className="label mb-1.5 block" htmlFor="password">Nova senha</label>
                            <div className="relative">
                                <input
                                    id="password"
                                    type={showPass ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={e => setData('password', e.target.value)}
                                    className="input pr-10"
                                    placeholder="••••••••"
                                    autoComplete="new-password"
                                    autoFocus
                                    required
                                />
                                <button type="button" onClick={() => setShowPass(s => !s)} tabIndex={-1}
                                    className="absolute inset-y-0 right-0 flex items-center px-3 transition-colors"
                                    style={{ color: 'var(--text-3)' }}>
                                    <EyeIcon visible={showPass} />
                                </button>
                            </div>
                            {errors.password && <p className="mt-1 text-xs text-red-400">{errors.password}</p>}
                        </div>

                        {/* Confirmar senha */}
                        <div>
                            <label className="label mb-1.5 block" htmlFor="password_confirmation">Confirmar senha</label>
                            <div className="relative">
                                <input
                                    id="password_confirmation"
                                    type={showConfirm ? 'text' : 'password'}
                                    value={data.password_confirmation}
                                    onChange={e => setData('password_confirmation', e.target.value)}
                                    className="input pr-10"
                                    placeholder="••••••••"
                                    autoComplete="new-password"
                                    required
                                />
                                <button type="button" onClick={() => setShowConfirm(s => !s)} tabIndex={-1}
                                    className="absolute inset-y-0 right-0 flex items-center px-3 transition-colors"
                                    style={{ color: 'var(--text-3)' }}>
                                    <EyeIcon visible={showConfirm} />
                                </button>
                            </div>
                            {errors.password_confirmation && <p className="mt-1 text-xs text-red-400">{errors.password_confirmation}</p>}
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
                                    Salvando…
                                </span>
                            ) : 'Redefinir senha'}
                        </button>
                    </form>
                </div>
            </div>
        </div>
        </ForceDark>
    );
}
