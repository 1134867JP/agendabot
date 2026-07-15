import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import EstabelecimentoForm from '@/Components/EstabelecimentoForm';

export default function EstabelecimentoCreate(_: PageProps) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center px-4" style={{ background: 'var(--bg-app)' }}>
            <Head title="Novo estabelecimento" />

            <div className="w-full max-w-md">
                <div className="mb-8">
                    <div
                        className="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl text-2xl"
                        style={{ background: 'var(--bg-surface-2)' }}
                    >
                        🏢
                    </div>
                    <h1 className="text-2xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}>
                        Novo estabelecimento
                    </h1>
                    <p className="mt-2 text-sm" style={{ color: 'var(--text-3)' }}>
                        Adicione outro estabelecimento à sua conta. Cada um tem seu próprio WhatsApp, agenda e configurações.
                    </p>
                </div>

                <EstabelecimentoForm submitLabel="Criar estabelecimento →" />

                <Link
                    href={route('dashboard')}
                    className="mt-4 block text-center text-sm transition-colors"
                    style={{ color: 'var(--text-3)' }}
                >
                    ← Voltar
                </Link>
            </div>
        </div>
    );
}
