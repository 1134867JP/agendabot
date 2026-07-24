import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verificar e-mail" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-primary">Verifique seu e-mail</h1>
                <p className="mt-1 text-sm leading-6 text-secondary">
                    Enviamos um link de confirmação para seu e-mail. Abra a mensagem e confirme o endereço para continuar.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-5 rounded-xl px-4 py-3 text-sm" style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)', color: '#6ee7b7' }} role="status">
                    Um novo link de verificação foi enviado.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="flex flex-col gap-3 sm:flex-row">
                    <button type="submit" className="btn-primary min-h-11 flex-1 justify-center" disabled={processing}>
                        {processing ? 'Enviando…' : 'Reenviar verificação'}
                    </button>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="btn-secondary min-h-11 flex-1 justify-center"
                    >
                        Sair da conta
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
