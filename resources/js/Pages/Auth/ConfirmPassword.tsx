import FormField from '@/Components/UI/FormField';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirmar senha" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold text-primary">Confirme sua senha</h1>
                <p className="mt-1 text-sm text-secondary">Esta é uma área protegida. Confirme sua senha para continuar.</p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <FormField label="Senha" htmlFor="password" error={errors.password} required>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="input"
                        autoFocus
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </FormField>

                <button type="submit" className="btn-primary min-h-11 w-full justify-center" disabled={processing}>
                    {processing ? 'Confirmando…' : 'Confirmar e continuar'}
                </button>
            </form>
        </GuestLayout>
    );
}
