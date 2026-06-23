import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';

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

// ─── Helpers ─────────────────────────────────────────────────────────────────

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

// ─── NovoServicoModal ─────────────────────────────────────────────────────────

function NovoServicoModal({ onClose }: { onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        nome: '',
        descricao: '',
        valor_min: '',
        valor_max: '',
        duracao_minutos: 30,
        requer_avaliacao: false,
        ativo: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.servicos.store'), {
            onSuccess: () => { reset(); onClose(); },
            preserveScroll: true,
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
            <div
                className="w-full max-w-md rounded-2xl p-7 shadow-2xl"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}
            >
                <div className="mb-5 flex items-start justify-between">
                    <h3
                        style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}
                        className="text-xl font-semibold text-white"
                    >
                        Novo serviço
                    </h3>
                    <button
                        onClick={onClose}
                        style={{ color: 'var(--text-3)' }}
                        className="hover:text-white transition-colors"
                    >
                        ✕
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    {/* Nome */}
                    <div>
                        <label className="label mb-1">Nome *</label>
                        <input
                            value={data.nome}
                            onChange={e => setData('nome', e.target.value)}
                            className="input"
                            placeholder="Ex: Consulta, Corte + Barba"
                            required
                        />
                        {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                    </div>

                    {/* Descrição */}
                    <div>
                        <label className="label mb-1">Descrição</label>
                        <textarea
                            value={data.descricao}
                            onChange={e => setData('descricao', e.target.value)}
                            rows={2}
                            className="input resize-none"
                            placeholder="Descreva brevemente o serviço"
                        />
                        {errors.descricao && <p className="mt-1 text-xs text-red-400">{errors.descricao}</p>}
                    </div>

                    {/* Valores e duração */}
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="label mb-1">Valor mín</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.valor_min}
                                onChange={e => setData('valor_min', e.target.value)}
                                className="input"
                                placeholder="R$ 0,00"
                            />
                            {errors.valor_min && <p className="mt-1 text-xs text-red-400">{errors.valor_min}</p>}
                        </div>
                        <div>
                            <label className="label mb-1">Valor máx</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.valor_max}
                                onChange={e => setData('valor_max', e.target.value)}
                                className="input"
                                placeholder="R$ 0,00"
                            />
                            {errors.valor_max && <p className="mt-1 text-xs text-red-400">{errors.valor_max}</p>}
                        </div>
                        <div>
                            <label className="label mb-1">Duração *</label>
                            <select
                                value={data.duracao_minutos}
                                onChange={e => setData('duracao_minutos', parseInt(e.target.value))}
                                className="input"
                            >
                                {DURACOES.map(m => (
                                    <option key={m} value={m}>{m} min</option>
                                ))}
                            </select>
                            {errors.duracao_minutos && <p className="mt-1 text-xs text-red-400">{errors.duracao_minutos}</p>}
                        </div>
                    </div>

                    {/* Requer avaliação */}
                    <label className="flex cursor-pointer items-center gap-3">
                        <div
                            onClick={() => setData('requer_avaliacao', !data.requer_avaliacao)}
                            className="relative flex h-5 w-9 cursor-pointer items-center rounded-full transition-colors"
                            style={{ background: data.requer_avaliacao ? 'var(--accent)' : 'rgba(255,255,255,0.15)' }}
                        >
                            <span
                                className={`absolute h-3.5 w-3.5 rounded-full bg-white shadow transition-transform ${
                                    data.requer_avaliacao ? 'translate-x-4' : 'translate-x-1'
                                }`}
                            />
                        </div>
                        <span className="text-sm" style={{ color: 'var(--text-2)' }}>
                            Requer avaliação prévia
                        </span>
                    </label>

                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit" disabled={processing} className="btn-primary">
                            {processing ? 'Criando…' : 'Criar serviço'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── EditarServicoModal ───────────────────────────────────────────────────────

function EditarServicoModal({
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

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
            <div
                className="w-full max-w-md rounded-2xl p-7 shadow-2xl"
                style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}
            >
                <div className="mb-5 flex items-start justify-between">
                    <h3
                        style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}
                        className="text-xl font-semibold text-white"
                    >
                        Editar serviço
                    </h3>
                    <button
                        onClick={onClose}
                        style={{ color: 'var(--text-3)' }}
                        className="hover:text-white transition-colors"
                    >
                        ✕
                    </button>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    {/* Nome */}
                    <div>
                        <label className="label mb-1">Nome *</label>
                        <input
                            value={data.nome}
                            onChange={e => setData('nome', e.target.value)}
                            className="input"
                            required
                        />
                        {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                    </div>

                    {/* Descrição */}
                    <div>
                        <label className="label mb-1">Descrição</label>
                        <textarea
                            value={data.descricao}
                            onChange={e => setData('descricao', e.target.value)}
                            rows={2}
                            className="input resize-none"
                        />
                        {errors.descricao && <p className="mt-1 text-xs text-red-400">{errors.descricao}</p>}
                    </div>

                    {/* Valores e duração */}
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="label mb-1">Valor mín</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.valor_min}
                                onChange={e => setData('valor_min', e.target.value)}
                                className="input"
                                placeholder="R$ 0,00"
                            />
                            {errors.valor_min && <p className="mt-1 text-xs text-red-400">{errors.valor_min}</p>}
                        </div>
                        <div>
                            <label className="label mb-1">Valor máx</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.valor_max}
                                onChange={e => setData('valor_max', e.target.value)}
                                className="input"
                                placeholder="R$ 0,00"
                            />
                            {errors.valor_max && <p className="mt-1 text-xs text-red-400">{errors.valor_max}</p>}
                        </div>
                        <div>
                            <label className="label mb-1">Duração *</label>
                            <select
                                value={data.duracao_minutos}
                                onChange={e => setData('duracao_minutos', parseInt(e.target.value))}
                                className="input"
                            >
                                {DURACOES.map(m => (
                                    <option key={m} value={m}>{m} min</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Requer avaliação */}
                    <label className="flex cursor-pointer items-center gap-3">
                        <div
                            onClick={() => setData('requer_avaliacao', !data.requer_avaliacao)}
                            className="relative flex h-5 w-9 cursor-pointer items-center rounded-full transition-colors"
                            style={{ background: data.requer_avaliacao ? 'var(--accent)' : 'rgba(255,255,255,0.15)' }}
                        >
                            <span
                                className={`absolute h-3.5 w-3.5 rounded-full bg-white shadow transition-transform ${
                                    data.requer_avaliacao ? 'translate-x-4' : 'translate-x-1'
                                }`}
                            />
                        </div>
                        <span className="text-sm" style={{ color: 'var(--text-2)' }}>
                            Requer avaliação prévia
                        </span>
                    </label>

                    {/* Status ativo */}
                    <div className="flex items-center gap-3">
                        <div
                            onClick={() => setData('ativo', !data.ativo)}
                            className="relative flex h-5 w-9 cursor-pointer items-center rounded-full transition-colors"
                            style={{ background: data.ativo ? 'var(--accent)' : 'rgba(255,255,255,0.15)' }}
                        >
                            <span
                                className={`absolute h-3.5 w-3.5 rounded-full bg-white shadow transition-transform ${
                                    data.ativo ? 'translate-x-4' : 'translate-x-1'
                                }`}
                            />
                        </div>
                        <span className="text-sm" style={{ color: 'var(--text-2)' }}>
                            {data.ativo ? 'Ativo' : 'Inativo'}
                        </span>
                    </div>

                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="btn-secondary">
                            Cancelar
                        </button>
                        <button type="submit" disabled={processing} className="btn-primary">
                            {processing ? 'Salvando…' : 'Salvar alterações'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────

export default function ServicosIndex({ servicos }: Props) {
    const [novoModal, setNovoModal] = useState(false);
    const [editando, setEditando] = useState<Servico | null>(null);

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

    const excluir = (id: number) => {
        if (confirm('Remover este serviço?')) {
            router.delete(route('tenant.servicos.destroy', id), { preserveScroll: true });
        }
    };

    return (
        <AppLayout title="Serviços">
            <Head title="Serviços" />

            <div className="mb-5 flex items-center justify-between">
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                    {servicos.length} serviço{servicos.length !== 1 ? 's' : ''} cadastrado{servicos.length !== 1 ? 's' : ''}
                </p>
                <button onClick={() => setNovoModal(true)} className="btn-primary">
                    + Novo serviço
                </button>
            </div>

            {/* Lista */}
            <div className="space-y-3">
                {servicos.length === 0 && (
                    <div className="card p-10 text-center" style={{ color: 'var(--text-3)' }}>
                        <p className="text-sm">Nenhum serviço cadastrado ainda.</p>
                        <p className="mt-1 text-xs">
                            Cadastre os serviços oferecidos pelo seu estabelecimento.
                        </p>
                    </div>
                )}

                {servicos.map(s => (
                    <div key={s.id} className="card overflow-hidden">
                        <div className="flex items-center justify-between px-6 py-4">
                            {/* Ícone + info */}
                            <div className="flex items-center gap-4">
                                <div
                                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                                    style={{
                                        background: s.ativo
                                            ? 'var(--accent)'
                                            : 'rgba(255,255,255,0.15)',
                                    }}
                                >
                                    {s.nome.charAt(0).toUpperCase()}
                                </div>

                                <div>
                                    <p className="font-semibold text-white">{s.nome}</p>

                                    {s.descricao && (
                                        <p
                                            className="mt-0.5 text-xs line-clamp-1"
                                            style={{ color: 'var(--text-3)' }}
                                        >
                                            {s.descricao}
                                        </p>
                                    )}

                                    <div
                                        className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs"
                                        style={{ color: 'var(--text-3)' }}
                                    >
                                        <span>{s.duracao_minutos} min</span>
                                        <span>{faixaValor(s)}</span>
                                        {s.requer_avaliacao && (
                                            <span
                                                className="rounded px-1.5 py-0.5"
                                                style={{
                                                    background: 'rgba(255,255,255,0.06)',
                                                    color: 'var(--text-3)',
                                                }}
                                            >
                                                Requer avaliação
                                            </span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Ações */}
                            <div className="flex items-center gap-2">
                                <span className={`badge ${s.ativo ? 'badge-green' : 'badge-gray'}`}>
                                    {s.ativo ? 'Ativo' : 'Inativo'}
                                </span>

                                <button
                                    onClick={() => setEditando(s)}
                                    className="btn-secondary py-1.5 text-xs"
                                >
                                    Editar
                                </button>

                                <button
                                    onClick={() => toggleAtivo(s)}
                                    className="btn-secondary py-1.5 text-xs"
                                >
                                    {s.ativo ? 'Desativar' : 'Ativar'}
                                </button>

                                <button
                                    onClick={() => excluir(s.id)}
                                    className="btn-danger py-1.5 text-xs"
                                >
                                    Excluir
                                </button>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {/* Modais */}
            {novoModal && <NovoServicoModal onClose={() => setNovoModal(false)} />}
            {editando && (
                <EditarServicoModal
                    servico={editando}
                    onClose={() => setEditando(null)}
                />
            )}
        </AppLayout>
    );
}
