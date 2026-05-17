import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';


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

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <div
            className="flex min-h-screen items-center justify-center px-4"
            style={{ background: 'var(--bg-app)' }}
        >
            <Head title="Entrar" />

            <div className="w-full max-w-sm">
                {/* Back link */}
                <div className="mb-6">
                    <Link
                        href={route('home')}
                        className="inline-flex items-center gap-1 text-xs transition-colors"
                        style={{ color: 'var(--text-3)' }}
                        onMouseEnter={e => (e.currentTarget.style.color = 'var(--text-2)')}
                        onMouseLeave={e => (e.currentTarget.style.color = 'var(--text-3)')}
                    >
                        ← Voltar ao início
                    </Link>
                </div>

                {/* Logo */}
                <div className="mb-8 text-center">
                    <div className="mb-3 flex items-center justify-center gap-2">
                        <span className="inline-block h-2 w-2 rounded-full" style={{ background: 'var(--emerald)' }} />
                        <span className="text-2xl text-white" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                            AgendaBot
                        </span>
                    </div>
                    <h2 className="text-xl font-semibold text-white">Bem-vindo de volta</h2>
                    <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>Entre na sua conta AgendaBot.</p>
                </div>

                {/* Card */}
                <div className="card p-7">
                    {status && (
                        <div
                            className="mb-4 rounded-lg px-4 py-3 text-sm"
                            style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)', color: '#6ee7b7' }}
                        >
                            {status}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="label mb-1" htmlFor="email">E-mail</label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                className="input"
                                autoComplete="username"
                                autoFocus
                                required
                            />
                            {errors.email && <p className="mt-1 text-xs text-red-400">{errors.email}</p>}
                        </div>

                        <div>
                            <label className="label mb-1" htmlFor="password">Senha</label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                className="input"
                                autoComplete="current-password"
                                required
                            />
                            {errors.password && <p className="mt-1 text-xs text-red-400">{errors.password}</p>}
                        </div>

                        <div className="flex items-center justify-between pt-1">
                            <label className="flex cursor-pointer items-center gap-2 text-sm" style={{ color: 'var(--text-2)' }}>
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={e => setData('remember', e.target.checked as false)}
                                    className="rounded"
                                    style={{ accentColor: 'var(--accent)' }}
                                />
                                Lembrar de mim
                            </label>
                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="text-xs transition-colors"
                                    style={{ color: 'var(--accent)' }}
                                >
                                    Esqueci a senha
                                </Link>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="btn-primary w-full justify-center py-2.5 text-base mt-2"
                        >
                            {processing ? 'Entrando…' : 'Entrar'}
                        </button>
                    </form>
                </div>

                <p className="mt-5 text-center text-sm" style={{ color: 'var(--text-3)' }}>
                    Ainda não tem conta?{' '}
                    <Link href={route('onboarding.step1')} className="transition-colors" style={{ color: 'var(--accent)' }}>
                        Começar grátis →
                    </Link>
                </p>
            </div>
        </div>
    );
}
