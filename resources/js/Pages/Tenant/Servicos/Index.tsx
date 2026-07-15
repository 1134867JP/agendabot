import { Head, router, useForm } from '@inertiajs/react';
import { useState, useRef } from 'react';
import { useConfirm } from '@/hooks/useConfirm';
import ConfiguracoesLayout from '@/Layouts/ConfiguracoesLayout';
import { PageProps } from '@/types';
import Toggle from '@/Components/Toggle';

// ─── Types ───────────────────────────────────────────────────────────────────

interface Servico {
    id: number;
    nome: string;
    descricao: string | null;
    valor_min: number | null;
    valor_max: number | null;
    duracao_minutos: number;
    requer_avaliacao: boolean;
    ativo: boolean;
}

interface Props extends PageProps {
    servicos: Servico[];
}

const DURACOES = [15, 20, 30, 45, 60, 90, 120];

const brl = (value: number | null) =>
    value != null
        ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value)
        : null;

function faixaValor(s: Servico): string {
    const min = brl(s.valor_min);
    const max = brl(s.valor_max);
    if (min && max) return `${min} – ${max}`;
    if (min) return `a partir de ${min}`;
    if (max) return `até ${max}`;
    return 'Sem valor definido';
}

// ─── Formulário novo serviço (inline) ────────────────────────────────────────

function NovoServicoForm({ onClose }: { onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        nome: '',
        descricao: '',
        valor_min: '',
        valor_max: '',
        duracao_minutos: 30,
        requer_avaliacao: false,
        ativo: true,
    });
    const nomeRef = useRef<HTMLInputElement>(null);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.servicos.store'), {
            onSuccess: () => { reset(); onClose(); },
            preserveScroll: true,
        });
    };

    return (
        <div className="card overflow-hidden" style={{ borderColor: 'var(--accent)' }}>
            <div className="px-4 py-4 sm:px-6" style={{ borderBottom: '1px solid var(--border)' }}>
                <h3 className="font-semibold text-primary">Novo serviço</h3>
            </div>
            <form onSubmit={submit} className="space-y-4 px-4 py-5 sm:px-6">
                <div>
                    <label className="label mb-1" htmlFor="novo-servico-nome">Nome *</label>
                    <input
                        id="novo-servico-nome"
                        ref={nomeRef}
                        autoFocus
                        value={data.nome}
                        onChange={e => setData('nome', e.target.value)}
                        className="input"
                        placeholder="Ex: Consulta, Corte + Barba"
                        required
                    />
                    {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                </div>

                <div>
                    <label className="label mb-1">Descrição</label>
                    <textarea
                        value={data.descricao}
                        onChange={e => setData('descricao', e.target.value)}
                        rows={2}
                        className="input resize-none"
                        placeholder="Descreva brevemente o serviço"
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label mb-1">Valor mín</label>
                        <input
                            type="number" step="0.01" min="0"
                            value={data.valor_min}
                            onChange={e => setData('valor_min', e.target.value)}
                            className="input" placeholder="R$ 0,00"
                        />
                    </div>
                    <div>
                        <label className="label mb-1">Valor máx</label>
                        <input
                            type="number" step="0.01" min="0"
                            value={data.valor_max}
                            onChange={e => setData('valor_max', e.target.value)}
                            className="input" placeholder="R$ 0,00"
                        />
                    </div>
                    <div>
                        <label className="label mb-1">Duração *</label>
                        <select
                            value={data.duracao_minutos}
                            onChange={e => setData('duracao_minutos', parseInt(e.target.value))}
                            className="input"
                        >
                            {DURACOES.map(m => <option key={m} value={m}>{m} min</option>)}
                        </select>
                    </div>
                </div>

                <Toggle
                    checked={data.requer_avaliacao}
                    onChange={v => setData('requer_avaliacao', v)}
                    label="Requer avaliação prévia"
                />

                <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row">
                    <button type="submit" disabled={processing} className="btn-primary">
                        {processing ? 'Criando…' : 'Criar serviço'}
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    );
}

