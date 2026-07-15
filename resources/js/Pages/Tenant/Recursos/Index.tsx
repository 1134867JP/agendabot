import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfiguracoesLayout from '@/Layouts/ConfiguracoesLayout';
import { PageProps, Recurso, HorarioFuncionamento } from '@/types';
import Toggle from '@/Components/Toggle';
import { useConfirm } from '@/hooks/useConfirm';

interface Props extends PageProps {
    recursos: Recurso[];
}

const DIAS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

interface HorarioRow {
    dia_semana: number;
    abertura: string;
    fechamento: string;
    ativo: boolean;
}

function buildRows(horarios: HorarioFuncionamento[]): HorarioRow[] {
    return DIAS.map((_, i) => {
        const h = horarios.find(h => h.dia_semana === i);
        return { dia_semana: i, abertura: h?.abertura ?? '09:00', fechamento: h?.fechamento ?? '18:00', ativo: !!h };
    });
}

// ─── Horários editor ──────────────────────────────────────────────────────────

function HorariosEditor({ recurso, onClose }: { recurso: Recurso; onClose: () => void }) {
    const [rows, setRows] = useState<HorarioRow[]>(buildRows(recurso.horarios_funcionamento ?? []));
    const [saving, setSaving] = useState(false);

    const toggle = (i: number) =>
        setRows(r => r.map((row, idx) => idx === i ? { ...row, ativo: !row.ativo } : row));
    const setHora = (i: number, field: 'abertura' | 'fechamento', val: string) =>
        setRows(r => r.map((row, idx) => idx === i ? { ...row, [field]: val } : row));

    const salvar = () => {
        setSaving(true);
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        router.post(route('tenant.horarios.sync', recurso.id), { horarios: rows } as any, {
            onSuccess: onClose,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div className="px-6 pb-5 pt-4" style={{ borderTop: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
            <h4 className="mb-4 text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                Horários de funcionamento
            </h4>
            <div className="space-y-2.5">
                {rows.map((row, i) => (
                    <div key={i} className="flex items-center gap-4">
                        <div className="w-28">
                            <Toggle checked={row.ativo} onChange={() => toggle(i)} label={DIAS[i].slice(0, 3)} />
                        </div>
                        <input type="time" value={row.abertura} onChange={e => setHora(i, 'abertura', e.target.value)} disabled={!row.ativo} className="input w-28 disabled:opacity-40" />
                        <span className="text-xs" style={{ color: 'var(--text-3)' }}>até</span>
                        <input type="time" value={row.fechamento} onChange={e => setHora(i, 'fechamento', e.target.value)} disabled={!row.ativo} className="input w-28 disabled:opacity-40" />
                    </div>
                ))}
            </div>
            <div className="mt-5 flex gap-2">
                <button onClick={salvar} disabled={saving} className="btn-primary">{saving ? 'Salvando…' : 'Salvar horários'}</button>
                <button onClick={onClose} className="btn-secondary">Fechar</button>
            </div>
        </div>
    );
}

// ─── Formulário novo recurso (inline) ────────────────────────────────────────

function NovoRecursoForm({ onClose }: { onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        nome: '',
        descricao: '',
        valor_hora: '',
        duracao_padrao_minutos: '60',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.recursos.store'), {
            onSuccess: () => { reset(); onClose(); },
            preserveScroll: true,
        });
    };

    return (
        <div className="card overflow-hidden" style={{ borderColor: 'var(--accent)' }}>
            <div className="px-6 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                <h3 className="font-semibold text-primary">Novo recurso</h3>
            </div>
            <form onSubmit={submit} className="space-y-4 px-6 py-5">
                <div>
                    <label className="label mb-1" htmlFor="novo-recurso-nome">Nome *</label>
                    <input
                        id="novo-recurso-nome"
                        autoFocus
                        value={data.nome}
                        onChange={e => setData('nome', e.target.value)}
                        className="input"
                        placeholder="Ex: Barbeiro João"
                        required
                    />
                    {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                </div>
                <div>
                    <label className="label mb-1">Descrição</label>
                    <textarea value={data.descricao} onChange={e => setData('descricao', e.target.value)} rows={2} className="input" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label mb-1">Valor/hora (R$)</label>
                        <input type="number" step="0.01" value={data.valor_hora} onChange={e => setData('valor_hora', e.target.value)} className="input" placeholder="60.00" />
                    </div>
                    <div>
                        <label className="label mb-1">Duração (min) *</label>
                        <input type="number" value={data.duracao_padrao_minutos} onChange={e => setData('duracao_padrao_minutos', e.target.value)} className="input" required />
                    </div>
                </div>
                <div className="flex gap-2 pt-1">
                    <button type="submit" disabled={processing} className="btn-primary">
                        {processing ? 'Criando…' : 'Criar recurso'}
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    );
}

// ─── Formulário editar recurso (inline) ──────────────────────────────────────

function EditarRecursoForm({ recurso, onClose }: { recurso: Recurso; onClose: () => void }) {
    const { data, setData, patch, processing, errors } = useForm({
        nome: recurso.nome,
        descricao: recurso.descricao ?? '',
        valor_hora: recurso.valor_hora?.toString() ?? '',
        duracao_padrao_minutos: recurso.duracao_padrao_minutos?.toString() ?? '60',
        ativo: recurso.ativo,
    });
    const { confirm, modal: confirmModal } = useConfirm();

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        patch(route('tenant.recursos.update', recurso.id), {
            onSuccess: onClose,
            preserveScroll: true,
        });
    };

    const excluir = async () => {
        const ok = await confirm({
            title: 'Desativar recurso',
            message: `Desativar "${recurso.nome}"? Ele não aceitará novos agendamentos.`,
            confirmLabel: 'Desativar',
            variant: 'warning',
        });
        if (ok) router.delete(route('tenant.recursos.destroy', recurso.id), { preserveScroll: true });
    };

    return (
        <div className="card overflow-hidden" style={{ borderColor: 'var(--accent)' }}>
            <div className="px-6 py-4" style={{ borderBottom: '1px solid var(--border)' }}>
                <h3 className="font-semibold text-primary">Editar recurso</h3>
            </div>
            <form onSubmit={submit} className="space-y-4 px-6 py-5">
                <div>
                    <label className="label mb-1" htmlFor="editar-recurso-nome">Nome *</label>
                    <input
                        id="editar-recurso-nome"
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
                    <textarea value={data.descricao} onChange={e => setData('descricao', e.target.value)} rows={2} className="input" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <label className="label mb-1">Valor/hora (R$)</label>
                        <input type="number" step="0.01" value={data.valor_hora} onChange={e => setData('valor_hora', e.target.value)} className="input" placeholder="60.00" />
                    </div>
                    <div>
                        <label className="label mb-1">Duração (min) *</label>
                        <input type="number" value={data.duracao_padrao_minutos} onChange={e => setData('duracao_padrao_minutos', e.target.value)} className="input" required />
                    </div>
                </div>

                <Toggle checked={data.ativo} onChange={v => setData('ativo', v)} label={data.ativo ? 'Ativo' : 'Inativo'} />

                <div className="flex items-center justify-between pt-1">
                    <button type="button" onClick={excluir} className="text-xs font-medium transition-opacity hover:opacity-70" style={{ color: '#f87171' }}>
                        Desativar recurso
                    </button>
                    <div className="flex gap-2">
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                        <button type="submit" disabled={processing} className="btn-primary">
                            {processing ? 'Salvando…' : 'Salvar'}
                        </button>
                    </div>
                </div>
            </form>
            {confirmModal}
        </div>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────

export default function RecursosIndex({ recursos }: Props) {
    const [novoAberto, setNovoAberto] = useState(false);
    const [editando, setEditando] = useState<number | null>(null);
    const [expandido, setExpandido] = useState<number | null>(null);

    const toggleAtivo = (r: Recurso) => {
        router.patch(route('tenant.recursos.update', r.id), {
            nome: r.nome,
            descricao: r.descricao ?? '',
            valor_hora: r.valor_hora,
            duracao_padrao_minutos: r.duracao_padrao_minutos,
            ativo: !r.ativo,
        });
    };

    return (
        <ConfiguracoesLayout title="Recursos" subtitle="O que pode ser agendado e seus horários de funcionamento">
            <Head title="Recursos" />

            <div className="mb-5 flex items-center justify-between">
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                    {recursos.length} recurso{recursos.length !== 1 ? 's' : ''} cadastrado{recursos.length !== 1 ? 's' : ''}
                </p>
                {!novoAberto && (
                    <button onClick={() => { setNovoAberto(true); setEditando(null); }} className="btn-primary">
                        + Novo recurso
                    </button>
                )}
            </div>

            <div className="space-y-3">
                {novoAberto && (
                    <NovoRecursoForm onClose={() => setNovoAberto(false)} />
                )}

                {recursos.length === 0 && !novoAberto && (
                    <div className="card p-10 text-center" style={{ color: 'var(--text-3)' }}>
                        <p className="text-sm">Nenhum recurso cadastrado ainda.</p>
                        <p className="mt-1 text-xs">Crie seus barbeiros, quadras ou serviços.</p>
                    </div>
                )}

                {recursos.map(r => (
                    <div key={r.id} className="card overflow-hidden">
                        {editando === r.id ? (
                            <EditarRecursoForm recurso={r} onClose={() => setEditando(null)} />
                        ) : (
                            <>
                                <div className="flex items-center justify-between px-6 py-4">
                                    <div className="flex items-center gap-4">
                                        <div
                                            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-primary"
                                            style={{ background: r.ativo ? 'var(--accent)' : 'rgba(255,255,255,0.15)' }}
                                        >
                                            {r.nome.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="font-semibold text-primary">{r.nome}</p>
                                            {r.descricao && <p className="text-xs" style={{ color: 'var(--text-3)' }}>{r.descricao}</p>}
                                            <div className="mt-1 flex flex-wrap gap-3">
                                                {r.valor_hora > 0 && (
                                                    <span className="text-xs" style={{ color: 'var(--text-3)' }}>
                                                        {Number(r.valor_hora).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}/h
                                                    </span>
                                                )}
                                                <span className="text-xs" style={{ color: 'var(--text-3)' }}>{r.duracao_padrao_minutos} min/slot</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        <span className={`badge ${r.ativo ? 'badge-green' : 'badge-gray'}`}>
                                            {r.ativo ? 'Ativo' : 'Inativo'}
                                        </span>
                                        <button
                                            onClick={() => setExpandido(expandido === r.id ? null : r.id)}
                                            className="btn-secondary py-1.5 text-xs"
                                        >
                                            {expandido === r.id ? 'Fechar' : 'Horários'}
                                        </button>
                                        <button
                                            onClick={() => { setEditando(r.id); setNovoAberto(false); setExpandido(null); }}
                                            className="btn-secondary py-1.5 text-xs"
                                        >
                                            Editar
                                        </button>
                                        <button onClick={() => toggleAtivo(r)} className="btn-secondary py-1.5 text-xs">
                                            {r.ativo ? 'Desativar' : 'Ativar'}
                                        </button>
                                    </div>
                                </div>

                                {expandido === r.id && (
                                    <HorariosEditor recurso={r} onClose={() => setExpandido(null)} />
                                )}
                            </>
                        )}
                    </div>
                ))}
            </div>
        </ConfiguracoesLayout>
    );
}
