import { useForm } from '@inertiajs/react';
import { Recurso } from '@/types';

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

interface Props {
    recurso?: Recurso;
    onCancel?: () => void;
}

export default function RecursoForm({ recurso, onCancel }: Props) {
    const isEdit = !!recurso;

    const { data, setData, post, put, processing, errors } = useForm({
        nome: recurso?.nome ?? '',
        descricao: recurso?.descricao ?? '',
        valor_hora: recurso?.valor_hora?.toString() ?? '',
        duracao_padrao_minutos: recurso?.duracao_padrao_minutos?.toString() ?? '60',
        ativo: recurso?.ativo ?? true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEdit) {
            put(route('recursos.update', recurso!.id));
        } else {
            post(route('recursos.store'));
        }
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700">Nome *</label>
                    <input
                        type="text"
                        value={data.nome}
                        onChange={e => setData('nome', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        required
                    />
                    {errors.nome && <p className="mt-1 text-xs text-red-600">{errors.nome}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Descrição</label>
                    <input
                        type="text"
                        value={data.descricao}
                        onChange={e => setData('descricao', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Valor/hora (R$)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        value={data.valor_hora}
                        onChange={e => setData('valor_hora', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700">Duração padrão (min) *</label>
                    <input
                        type="number"
                        min="15"
                        step="15"
                        value={data.duracao_padrao_minutos}
                        onChange={e => setData('duracao_padrao_minutos', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        required
                    />
                </div>
            </div>

            {isEdit && (
                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={data.ativo}
                        onChange={e => setData('ativo', e.target.checked)}
                        className="rounded border-gray-300"
                    />
                    Ativo
                </label>
            )}

            {isEdit && recurso && (
                <HorariosSection recurso={recurso} />
            )}

            <div className="flex gap-3">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    {processing ? 'Salvando...' : isEdit ? 'Salvar Alterações' : 'Criar Recurso'}
                </button>
                {onCancel && (
                    <button type="button" onClick={onCancel} className="rounded-md border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </button>
                )}
            </div>
        </form>
    );
}

function HorariosSection({ recurso }: { recurso: Recurso }) {
    const { data, setData, post, processing } = useForm({ dia_semana: 0, abertura: '09:00', fechamento: '18:00' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('horarios.store', recurso.id));
    };

    return (
        <div className="border-t pt-4">
            <h4 className="mb-3 text-sm font-semibold text-gray-700">Horários de Funcionamento</h4>

            {recurso.horarios_funcionamento.length > 0 && (
                <div className="mb-3 space-y-1">
                    {recurso.horarios_funcionamento
                        .sort((a, b) => a.dia_semana - b.dia_semana)
                        .map(h => (
                            <div key={h.id} className="flex items-center justify-between rounded bg-gray-50 px-3 py-1.5 text-sm">
                                <span className="font-medium w-10">{DIAS[h.dia_semana]}</span>
                                <span className="text-gray-600">{h.abertura} – {h.fechamento}</span>
                            </div>
                        ))
                    }
                </div>
            )}

            <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                <div>
                    <label className="block text-xs text-gray-600">Dia</label>
                    <select
                        value={data.dia_semana}
                        onChange={e => setData('dia_semana', Number(e.target.value))}
                        className="mt-0.5 rounded-md border-gray-300 text-sm shadow-sm"
                    >
                        {DIAS.map((d, i) => <option key={i} value={i}>{d}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-xs text-gray-600">Abertura</label>
                    <input type="time" value={data.abertura} onChange={e => setData('abertura', e.target.value)}
                        className="mt-0.5 rounded-md border-gray-300 text-sm shadow-sm" />
                </div>
                <div>
                    <label className="block text-xs text-gray-600">Fechamento</label>
                    <input type="time" value={data.fechamento} onChange={e => setData('fechamento', e.target.value)}
                        className="mt-0.5 rounded-md border-gray-300 text-sm shadow-sm" />
                </div>
                <button type="submit" disabled={processing}
                    className="rounded-md bg-gray-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-gray-800 disabled:opacity-50">
                    Adicionar
                </button>
            </form>
        </div>
    );
}
