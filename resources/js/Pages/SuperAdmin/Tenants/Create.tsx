import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, TipoServico } from '@/types';
import { TIPOS_SERVICO } from '@/constants/tiposServico';

export default function TenantCreate(_: PageProps) {
    const { data, setData, post, processing, errors } = useForm({
        nome:                        '',
        tipo_servico:                'barbeiro' as TipoServico,
        tipo_servico_personalizado:  '',
        email_dono:                  '',
        senha_dono:                  '',
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
                            <label className="label mb-1" htmlFor="tenant-nome">Nome do estabelecimento</label>
                            <input
                                id="tenant-nome"
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
                                {TIPOS_SERVICO.map(t => (
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
                            {data.tipo_servico === 'personalizado' && (
                                <input
                                    aria-label="Tipo de serviço personalizado"
                                    value={data.tipo_servico_personalizado}
                                    onChange={e => setData('tipo_servico_personalizado', e.target.value)}
                                    className="input mt-2"
                                    placeholder="Ex: Clínica veterinária, Estúdio de pilates…"
                                    required
                                />
                            )}
                            {errors.tipo_servico && <p className="mt-1 text-xs text-red-400">{errors.tipo_servico}</p>}
                            {errors.tipo_servico_personalizado && <p className="mt-1 text-xs text-red-400">{errors.tipo_servico_personalizado}</p>}
                        </div>

                        <hr style={{ borderColor: 'var(--border)' }} />

                        <p className="text-xs font-semibold uppercase tracking-wider" style={{ color: 'var(--text-3)' }}>
                            Dados do dono
                        </p>

                        {/* Email */}
                        <div>
                            <label className="label mb-1" htmlFor="tenant-email-dono">E-mail</label>
                            <input
                                id="tenant-email-dono"
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
                            <label className="label mb-1" htmlFor="tenant-senha-dono">Senha inicial</label>
                            <input
                                id="tenant-senha-dono"
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
