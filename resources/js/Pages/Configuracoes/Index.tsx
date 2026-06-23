import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import WhatsAppConector from '@/Components/WhatsAppConector';
import RecursoForm from '@/Components/RecursoForm';
import { PageProps, Recurso, Tenant } from '@/types';

type Tab = 'geral' | 'whatsapp' | 'recursos';

interface Props extends PageProps {
    tenant: Tenant;
    recursos: Recurso[];
}

export default function ConfiguracaoIndex({ auth, tenant, recursos }: Props) {
    const [tab, setTab] = useState<Tab>('geral');
    const [editandoRecurso, setEditandoRecurso] = useState<number | null>(null);
    const [criandoRecurso, setCriandoRecurso] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        nome: tenant.nome,
        tipo_servico: tenant.tipo_servico,
    });

    const salvarGeral = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('configuracoes.update'));
    };

    const TABS: { id: Tab; label: string }[] = [
        { id: 'geral', label: 'Dados Gerais' },
        { id: 'whatsapp', label: 'WhatsApp' },
        { id: 'recursos', label: 'Recursos' },
    ];

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">{tenant.nome} — Configurações</h2>}>
            <Head title="Configurações" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

                    <div className="mb-6 border-b">
                        <div className="flex gap-6">
                            {TABS.map(t => (
                                <button key={t.id} onClick={() => setTab(t.id)}
                                    className={`pb-3 text-sm font-medium border-b-2 transition ${tab === t.id ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
                                    {t.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    {tab === 'geral' && (
                        <div className="rounded-lg border bg-white p-6 shadow-sm">
                            <form onSubmit={salvarGeral} className="space-y-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Nome</label>
                                    <input type="text" value={data.nome} onChange={e => setData('nome', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm" required />
                                    {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700">Tipo de Serviço</label>
                                    <select value={data.tipo_servico} onChange={e => setData('tipo_servico', e.target.value as typeof data.tipo_servico)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                        <option value="barbeiro">Barbearia</option>
                                        <option value="quadra">Quadra Esportiva</option>
                                        <option value="estetica">Estética</option>
                                        <option value="personalizado">Personalizado</option>
                                    </select>
                                </div>
                                <button type="submit" disabled={processing}
                                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-primary hover:bg-indigo-700 disabled:opacity-50">
                                    {processing ? 'Salvando...' : 'Salvar'}
                                </button>
                            </form>
                        </div>
                    )}

                    {tab === 'whatsapp' && (
                        <div className="rounded-lg border bg-white p-6 shadow-sm space-y-4">
                            <p className="text-sm text-gray-500">Instância: <span className="font-mono text-gray-800">{tenant.evolution_instance ?? '—'}</span></p>
                            <WhatsAppConector status={tenant.whatsapp_conectado ? 'open' : 'close'} instance={tenant.evolution_instance} />
                        </div>
                    )}

                    {tab === 'recursos' && (
                        <div className="space-y-4">
                            {recursos.map(recurso => (
                                <div key={recurso.id} className="rounded-lg border bg-white p-5 shadow-sm">
                                    {editandoRecurso === recurso.id ? (
                                        <RecursoForm recurso={recurso} onCancel={() => setEditandoRecurso(null)} />
                                    ) : (
                                        <div className="flex items-center justify-between">
                                            <div>
                                                <p className="font-medium text-gray-900">{recurso.nome}</p>
                                                <p className="text-sm text-gray-500">
                                                    {recurso.duracao_padrao_minutos}min
                                                    {recurso.valor_hora ? ` · R$ ${Number(recurso.valor_hora).toFixed(2)}/h` : ''}
                                                    {!recurso.ativo && <span className="ml-2 text-xs text-red-500">Inativo</span>}
                                                </p>
                                            </div>
                                            <button onClick={() => setEditandoRecurso(recurso.id)}
                                                className="text-sm text-indigo-600 hover:underline">
                                                Editar
                                            </button>
                                        </div>
                                    )}
                                </div>
                            ))}

                            {criandoRecurso ? (
                                <div className="rounded-lg border bg-white p-5 shadow-sm">
                                    <h4 className="mb-4 font-medium text-gray-800">Novo Recurso</h4>
                                    <RecursoForm onCancel={() => setCriandoRecurso(false)} />
                                </div>
                            ) : (
                                <button onClick={() => setCriandoRecurso(true)}
                                    className="rounded-lg border-2 border-dashed border-gray-300 w-full py-4 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition">
                                    + Adicionar Recurso
                                </button>
                            )}
                        </div>
                    )}

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
