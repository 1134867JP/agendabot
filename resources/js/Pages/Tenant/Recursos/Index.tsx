import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Recurso, HorarioFuncionamento } from '@/types';

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
        <div className="px-6 pb-5 pt-4" style={{ borderTop: '1px solid var(--border)', background: 'rgba(255,255,255,0.02)' }}>
            <h4 className="mb-4 text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                Horários de funcionamento
            </h4>
            <div className="space-y-2.5">
                {rows.map((row, i) => (
                    <div key={i} className="flex items-center gap-4">
                        <label className="flex w-28 cursor-pointer items-center gap-2.5">
                            <div
                                onClick={() => toggle(i)}
                                className="relative flex h-5 w-9 cursor-pointer items-center rounded-full transition-colors"
                                style={{ background: row.ativo ? 'var(--accent)' : 'rgba(255,255,255,0.15)' }}
                            >
                                <span className={`absolute h-3.5 w-3.5 rounded-full bg-white shadow transition-transform ${row.ativo ? 'translate-x-4' : 'translate-x-1'}`} />
                            </div>
                            <span className="text-sm" style={{ color: row.ativo ? 'var(--text-1)' : 'var(--text-3)' }}>
                                {DIAS[i].slice(0, 3)}
                            </span>
                        </label>
                        <input
                            type="time"
                            value={row.abertura}
                            onChange={e => setHora(i, 'abertura', e.target.value)}
                            disabled={!row.ativo}
                            className="input w-28 disabled:opacity-40"
                        />
                        <span className="text-xs" style={{ color: 'var(--text-3)' }}>até</span>
                        <input
                            type="time"
                            value={row.fechamento}
                            onChange={e => setHora(i, 'fechamento', e.target.value)}
                            disabled={!row.ativo}
                            className="input w-28 disabled:opacity-40"
                        />
                    </div>
                ))}
            </div>
            <div className="mt-5 flex gap-2">
                <button onClick={salvar} disabled={saving} className="btn-primary">
                    {saving ? 'Salvando…' : 'Salvar horários'}
                </button>
                <button onClick={onClose} className="btn-secondary">Fechar</button>
            </div>
        </div>
    );
}

// ─── Novo recurso modal ───────────────────────────────────────────────────────

function NovoRecursoModal({ onClose }: { onClose: () => void }) {
    const { data, setData, post, processing, errors } = useForm({
        nome: '',
        descricao: '',
        valor_hora: '',
        duracao_padrao_minutos: '60',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.recursos.store'), { onSuccess: onClose });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
            <div className="w-full max-w-sm rounded-2xl p-7 shadow-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                <div className="mb-5 flex items-start justify-between">
                    <h3 style={{ fontFamily: 'Instrument Serif, Georgia, serif' }} className="text-xl font-semibold text-white">
                        Novo recurso
                    </h3>
                    <button onClick={onClose} style={{ color: 'var(--text-3)' }} className="hover:text-white transition-colors">✕</button>
                </div>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="label mb-1">Nome *</label>
                        <input value={data.nome} onChange={e => setData('nome', e.target.value)} className="input" placeholder="Ex: Barbeiro João" required />
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
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                        <button type="submit" disabled={processing} className="btn-primary">
                            {processing ? 'Criando…' : 'Criar recurso'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ─── Main ─────────────────────────────────────────────────────────────────────

export default function RecursosIndex({ recursos }: Props) {
    const [novoModal, setNovoModal]   = useState(false);
    const [expandido, setExpandido]   = useState<number | null>(null);

    const toggleAtivo = (r: Recurso) => {
        router.patch(route('tenant.recursos.update', r.id), {
            nome: r.nome,
            descricao: r.descricao ?? '',
            valor_hora: r.valor_hora,
            duracao_padrao_minutos: r.duracao_padrao_minutos,
            ativo: !r.ativo,
        });
    };

    const excluir = (id: number) => {
        if (confirm('Remover este recurso?')) router.delete(route('tenant.recursos.destroy', id));
    };

    return (
        <AppLayout title="Recursos">
            <Head title="Recursos" />

            <div className="mb-5 flex items-center justify-between">
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                    {recursos.length} recurso{recursos.length !== 1 ? 's' : ''} cadastrado{recursos.length !== 1 ? 's' : ''}
                </p>
                <button onClick={() => setNovoModal(true)} className="btn-primary">
                    + Novo recurso
                </button>
            </div>

            <div className="space-y-3">
                {recursos.length === 0 && (
                    <div className="card p-10 text-center" style={{ color: 'var(--text-3)' }}>
                        <p className="text-sm">Nenhum recurso cadastrado ainda.</p>
                        <p className="mt-1 text-xs">Crie seus barbeiros, quadras ou serviços.</p>
                    </div>
                )}

                {recursos.map(r => (
                    <div key={r.id} className="card overflow-hidden">
                        <div className="flex items-center justify-between px-6 py-4">
                            <div className="flex items-center gap-4">
                                <div
                                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white"
                                    style={{ background: r.ativo ? 'var(--accent)' : 'rgba(255,255,255,0.15)' }}
                                >
                                    {r.nome.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <p className="font-semibold text-white">{r.nome}</p>
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
                                <button onClick={() => toggleAtivo(r)} className="btn-secondary py-1.5 text-xs">
                                    {r.ativo ? 'Desativar' : 'Ativar'}
                                </button>
                                <button onClick={() => excluir(r.id)} className="btn-danger py-1.5 text-xs">
                                    Excluir
                                </button>
                            </div>
                        </div>

                        {expandido === r.id && (
                            <HorariosEditor recurso={r} onClose={() => setExpandido(null)} />
                        )}
                    </div>
                ))}
            </div>

            {novoModal && <NovoRecursoModal onClose={() => setNovoModal(false)} />}
        </AppLayout>
    );
}
