import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useRef, useState } from 'react';
import { Transition } from '@headlessui/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';

/* ─── Tipos ──────────────────────────────────────────────────────────── */
interface Props extends PageProps {
    mustVerifyEmail: boolean;
    status?: string;
}

/* ─── Sub-componentes inline ─────────────────────────────────────────── */
function Field({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div>
            <label className="label mb-1.5 block">{label}</label>
            {children}
        </div>
    );
}

function SavedMsg({ show }: { show: boolean }) {
    return (
        <Transition
            show={show}
            enter="transition ease-in-out duration-200"
            enterFrom="opacity-0 translate-y-1"
            leave="transition ease-in-out duration-200"
            leaveTo="opacity-0"
        >
            <span className="text-xs font-medium" style={{ color: '#6ee7b7' }}>Salvo!</span>
        </Transition>
    );
}

/* ─── Seção: dados pessoais ──────────────────────────────────────────── */
function DadosPessoaisForm({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const user = usePage<Props>().props.auth.user as any;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name:     user.name  ?? '',
        email:    user.email ?? '',
        telefone: user.telefone ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    return (
        <section>
            <div className="mb-5">
                <h2 className="text-base font-semibold" style={{ color: 'var(--text-1)' }}>Informações pessoais</h2>
                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>Atualize seu nome, e-mail e telefone.</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <Field label="Nome">
                    <input
                        type="text"
                        value={data.name}
                        onChange={e => setData('name', e.target.value)}
                        className="input"
                        placeholder="Seu nome completo"
                        autoComplete="name"
                        required
                    />
                    {errors.name && <p className="mt-1 text-xs text-red-400">{errors.name}</p>}
                </Field>

                <Field label="E-mail">
                    <input
                        type="email"
                        value={data.email}
                        onChange={e => setData('email', e.target.value)}
                        className="input"
                        placeholder="seu@email.com"
                        autoComplete="email"
                        required
                    />
                    {errors.email && <p className="mt-1 text-xs text-red-400">{errors.email}</p>}
                </Field>

                <Field label="Telefone (opcional)">
                    <input
                        type="tel"
                        value={data.telefone}
                        onChange={e => setData('telefone', e.target.value)}
                        className="input"
                        placeholder="(51) 99999-9999"
                        autoComplete="tel"
                    />
                    {errors.telefone && <p className="mt-1 text-xs text-red-400">{errors.telefone}</p>}
                </Field>

                {mustVerifyEmail && (user as any).email_verified_at === null && (
                    <div
                        className="rounded-xl px-4 py-3 text-xs"
                        style={{ background: 'rgba(251,191,36,0.08)', border: '1px solid rgba(251,191,36,0.2)', color: '#fbbf24' }}
                    >
                        Seu e-mail ainda não foi verificado.{' '}
                        <button
                            type="button"
                            onClick={() => router.post(route('verification.send'))}
                            className="underline hover:no-underline"
                        >
                            Reenviar verificação →
                        </button>
                        {status === 'verification-link-sent' && (
                            <span className="ml-2" style={{ color: '#6ee7b7' }}>Link enviado!</span>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4 pt-1">
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-primary rounded-xl px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        style={{ background: 'var(--accent)' }}
                    >
                        {processing ? 'Salvando…' : 'Salvar alterações'}
                    </button>
                    <SavedMsg show={recentlySuccessful} />
                </div>
            </form>
        </section>
    );
}

/* ─── Seção: alterar senha ───────────────────────────────────────────── */
function AlterarSenhaForm() {
    const passwordRef = useRef<HTMLInputElement>(null);
    const currentRef  = useRef<HTMLInputElement>(null);
    const [showCurrent, setShowCurrent] = useState(false);
    const [showNew,     setShowNew]     = useState(false);

    const { data, setData, errors, put, reset, processing, recentlySuccessful } = useForm({
        current_password:      '',
        password:              '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errs) => {
                if (errs.password) { reset('password', 'password_confirmation'); passwordRef.current?.focus(); }
                if (errs.current_password) { reset('current_password'); currentRef.current?.focus(); }
            },
        });
    };

    const Eye = ({ show, toggle }: { show: boolean; toggle: () => void }) => (
        <button type="button" onClick={toggle} tabIndex={-1}
            className="absolute inset-y-0 right-0 flex items-center px-3 transition-colors"
            style={{ color: 'var(--text-3)' }}>
            {show ? (
                <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/>
                    <line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            ) : (
                <svg width={14} height={14} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            )}
        </button>
    );

    return (
        <section>
            <div className="mb-5">
                <h2 className="text-base font-semibold" style={{ color: 'var(--text-1)' }}>Alterar senha</h2>
                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>Use uma senha longa e aleatória para manter sua conta segura.</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <Field label="Senha atual">
                    <div className="relative">
                        <input
                            ref={currentRef}
                            type={showCurrent ? 'text' : 'password'}
                            value={data.current_password}
                            onChange={e => setData('current_password', e.target.value)}
                            className="input pr-10"
                            placeholder="••••••••"
                            autoComplete="current-password"
                        />
                        <Eye show={showCurrent} toggle={() => setShowCurrent(s => !s)} />
                    </div>
                    {errors.current_password && <p className="mt-1 text-xs text-red-400">{errors.current_password}</p>}
                </Field>

                <Field label="Nova senha">
                    <div className="relative">
                        <input
                            ref={passwordRef}
                            type={showNew ? 'text' : 'password'}
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            className="input pr-10"
                            placeholder="••••••••"
                            autoComplete="new-password"
                        />
                        <Eye show={showNew} toggle={() => setShowNew(s => !s)} />
                    </div>
                    {errors.password && <p className="mt-1 text-xs text-red-400">{errors.password}</p>}
                </Field>

                <Field label="Confirmar nova senha">
                    <div className="relative">
                        <input
                            type={showNew ? 'text' : 'password'}
                            value={data.password_confirmation}
                            onChange={e => setData('password_confirmation', e.target.value)}
                            className="input pr-10"
                            placeholder="••••••••"
                            autoComplete="new-password"
                        />
                        <Eye show={showNew} toggle={() => setShowNew(s => !s)} />
                    </div>
                    {errors.password_confirmation && <p className="mt-1 text-xs text-red-400">{errors.password_confirmation}</p>}
                </Field>

                <div className="flex items-center gap-4 pt-1">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"
                        style={{ background: 'var(--accent)' }}
                    >
                        {processing ? 'Salvando…' : 'Alterar senha'}
                    </button>
                    <SavedMsg show={recentlySuccessful} />
                </div>
            </form>
        </section>
    );
}

/* ─── Seção: excluir conta ───────────────────────────────────────────── */
function ExcluirContaForm() {
    const [confirming, setConfirming] = useState(false);
    const { data, setData, delete: destroy, processing, errors, reset } = useForm({ password: '' });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => setConfirming(false),
            onError: () => {},
            onFinish: () => reset(),
        });
    };

    return (
        <section>
            <div className="mb-5">
                <h2 className="text-base font-semibold text-red-400">Excluir conta</h2>
                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                    Após a exclusão todos os dados serão permanentemente removidos.
                </p>
            </div>

            {!confirming ? (
                <button
                    type="button"
                    onClick={() => setConfirming(true)}
                    className="rounded-xl border px-5 py-2 text-sm font-medium text-red-400 transition-colors hover:bg-red-500/10"
                    style={{ borderColor: 'rgba(248,113,113,0.3)' }}
                >
                    Excluir minha conta
                </button>
            ) : (
                <form onSubmit={submit} className="space-y-4">
                    <p className="text-sm" style={{ color: 'var(--text-2)' }}>
                        Confirme sua senha para excluir permanentemente a conta.
                    </p>
                    <Field label="Sua senha">
                        <input
                            type="password"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            className="input"
                            placeholder="••••••••"
                            autoFocus
                        />
                        {errors.password && <p className="mt-1 text-xs text-red-400">{errors.password}</p>}
                    </Field>
                    <div className="flex gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl px-5 py-2 text-sm font-semibold text-white disabled:opacity-60"
                            style={{ background: '#ef4444' }}
                        >
                            {processing ? 'Excluindo…' : 'Confirmar exclusão'}
                        </button>
                        <button
                            type="button"
                            onClick={() => { setConfirming(false); reset(); }}
                            className="rounded-xl px-5 py-2 text-sm font-medium"
                            style={{ color: 'var(--text-2)', background: 'var(--bg-card)' }}
                        >
                            Cancelar
                        </button>
                    </div>
                </form>
            )}
        </section>
    );
}

/* ─── Divisor ─────────────────────────────────────────────────────────── */
function Divider() {
    return <div className="my-8" style={{ borderTop: '1px solid var(--border)' }} />;
}

/* ─── Página principal ───────────────────────────────────────────────── */
export default function ProfileEdit({ mustVerifyEmail, status }: Props) {
    return (
        <AppLayout title="Perfil">
            <Head title="Perfil" />

            <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6">
                {/* Card */}
                <div
                    className="rounded-2xl p-6 sm:p-8"
                    style={{ background: 'var(--bg-card)', border: '1px solid var(--border)' }}
                >
                    <DadosPessoaisForm mustVerifyEmail={mustVerifyEmail} status={status} />
                    <Divider />
                    <AlterarSenhaForm />
                    <Divider />
                    <ExcluirContaForm />
                </div>
            </div>
        </AppLayout>
    );
}
