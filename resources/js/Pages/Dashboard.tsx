import { Head, router } from '@inertiajs/react';
import { PageProps, Tenant } from '@/types';

const TIPO_EMOJI: Record<string, string> = {
    barbeiro: '✂️', quadra: '🏟️', estetica: '💆', personalizado: '⚙️',
};

interface Props extends PageProps {
    tenants: Tenant[];
}

export default function Dashboard({ auth, tenants }: Props) {
    const selecionar = (tenant: Tenant) => {
        router.post(route('tenants.selecionar', tenant.id));
    };

    return (
        <div className="flex min-h-screen items-center justify-center px-4" style={{ background: 'var(--bg-app)' }}>
            <Head title="Selecionar estabelecimento" />

            <div className="w-full max-w-md">
                <div className="mb-8 flex items-center justify-between">
                    <h1 className="text-2xl text-white" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                        Selecione o estabelecimento
                    </h1>
                    <span className="text-sm" style={{ color: 'var(--text-3)' }}>{auth.user.name}</span>
                </div>

                <div className="space-y-3">
                    {tenants.map(tenant => (
                        <button
                            key={tenant.id}
                            onClick={() => selecionar(tenant)}
                            className="card group flex w-full items-center gap-4 p-4 text-left transition-all"
                            style={{ cursor: 'pointer' }}
                            onMouseEnter={e => { (e.currentTarget as HTMLElement).style.borderColor = 'var(--border-strong)'; (e.currentTarget as HTMLElement).style.background = 'var(--bg-surface2)'; }}
                            onMouseLeave={e => { (e.currentTarget as HTMLElement).style.borderColor = 'var(--border)'; (e.currentTarget as HTMLElement).style.background = 'var(--bg-surface)'; }}
                        >
                            <div
                                className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-xl"
                                style={{ background: 'var(--bg-surface-2)' }}
                            >
                                {TIPO_EMOJI[tenant.tipo_servico] ?? '🏢'}
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="font-medium text-white truncate">{tenant.nome}</p>
                                <div className="mt-0.5 flex items-center gap-1.5">
                                    <span
                                        className="h-1.5 w-1.5 rounded-full"
                                        style={{ background: tenant.whatsapp_conectado ? 'var(--emerald)' : 'var(--text-3)' }}
                                    />
                                    <span className="text-xs" style={{ color: 'var(--text-3)' }}>
                                        WhatsApp {tenant.whatsapp_conectado ? 'conectado' : 'desconectado'}
                                    </span>
                                </div>
                            </div>
                            <span style={{ color: 'var(--text-3)' }}>→</span>
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}
