import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Tenant, TipoServico } from '@/types';
import TipoServicoSelector from '@/Components/TipoServicoSelector';

interface Props extends PageProps {
    tenant: Tenant;
}

export default function Configuracoes({ tenant }: Props) {
    const { data, setData, put, processing, errors, wasSuccessful } = useForm({
        nome:                       tenant.nome,
        tipo_servico:               tenant.tipo_servico,
        tipo_servico_personalizado: tenant.tipo_servico_personalizado ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('tenant.configuracoes.update'));
    };

    return (
        <AppLayout title="Configurações">
            <Head title="Configurações" />

            <div className="mx-auto max-w-lg space-y-6">
                {wasSuccessful && (
                    <div className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-emerald-400" style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)' }}>
                        <span>✓</span>
                        Configurações salvas com sucesso.
                    </div>
                )}

                <div className="card p-7">
                    <h2 className="mb-6 text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                        Dados do estabelecimento
                    </h2>
                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <label className="label mb-1">Nome do estabelecimento</label>
                            <input
                                value={data.nome}
                                onChange={e => setData('nome', e.target.value)}
                                className="input"
                                required
                            />
                            {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                        </div>

                        <div>
                            <label className="label mb-2">Tipo de serviço</label>
                            <TipoServicoSelector
                                value={data.tipo_servico}
                                onChange={v => setData('tipo_servico', v as TipoServico)}
                                customValue={data.tipo_servico_personalizado}
                                onChangeCustom={v => setData('tipo_servico_personalizado', v)}
                                error={errors.tipo_servico || errors.tipo_servico_personalizado}
                            />
                        </div>

                        <div>
                            <label className="label mb-1">Slug (identificador único)</label>
                            <input
                                value={tenant.slug}
                                readOnly
                                className="input cursor-not-allowed opacity-50"
                            />
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                O slug não pode ser alterado após a criação do estabelecimento.
                            </p>
                        </div>

                        <div className="pt-2">
                            <button type="submit" disabled={processing} className="btn-primary w-full justify-center py-2.5">
                                {processing ? 'Salvando…' : 'Salvar configurações'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