// ─── Formulário editar serviço (inline) ──────────────────────────────────────

function EditarServicoForm({
    servico,
    onClose,
}: {
    servico: Servico;
    onClose: () => void;
}) {
    const { data, setData, put, processing, errors } = useForm({
        nome: servico.nome,
        descricao: servico.descricao ?? '',
        valor_min: servico.valor_min?.toString() ?? '',
        valor_max: servico.valor_max?.toString() ?? '',
        duracao_minutos: servico.duracao_minutos,
        requer_avaliacao: servico.requer_avaliacao,
        ativo: servico.ativo,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('tenant.servicos.update', servico.id), {
            onSuccess: onClose,
            preserveScroll: true,
        });
    };

    const { confirm, modal: confirmModal } = useConfirm();

    const excluir = async () => {
        const ok = await confirm({ title: 'Remover serviço', message: `Remover "${servico.nome}"? Esta ação não pode ser desfeita.`, confirmLabel: 'Remover', variant: 'danger' });
        if (ok) router.delete(route('tenant.servicos.destroy', servico.id), { preserveScroll: true });
    };

    return (
        <>
        {confirmModal}
        <div className="card overflow-hidden" style={{ borderColor: 'var(--accent)' }}>
            <div className="px-4 py-4 sm:px-6" style={{ borderBottom: '1px solid var(--border)' }}>
                <h3 className="font-semibold text-primary">Editar serviço</h3>
            </div>
            <form onSubmit={submit} className="space-y-4 px-4 py-5 sm:px-6">
                <div>
                    <label className="label mb-1" htmlFor="editar-servico-nome">Nome *</label>
                    <input
                        id="editar-servico-nome"
                        autoFocus
                        value={data.nome}
                        onChange={e => setData('nome', e.target.value)}
                        className="input"
                        required
                    />
                    {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                </div>

                <div>
                    <label className="label mb-1">Descrição</label>
                    <textarea
                        value={data.descricao}
                        onChange={e => setData('descricao', e.target.value)}
                        rows={2}
                        className="input resize-none"
                    />
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label className="label mb-1">Valor mín</label>
                        <input
                            type="number" step="0.01" min="0"
                            value={data.valor_min}
                            onChange={e => setData('valor_min', e.target.value)}
                            className="input" placeholder="R$ 0,00"
                        />
                    </div>
                    <div>
                        <label className="label mb-1">Valor máx</label>
                        <input
                            type="number" step="0.01" min="0"
                            value={data.valor_max}
                            onChange={e => setData('valor_max', e.target.value)}
                            className="input" placeholder="R$ 0,00"
                        />
                    </div>
                    <div>
                        <label className="label mb-1">Duração *</label>
                        <select
                            value={data.duracao_minutos}
                            onChange={e => setData('duracao_minutos', parseInt(e.target.value))}
                            className="input"
                        >
                            {DURACOES.map(m => <option key={m} value={m}>{m} min</option>)}
                        </select>
                    </div>
                </div>

                <Toggle
                    checked={data.requer_avaliacao}
                    onChange={v => setData('requer_avaliacao', v)}
                    label="Requer avaliação prévia"
                />

                <Toggle
                    checked={data.ativo}
                    onChange={v => setData('ativo', v)}
                    label={data.ativo ? 'Ativo' : 'Inativo'}
                />

                <div className="flex flex-col items-stretch gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
                    <button
                        type="button"
                        onClick={excluir}
                        className="text-xs font-medium transition-opacity hover:opacity-70"
                        style={{ color: '#f87171' }}
                    >
                        Excluir serviço
                    </button>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row">
                        <button type="button" onClick={onClose} className="btn-secondary justify-center">
                            Cancelar
                        </button>
                        <button type="submit" disabled={processing} className="btn-primary">
                            {processing ? 'Salvando…' : 'Salvar'}
                        </button>
                    </div>
                </div>
            </form>
        </div>
        </>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────

export default function ServicosIndex({ servicos }: Props) {
    const [novoAberto, setNovoAberto] = useState(false);
    const [editando, setEditando] = useState<number | null>(null);

    const toggleAtivo = (s: Servico) => {
        router.put(
            route('tenant.servicos.update', s.id),
            {
                nome: s.nome,
                descricao: s.descricao ?? '',
                valor_min: s.valor_min?.toString() ?? '',
                valor_max: s.valor_max?.toString() ?? '',
                duracao_minutos: s.duracao_minutos,
                requer_avaliacao: s.requer_avaliacao,
                ativo: !s.ativo,
            },
            { preserveScroll: true }
        );
    };

    return (
        <ConfiguracoesLayout title="Serviços" subtitle="Serviços oferecidos, valores e duração">
            <Head title="Serviços" />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                    {servicos.length} serviço{servicos.length !== 1 ? 's' : ''} cadastrado{servicos.length !== 1 ? 's' : ''}
                </p>
                {!novoAberto && (
                    <button onClick={() => { setNovoAberto(true); setEditando(null); }} className="btn-primary">
                        + Novo serviço
                    </button>
                )}
            </div>

            <div className="space-y-3">
                {/* Formulário novo serviço inline */}
                {novoAberto && (
                    <NovoServicoForm onClose={() => setNovoAberto(false)} />
                )}

                {servicos.length === 0 && !novoAberto && (
                    <div className="card flex flex-col items-center gap-3 p-8 text-center sm:p-10" style={{ color: 'var(--text-3)' }}>
                        <div>
                            <p className="text-sm font-medium text-primary">Cadastre seu primeiro serviço</p>
                            <p className="mt-1 text-xs">Informe o que pode ser reservado, duração e valores.</p>
                        </div>
                        <button type="button" onClick={() => setNovoAberto(true)} className="btn-primary min-h-11">Criar serviço</button>
                    </div>
                )}

                {servicos.map(s => (
                    editando === s.id ? (
                        <EditarServicoForm
                            key={s.id}
                            servico={s}
                            onClose={() => setEditando(null)}
                        />
                    ) : (
                        <div key={s.id} className="card overflow-hidden">
                            <div className="flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                                    <div
                                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-primary"
                                        style={{ background: s.ativo ? 'var(--accent)' : 'rgba(255,255,255,0.15)' }}
                                    >
                                        {s.nome.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-primary">{s.nome}</p>
                                        {s.descricao && (
                                            <p className="mt-0.5 text-xs line-clamp-1" style={{ color: 'var(--text-3)' }}>
                                                {s.descricao}
                                            </p>
                                        )}
                                        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                            <span>{s.duracao_minutos} min</span>
                                            <span>{faixaValor(s)}</span>
                                            {s.requer_avaliacao && (
                                                <span className="rounded px-1.5 py-0.5" style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-3)' }}>
                                                    Requer avaliação
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:items-center">
                                    <span className={`badge col-span-2 justify-self-start sm:col-span-1 ${s.ativo ? 'badge-green' : 'badge-gray'}`}>
                                        {s.ativo ? 'Ativo' : 'Inativo'}
                                    </span>
                                    <button
                                        onClick={() => { setEditando(s.id); setNovoAberto(false); }}
                                        className="btn-secondary min-h-10 justify-center py-1.5 text-xs"
                                    >
                                        Editar
                                    </button>
                                    <button onClick={() => toggleAtivo(s)} className="btn-secondary min-h-10 justify-center py-1.5 text-xs">
                                        {s.ativo ? 'Desativar' : 'Ativar'}
                                    </button>
                                </div>
                            </div>
                        </div>
                    )
                ))}
            </div>
        </ConfiguracoesLayout>
    );
}
