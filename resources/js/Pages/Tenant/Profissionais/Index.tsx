import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfiguracoesLayout from '@/Layouts/ConfiguracoesLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { PageProps } from '@/types';
import Toggle from '@/Components/Toggle';

// ─── Types ───────────────────────────────────────────────────────────────────

interface HorarioProfissional {
    id?: number;
    profissional_id?: number;
    dia_semana: number;
    hora_inicio: string;
    hora_fim: string;
    duracao_slot: number;
    ativo: boolean;
}

interface Profissional {
    id: number;
    nome: string;
    especialidades: string[] | null;
    ativo: boolean;
    horarios: HorarioProfissional[];
}

interface Props extends PageProps {
    profissionais: Profissional[];
}

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const DURACOES = [15, 20, 30, 45, 60, 90, 120];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function buildHorariosRows(horarios: HorarioProfissional[]): HorarioProfissional[] {
    return DIAS.map((_, i) => {
        const h = horarios.find(h => h.dia_semana === i);
        if (h) return { ...h, ativo: true };
        return { dia_semana: i, hora_inicio: '09:00', hora_fim: '18:00', duracao_slot: 30, ativo: false };
    });
}

// ─── EspecialidadesField ──────────────────────────────────────────────────────

function EspecialidadesField({
    value,
    onChange,
}: {
    value: string[];
    onChange: (v: string[]) => void;
}) {
    const [novaEsp, setNovaEsp] = useState('');

    const add = () => {
        const trimmed = novaEsp.trim();
        if (!trimmed) return;
        onChange([...value, trimmed]);
        setNovaEsp('');
    };

    return (
        <div>
            <label className="label mb-1">Especialidades</label>
            <div className="flex gap-2">
                <input
                    value={novaEsp}
                    onChange={e => setNovaEsp(e.target.value)}
                    onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); add(); } }}
                    className="input flex-1"
                    placeholder="Ex: Corte masculino"
                />
                <button type="button" onClick={add} className="btn-secondary px-3">+</button>
            </div>
            {value.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1.5">
                    {value.map((esp, i) => (
                        <span key={i} className="flex items-center gap-1 rounded px-2 py-0.5 text-xs" style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}>
                            {esp}
                            <button type="button" onClick={() => onChange(value.filter((_, j) => j !== i))} className="transition-opacity hover:opacity-70">×</button>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

// ─── HorariosEditor ──────────────────────────────────────────────────────────

function HorariosEditor({ profissional, onClose }: { profissional: Profissional; onClose: () => void }) {
    const [rows, setRows] = useState<HorarioProfissional[]>(buildHorariosRows(profissional.horarios ?? []));
    const [saving, setSaving] = useState(false);

    const toggle = (idx: number) =>
        setRows(r => r.map((row, i) => i === idx ? { ...row, ativo: !row.ativo } : row));
    const setField = (idx: number, field: 'hora_inicio' | 'hora_fim' | 'duracao_slot', value: string | number) =>
        setRows(r => r.map((row, i) => i === idx ? { ...row, [field]: value } : row));

    const salvar = () => {
        setSaving(true);
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        router.post(route('tenant.profissionais.horarios.sync', profissional.id), { horarios: rows } as any, {
            onSuccess: onClose,
            onFinish: () => setSaving(false),
            preserveScroll: true,
        });
    };

    return (
        <div className="px-4 pb-5 pt-4 sm:px-6" style={{ borderTop: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
            <h4 className="mb-4 text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                Horários de atendimento
            </h4>
            <div className="space-y-2.5">
                {rows.map((row, i) => (
                    <div key={i} className="grid grid-cols-2 items-center gap-2 rounded-lg p-2 sm:flex sm:flex-wrap sm:gap-4">
                        <div className="col-span-2 sm:col-span-1 sm:w-28">
                            <Toggle checked={row.ativo} onChange={() => toggle(i)} label={DIAS[i]} />
                        </div>
                        <input type="time" value={row.hora_inicio} onChange={e => setField(i, 'hora_inicio', e.target.value)} disabled={!row.ativo} className="input w-full min-w-0 disabled:opacity-40 sm:w-28" />
                        <span className="hidden text-xs sm:inline" style={{ color: 'var(--text-3)' }}>até</span>
                        <input type="time" value={row.hora_fim} onChange={e => setField(i, 'hora_fim', e.target.value)} disabled={!row.ativo} className="input w-full min-w-0 disabled:opacity-40 sm:w-28" />
                        <select value={row.duracao_slot} onChange={e => setField(i, 'duracao_slot', parseInt(e.target.value))} disabled={!row.ativo} className="input w-full min-w-0 disabled:opacity-40 sm:w-28">
                            {DURACOES.map(m => <option key={m} value={m}>{m} min</option>)}
                        </select>
                    </div>
                ))}
            </div>
            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row">
                <button onClick={salvar} disabled={saving} className="btn-primary">{saving ? 'Salvando…' : 'Salvar horários'}</button>
                <button onClick={onClose} className="btn-secondary">Fechar</button>
            </div>
        </div>
    );
}

// ─── Formulário novo profissional (inline) ────────────────────────────────────

function NovoProfissionalForm({ onClose }: { onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        nome: '',
        especialidades: [] as string[],
        ativo: true,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('tenant.profissionais.store'), {
            onSuccess: () => { reset(); onClose(); },
            preserveScroll: true,
        });
    };

    return (
        <div className="card overflow-hidden" style={{ borderColor: 'var(--accent)' }}>
            <div className="px-4 py-4 sm:px-6" style={{ borderBottom: '1px solid var(--border)' }}>
                <h3 className="font-semibold text-primary">Novo profissional</h3>
            </div>
            <form onSubmit={submit} className="space-y-4 px-4 py-5 sm:px-6">
                <div>
                    <label className="label mb-1" htmlFor="novo-prof-nome">Nome *</label>
                    <input
                        id="novo-prof-nome"
                        autoFocus
                        value={data.nome}
                        onChange={e => setData('nome', e.target.value)}
                        className="input"
                        placeholder="Ex: Dr. Ana, Barbeiro Carlos"
                        required
                    />
                    {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                </div>

                <EspecialidadesField value={data.especialidades} onChange={v => setData('especialidades', v)} />

                <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row">
                    <button type="submit" disabled={processing} className="btn-primary">
                        {processing ? 'Criando…' : 'Criar profissional'}
                    </button>
                    <button type="button" onClick={onClose} className="btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    );
}

// ─── Formulário editar profissional (inline) ──────────────────────────────────

function EditarProfissionalForm({ profissional, onClose }: { profissional: Profissional; onClose: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        nome: profissional.nome,
        especialidades: profissional.especialidades ?? [],
        ativo: profissional.ativo,
    });
    const { confirm, modal: confirmModal } = useConfirm();

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('tenant.profissionais.update', profissional.id), {
            onSuccess: onClose,
            preserveScroll: true,
        });
    };

    const excluir = async () => {
        const ok = await confirm({ title: 'Desativar profissional', message: `Desativar "${profissional.nome}"? Ele não aceitará novos agendamentos.`, confirmLabel: 'Desativar', variant: 'warning' });
        if (ok) router.delete(route('tenant.profissionais.destroy', profissional.id), { preserveScroll: true });
    };

    return (
        <>
        {confirmModal}
        <div className="card overflow-hidden" style={{ borderColor: 'var(--accent)' }}>
            <div className="px-4 py-4 sm:px-6" style={{ borderBottom: '1px solid var(--border)' }}>
                <h3 className="font-semibold text-primary">Editar profissional</h3>
            </div>
            <form onSubmit={submit} className="space-y-4 px-4 py-5 sm:px-6">
                <div>
                    <label className="label mb-1" htmlFor="editar-prof-nome">Nome *</label>
                    <input
                        id="editar-prof-nome"
                        autoFocus
                        value={data.nome}
                        onChange={e => setData('nome', e.target.value)}
                        className="input"
                        required
                    />
                    {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                </div>

                <EspecialidadesField value={data.especialidades} onChange={v => setData('especialidades', v)} />

                <Toggle checked={data.ativo} onChange={v => setData('ativo', v)} label={data.ativo ? 'Ativo' : 'Inativo'} />

                <div className="flex flex-col items-stretch gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
                    <button type="button" onClick={excluir} className="text-xs font-medium transition-opacity hover:opacity-70" style={{ color: '#f87171' }}>
                        Excluir profissional
                    </button>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row">
                        <button type="button" onClick={onClose} className="btn-secondary justify-center">Cancelar</button>
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

export default function ProfissionaisIndex({ profissionais }: Props) {
    const [novoAberto, setNovoAberto] = useState(false);
    const [editando, setEditando] = useState<number | null>(null);
    const [expandido, setExpandido] = useState<number | null>(null);

    const toggleAtivo = (p: Profissional) => {
        router.put(
            route('tenant.profissionais.update', p.id),
            { nome: p.nome, especialidades: p.especialidades ?? [], ativo: !p.ativo },
            { preserveScroll: true }
        );
    };

    return (
        <ConfiguracoesLayout title="Profissionais" subtitle="Sua equipe, especialidades e horários de atendimento">
            <Head title="Profissionais" />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                    {profissionais.length} profissional{profissionais.length !== 1 ? 'is' : ''} cadastrado{profissionais.length !== 1 ? 's' : ''}
                </p>
                {!novoAberto && (
                    <button onClick={() => { setNovoAberto(true); setEditando(null); }} className="btn-primary">
                        + Novo profissional
                    </button>
                )}
            </div>

            <div className="space-y-3">
                {/* Formulário novo profissional inline */}
                {novoAberto && (
                    <NovoProfissionalForm onClose={() => setNovoAberto(false)} />
                )}

                {profissionais.length === 0 && !novoAberto && (
                    <div className="card flex flex-col items-center gap-3 p-8 text-center sm:p-10" style={{ color: 'var(--text-3)' }}>
                        <div>
                            <p className="text-sm font-medium text-primary">Cadastre seu primeiro profissional</p>
                            <p className="mt-1 text-xs">Defina quem atende, especialidades e horários disponíveis.</p>
                        </div>
                        <button type="button" onClick={() => setNovoAberto(true)} className="btn-primary min-h-11">Criar profissional</button>
                    </div>
                )}

                {profissionais.map(p => (
                    <div key={p.id} className="card overflow-hidden">
                        {editando === p.id ? (
                            <EditarProfissionalForm profissional={p} onClose={() => setEditando(null)} />
                        ) : (
                            <>
                                <div className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                    <div className="flex items-center gap-4">
                                        <div
                                            className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold text-primary"
                                            style={{ background: p.ativo ? 'var(--accent)' : 'rgba(255,255,255,0.15)' }}
                                        >
                                            {p.nome.charAt(0).toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="font-semibold text-primary">{p.nome}</p>
                                            {p.especialidades && p.especialidades.length > 0 && (
                                                <div className="mt-1 flex flex-wrap gap-1">
                                                    {p.especialidades.map((esp, i) => (
                                                        <span key={i} className="rounded px-1.5 py-0.5 text-[11px]" style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-3)' }}>
                                                            {esp}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                            <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                                {p.horarios.length > 0
                                                    ? `${p.horarios.length} dia${p.horarios.length !== 1 ? 's' : ''} configurado${p.horarios.length !== 1 ? 's' : ''}`
                                                    : 'Sem horários definidos'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="grid w-full grid-cols-2 gap-2 pl-14 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:pl-0">
                                        <span className={`badge ${p.ativo ? 'badge-green' : 'badge-gray'}`}>
                                            {p.ativo ? 'Ativo' : 'Inativo'}
                                        </span>
                                        <button
                                            onClick={() => setExpandido(expandido === p.id ? null : p.id)}
                                            className="btn-secondary min-h-10 justify-center py-1.5 text-xs"
                                        >
                                            {expandido === p.id ? 'Fechar' : 'Horários'}
                                        </button>
                                        <button
                                            onClick={() => { setEditando(p.id); setNovoAberto(false); setExpandido(null); }}
                                            className="btn-secondary min-h-10 justify-center py-1.5 text-xs"
                                        >
                                            Editar
                                        </button>
                                        <button onClick={() => toggleAtivo(p)} className="btn-secondary min-h-10 justify-center py-1.5 text-xs">
                                            {p.ativo ? 'Desativar' : 'Ativar'}
                                        </button>
                                    </div>
                                </div>

                                {expandido === p.id && (
                                    <HorariosEditor profissional={p} onClose={() => setExpandido(null)} />
                                )}
                            </>
                        )}
                    </div>
                ))}
            </div>
        </ConfiguracoesLayout>
    );
}
