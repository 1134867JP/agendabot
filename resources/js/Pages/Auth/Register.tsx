import FormField from '@/Components/UI/FormField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Criar conta" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-primary">Criar sua conta</h1>
                <p className="mt-1 text-sm text-secondary">Comece a organizar os atendimentos do seu estabelecimento.</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Nome" htmlFor="name" error={errors.name} required>
                    <input
                        id="name"
                        name="name"
                        value={data.name}
                        className="input"
                        autoComplete="name"
                        autoFocus
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                </FormField>

                <FormField label="E-mail" htmlFor="email" error={errors.email} required>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="input"
                        autoComplete="email"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                </FormField>

                <FormField label="Senha" htmlFor="password" error={errors.password} hint="Use pelo menos 8 caracteres." required>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="input"
                        autoComplete="new-password"
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                </FormField>

                <FormField label="Confirmar senha" htmlFor="password_confirmation" error={errors.password_confirmation} required>
                    <input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="input"
                        autoComplete="new-password"
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        required
                    />
                </FormField>

                <button type="submit" className="btn-primary min-h-11 w-full justify-center" disabled={processing}>
                    {processing ? 'Criando conta…' : 'Criar conta'}
                </button>
            </form>

            <p className="mt-6 text-center text-sm text-secondary">
                Já possui uma conta?{' '}
                <Link href={route('login')} className="font-medium" style={{ color: 'var(--accent)' }}>Entrar</Link>
            </p>
        </GuestLayout>
    );
}
