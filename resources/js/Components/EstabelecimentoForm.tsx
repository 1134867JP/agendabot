import { useForm } from '@inertiajs/react';
import { TipoServico } from '@/types';
import TipoServicoSelector from '@/Components/TipoServicoSelector';

interface Props {
    submitLabel?: string;
}

/**
 * Formulário de criação de estabelecimento reutilizado no onboarding-lite do
 * Dashboard (usuário com 0 tenants) e na rota dedicada de novo estabelecimento.
 * Sempre posta para `tenants.store`.
 */
export default function EstabelecimentoForm({ submitLabel = 'Criar estabelecimento →' }: Props) {
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
        <form
            onSubmit={submit}
            className="space-y-5 rounded-2xl p-7"
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
                {processing ? 'Criando…' : submitLabel}
            </button>
        </form>
    );
}
