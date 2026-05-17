import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';

interface Props extends PageProps {
    user: { name: string };
}

const steps = [
    {
        n: 1,
        title: 'Conecte seu WhatsApp',
        desc: 'Acesse a aba WhatsApp e escaneie o QR Code com o celular do estabelecimento.',
    },
    {
        n: 2,
        title: 'Cadastre seus recursos',
        desc: 'Adicione barbeiros, quadras ou serviços e configure os horários de funcionamento.',
    },
    {
        n: 3,
        title: 'Compartilhe com seus clientes',
        desc: 'Divulgue o número de WhatsApp — o bot já estará pronto para atender.',
    },
];

export default function OnboardingSucesso({ user }: Props) {
    const firstName = user.name.split(' ')[0];

    return (
        <div
            className="flex min-h-screen flex-col items-center justify-center px-4"
            style={{ background: 'var(--bg-app)' }}
        >
            <Head title="Tudo pronto!" />

            <div className="fixed left-0 right-0 top-0 h-0.5" style={{ background: 'var(--accent)' }} />

            <div className="w-full max-w-lg text-center">
                <div
                    className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full text-3xl"
                    style={{ background: 'var(--accent-light)', border: '1px solid var(--border-strong)' }}
                >
                    ✓
                </div>

                <h1 className="text-3xl text-white" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                    Tudo pronto, {firstName}!
                </h1>
                <p className="mt-2 text-sm" style={{ color: 'var(--text-3)' }}>Seu estabelecimento está configurado.</p>

                <div className="mt-8 space-y-3 text-left">
                    {steps.map(s => (
                        <div key={s.n} className="card flex items-start gap-4 p-4">
                            <div
                                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                                style={{ background: 'var(--accent)' }}
                            >
                                {s.n}
                            </div>
                            <div>
                                <p className="font-medium text-white">{s.title}</p>
                                <p className="mt-0.5 text-sm" style={{ color: 'var(--text-3)' }}>{s.desc}</p>
                            </div>
                        </div>
                    ))}
                </div>

                <Link
                    href={route('tenant.dashboard')}
                    className="btn-primary mt-8 inline-flex justify-center px-8 py-3 text-base"
                >
                    Ir para o painel →
                </Link>
            </div>
        </div>
    );
}
