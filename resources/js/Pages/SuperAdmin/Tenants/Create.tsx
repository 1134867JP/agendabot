import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, TipoServico } from '@/types';

const TIPOS: { value: TipoServico; label: string; emoji: string }[] = [
    { value: 'barbeiro',      label: 'Barbearia',        emoji: '✂️' },
    { value: 'quadra',        label: 'Quadra Esportiva', emoji: '🏟️' },
    { value: 'estetica',      label: 'Estética',         emoji: '💆' },
    { value: 'personalizado', label: 'Personalizado',    emoji: '⚙️' },
];

export default function TenantCreate(_: PageProps) {
    const { data, setData, post, processing, errors } = useForm({
        nome:          '',
        tipo_servico:  'barbeiro' as TipoServico,
        email_dono:    '',
        senha_dono:    '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('superadmin.tenants.store'));
    };

    return (
        <AppLayout title="Novo Tenant">
            <Head title="Novo Tenant" />

            <div className="mx-auto max-w-lg">
                <div className="card p-7">
                    <form onSubmit={submit} className="space-y-5">
                        {/* Nome */}
                        <div>
                            <label className="label mb-1">Nome do estabelecimento</label>
                            <input
                                value={data.nome}
                                onChange={e => setData('nome', e.target.value)}
                                className="input"
                                placeholder="Ex: Barbearia do Carlos"
                                required
                            />
                            {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                        </div>

                        {/* Tipo */}
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
                            {errors.tipo_servico && <p className="mt-1 text-xs text-red-400">{errors.tipo_servico}</p>}
                        </div>

                        <hr style={{ borderColor: 'var(--border)' }} />

                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                            Dados do dono
                        </p>

                        {/* Email */}
                        <div>
                            <label className="label mb-1">E-mail</label>
                            <input
                                type="email"
                                value={data.email_dono}
                                onChange={e => setData('email_dono', e.target.value)}
                                className="input"
                                placeholder="dono@exemplo.com"
                                required
                            />
                            {errors.email_dono && <p className="mt-1 text-xs text-red-400">{errors.email_dono}</p>}
                        </div>

                        {/* Senha */}
                        <div>
                            <label className="label mb-1">Senha inicial</label>
                            <input
                                type="password"
                                value={data.senha_dono}
                                onChange={e => setData('senha_dono', e.target.value)}
                                className="input"
                                placeholder="Mínimo 8 caracteres"
                                required
                                minLength={8}
                            />
                            {errors.senha_dono && <p className="mt-1 text-xs text-red-400">{errors.senha_dono}</p>}
                        </div>

                        <div className="flex gap-3 pt-2">
                            <button type="submit" disabled={processing} className="btn-primary flex-1 justify-center py-2.5">
                                {processing ? 'Criando…' : 'Criar tenant'}
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
