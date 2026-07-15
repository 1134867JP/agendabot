import { Head, Link } from '@inertiajs/react';
import { PageProps, TipoServico } from '@/types';
import ForceDark from '@/Components/ForceDark';
import { usaProfissionais } from '@/lib/tenantNav';

interface Props extends PageProps {
    user: { name: string };
    tenant: { nome: string; tipo_servico: TipoServico } | null;
}

interface Step { n: number; title: string; desc: string; href?: string; linkLabel?: string }

export default function OnboardingSucesso({ user, tenant }: Props) {
    const firstName = user.name.split(' ')[0];

    // O catálogo varia por tipo: quadras cadastram Recursos, os demais Profissionais.
    const comProfissionais = tenant ? usaProfissionais(tenant.tipo_servico) : true;
    const catalogoRota   = comProfissionais ? 'tenant.profissionais.index' : 'tenant.recursos.index';
    const catalogoTitulo = comProfissionais ? 'Cadastre profissionais e serviços' : 'Cadastre seus recursos';

    // Ordem: primeiro montar o catálogo, testar no simulador e só então conectar o
    // WhatsApp — o passo de teste explicitamente acontece antes da conexão.
    const steps: Step[] = [
        {
            n: 1,
            title: catalogoTitulo,
            desc: 'Configure os itens agendáveis e seus horários de funcionamento.',
            href: catalogoRota,
            linkLabel: 'Abrir cadastro →',
        },
        {
            n: 2,
            title: 'Teste o bot no simulador',
            desc: 'Converse com uma versão segura antes de conectar o WhatsApp.',
            href: 'tenant.bot.simulador',
            linkLabel: 'Abrir simulador →',
        },
        {
            n: 3,
            title: 'Conecte seu WhatsApp',
            desc: 'Escaneie o QR Code com o celular do estabelecimento.',
            href: 'tenant.whatsapp',
            linkLabel: 'Conectar WhatsApp →',
        },
        {
            n: 4,
            title: 'Compartilhe com seus clientes',
            desc: 'Divulgue o número de WhatsApp — o bot já estará pronto para atender.',
        },
    ];

    return (
        <ForceDark>
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

                <h1 className="text-3xl text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                    Tudo pronto, {firstName}!
                </h1>
                <p className="mt-2 text-sm" style={{ color: 'var(--text-3)' }}>Seu estabelecimento está configurado.</p>

                <div className="mt-8 space-y-3 text-left">
                    {steps.map(s => (
                        <div key={s.n} className="card flex items-start gap-4 p-4">
                            <div
                                className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold text-primary"
                                style={{ background: 'var(--accent)' }}
                            >
                                {s.n}
                            </div>
                            <div>
                                <p className="font-medium text-primary">{s.title}</p>
                                <p className="mt-0.5 text-sm" style={{ color: 'var(--text-3)' }}>{s.desc}</p>
                                {s.href && (
                                    <Link href={route(s.href)} className="mt-2 inline-block text-xs font-medium" style={{ color: 'var(--accent)' }}>
                                        {s.linkLabel ?? 'Abrir →'}
                                    </Link>
                                )}
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
        </ForceDark>
    );
}
