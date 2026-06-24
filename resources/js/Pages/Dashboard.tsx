import { Head, router, useForm } from '@inertiajs/react';
import { PageProps, Tenant, TipoServico } from '@/types';
import TipoServicoSelector from '@/Components/TipoServicoSelector';

const TIPO_EMOJI: Record<string, string> = {
    barbeiro: '✂️', quadra: '🏟️', estetica: '💆', personalizado: '⚙️',
    clinica: '🏥', studio: '📸',
};

interface Props extends PageProps {
    tenants: Tenant[];
}

function CriarPrimeiroTenant() {
    const { data, setData, post, processing, errors } = useForm({
        nome:                       '',
        tipo_servico:               'barbeiro' as TipoServico,
        tipo_servico_personalizado: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenants.store'));
    };

    return (
        <div className="w-full max-w-md">
            <div className="mb-8">
                <div
                    className="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl text-2xl"
                    style={{ background: 'var(--bg-surface-2)' }}
                >
                    🏢
                </div>
                <h1
                    className="text-2xl"
                    style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'var(--text-1)' }}
                >
                    Crie seu estabelecimento
                </h1>
                <p className="mt-2 text-sm" style={{ color: 'var(--text-3)' }}>
                    Configure o seu primeiro estabelecimento para começar a receber agendamentos pelo WhatsApp.
                </p>
            </div>

            <form
                onSubmit={submit}
                className="rounded-2xl p-7 space-y-5"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
            >
                <div>
                    <label className="label mb-1 block">Nome do estabelecimento</label>
                    <input
                        value={data.nome}
                        onChange={e => setData('nome', e.target.value)}
                        className="input"
                        placeholder="Ex: Barbearia do João"
                        required
                        autoFocus
                    />
                    {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                </div>

                <div>
                    <label className="label mb-2 block">Tipo de serviço</label>
                    <TipoServicoSelector
                        value={data.tipo_servico}
                        onChange={v => setData('tipo_servico', v as TipoServico)}
                        customValue={data.tipo_servico_personalizado}
                        onChangeCustom={v => setData('tipo_servico_personalizado', v)}
                        error={errors.tipo_servico || errors.tipo_servico_personalizado}
                    />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-xl py-3 text-sm font-semibold text-white transition-all hover:brightness-110 disabled:opacity-60"
                    style={{ background: 'var(--accent)' }}
                >
                    {processing ? 'Criando…' : 'Criar estabelecimento →'}
                </button>
            </form>
        </div>
    );
}

export default function Dashboard({ auth, tenants }: Props) {
    const selecionar = (tenant: Tenant) => {
        router.post(route('tenants.selecionar', tenant.id));
    };

    return (
        <div className="flex min-h-screen items-center justify-center px-4" style={{ background: 'var(--bg-app)' }}>
            <Head title="Selecionar estabelecimento" />

            {tenants.length === 0 ? (
                <CriarPrimeiroTenant />
            ) : (
                <div className="w-full max-w-md">
                    <div className="mb-8 flex items-center justify-between">
                        <h1 className="text-2xl text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
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
                                    <p className="font-medium text-primary truncate">{tenant.nome}</p>
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
            )}
        </div>
    );
}
