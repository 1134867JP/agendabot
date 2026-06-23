import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Tenant, TipoServico } from '@/types';

interface Props extends PageProps {
    tenant: Tenant & { users: { id: number; name: string; email: string }[] };
}

const TIPOS: { value: TipoServico; label: string; emoji: string }[] = [
    { value: 'barbeiro',      label: 'Barbearia',        emoji: '✂️' },
    { value: 'quadra',        label: 'Quadra Esportiva', emoji: '🏟️' },
    { value: 'estetica',      label: 'Estética',         emoji: '💆' },
    { value: 'personalizado', label: 'Personalizado',    emoji: '⚙️' },
];

export default function TenantEdit({ tenant }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        nome:         tenant.nome,
        tipo_servico: tenant.tipo_servico,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('superadmin.tenants.update', tenant.id));
    };

    const dono = tenant.users?.[0];

    return (
        <AppLayout title={`Editar: ${tenant.nome}`}>
            <Head title="Editar Tenant" />

            <div className="mx-auto max-w-lg space-y-4">
                <div className="card p-7">
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
                            <div className="grid grid-cols-2 gap-2">
                                {TIPOS.map(t => (
                                    <button
                                        key={t.value}
                                        type="button"
                                        onClick={() => setData('tipo_servico', t.value)}
                                        className="flex items-center gap-2.5 rounded-xl border-2 px-4 py-3 text-sm transition-all"
                                        style={data.tipo_servico === t.value
                                            ? { borderColor: 'var(--accent)', background: 'var(--accent-light)', color: 'white' }
                                            : { borderColor: 'var(--border-strong)', background: 'var(--bg-surface-2)', color: 'var(--text-2)' }
                                        }
                                    >
                                        <span>{t.emoji}</span>
                                        <span className="font-medium">{t.label}</span>
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="label mb-1">Slug</label>
                            <input
                                value={tenant.slug}
                                readOnly
                                className="input cursor-not-allowed opacity-50"
                            />
                        </div>

                        {dono && (
                            <div className="rounded-xl px-4 py-3" style={{ background: 'var(--bg-surface-2)', border: '1px solid var(--border)' }}>
                                <p className="text-xs font-semibold uppercase tracking-wide mb-1" style={{ color: 'var(--text-3)' }}>Dono</p>
                                <p className="text-sm font-medium text-primary">{dono.name}</p>
                                <p className="text-xs" style={{ color: 'var(--text-3)' }}>{dono.email}</p>
                            </div>
                        )}

                        <div className="flex gap-3 pt-2">
                            <button type="submit" disabled={processing} className="btn-primary flex-1 justify-center py-2.5">
                                {processing ? 'Salvando…' : 'Salvar alterações'}
                            </button>
                            <a href={route('superadmin.tenants.index')} className="btn-secondary px-6">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
