import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfiguracoesLayout from '@/Layouts/ConfiguracoesLayout';
import { useConfirm } from '@/hooks/useConfirm';
import { PageProps } from '@/types';
import Toggle from '@/Components/Toggle';
import EmptyState from '@/Components/UI/EmptyState';
import StatusBadge from '@/Components/UI/StatusBadge';
import Toolbar from '@/Components/UI/Toolbar';

// ─── Types ───────────────────────────────────────────────────────────────────

interface HorarioProfissional {
    id?: number;
    profissional_id?: number;
    dia_semana: number;
    hora_inicio: string;
    hora_fim: string;
    intervalo_inicio: string | null;
    intervalo_fim: string | null;
    duracao_slot: number;
    ativo: boolean;
}

interface ServicoVinculado {
    id: number;
    nome: string;
    pivot: { valor: string | number | null; duracao_minutos: number | null };
}

interface Profissional {
    id: number;
    nome: string;
    especialidades: string[] | null;
    ativo: boolean;
    horarios: HorarioProfissional[];
    servicos: ServicoVinculado[];
}

interface ServicoCatalogo {
    id: number;
    nome: string;
    valor_min: string | number | null;
    valor_max: string | number | null;
    duracao_minutos: number;
}

interface Props extends PageProps {
    profissionais: Profissional[];
    servicos_disponiveis: ServicoCatalogo[];
}

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const DURACOES = [15, 20, 30, 45, 60, 90, 120];

// ─── Helpers ─────────────────────────────────────────────────────────────────

// O banco devolve "HH:MM:SS"; o backend valida "HH:MM".
const hhmm = (hora: string | null | undefined) => (hora ? hora.slice(0, 5) : null);

function buildHorariosRows(horarios: HorarioProfissional[]): HorarioProfissional[] {
    return DIAS.map((_, i) => {
        const h = horarios.find(h => h.dia_semana === i);
        if (h) {
            return {
                ...h,
                hora_inicio: hhmm(h.hora_inicio) ?? '09:00',
                hora_fim: hhmm(h.hora_fim) ?? '18:00',
                intervalo_inicio: hhmm(h.intervalo_inicio),
                intervalo_fim: hhmm(h.intervalo_fim),
                ativo: true,
            };
        }
        return { dia_semana: i, hora_inicio: '09:00', hora_fim: '18:00', intervalo_inicio: null, intervalo_fim: null, duracao_slot: 30, ativo: false };
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
            <label className="label mb-1" htmlFor="nova-especialidade">Especialidades</label>
            <div className="flex gap-2">
                <input
                    id="nova-especialidade"
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
                        <span key={i} className="flex items-center gap-1 rounded-sm px-2 py-0.5 text-xs" style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}>
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
    const [erro, setErro] = useState<string | null>(null);

    const toggle = (idx: number) =>
        setRows(r => r.map((row, i) => i === idx ? { ...row, ativo: !row.ativo } : row));
    const setField = (idx: number, field: 'hora_inicio' | 'hora_fim' | 'intervalo_inicio' | 'intervalo_fim' | 'duracao_slot', value: string | number | null) =>
        setRows(r => r.map((row, i) => i === idx ? { ...row, [field]: value } : row));
    const toggleIntervalo = (idx: number) =>
        setRows(r => r.map((row, i) => {
            if (i !== idx) return row;
            return row.intervalo_inicio
                ? { ...row, intervalo_inicio: null, intervalo_fim: null }
                : { ...row, intervalo_inicio: '12:00', intervalo_fim: '13:00' };
        }));
    const aplicarHorarioComercial = () => setRows(r => r.map((row, i) => ({
        ...row,
        ativo: i >= 1 && i <= 5,
        hora_inicio: '09:00',
        hora_fim: '18:00',
        intervalo_inicio: '12:00',
        intervalo_fim: '13:00',
        duracao_slot: 30,
    })));

    const salvar = () => {
        const invalido = rows.find(row => row.ativo && row.hora_fim <= row.hora_inicio);
        if (invalido) {
            setErro('O horário de término precisa ser posterior ao início nos dias ativos.');
            return;
        }
        const intervaloInvalido = rows.find(row => row.ativo && row.intervalo_inicio && row.intervalo_fim && (
            row.intervalo_fim <= row.intervalo_inicio
            || row.intervalo_inicio <= row.hora_inicio
            || row.intervalo_fim >= row.hora_fim
        ));
        if (intervaloInvalido) {
            setErro(`O intervalo de ${DIAS[intervaloInvalido.dia_semana]} precisa ficar dentro do expediente.`);
            return;
        }

        setErro(null);
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
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h4 className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                        Horários de atendimento
                    </h4>
                    <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                        Ative os dias, defina o expediente e, se houver, o intervalo de almoço. O bot não oferece horários durante o intervalo.
                    </p>
                </div>
                <button type="button" onClick={aplicarHorarioComercial} className="btn-secondary min-h-10 justify-center text-xs">
                    Usar seg–sex, 9h–18h, almoço 12h–13h
                </button>
            </div>
            <div className="space-y-2.5">
                {rows.map((row, i) => (
                    <div key={i} className="grid grid-cols-2 items-end gap-2 rounded-lg p-3 sm:grid-cols-[112px_1fr_1fr_1fr] sm:gap-3">
                        <div className="col-span-2 self-center sm:col-span-1">
                            <Toggle checked={row.ativo} onChange={() => toggle(i)} label={DIAS[i]} />
                        </div>
                        <label>
                            <span className="label mb-1">Início</span>
                            <input type="time" value={row.hora_inicio} onChange={e => setField(i, 'hora_inicio', e.target.value)} disabled={!row.ativo} className="input w-full min-w-0 disabled:opacity-40" />
                        </label>
                        <label>
                            <span className="label mb-1">Fim</span>
                            <input type="time" value={row.hora_fim} onChange={e => setField(i, 'hora_fim', e.target.value)} disabled={!row.ativo} className="input w-full min-w-0 disabled:opacity-40" />
                        </label>
                        <label className="col-span-2 sm:col-span-1">
                            <span className="label mb-1">Horários a cada</span>
                            <select value={row.duracao_slot} onChange={e => setField(i, 'duracao_slot', parseInt(e.target.value))} disabled={!row.ativo} className="input w-full min-w-0 disabled:opacity-40">
                                {DURACOES.map(m => <option key={m} value={m}>{m} min</option>)}
                            </select>
                        </label>
                        <div className="col-span-2 flex flex-wrap items-center gap-2 sm:col-span-3 sm:col-start-2">
                            <label className="flex items-center gap-2 text-xs" style={{ color: 'var(--text-2)' }}>
                                <input type="checkbox" checked={!!row.intervalo_inicio} onChange={() => toggleIntervalo(i)} disabled={!row.ativo} />
                                Intervalo (almoço)
                            </label>
                            {row.intervalo_inicio && (
                                <>
                                    <input type="time" aria-label={`Início do intervalo de ${DIAS[i]}`} value={row.intervalo_inicio ?? ''} onChange={e => setField(i, 'intervalo_inicio', e.target.value)} disabled={!row.ativo} className="input w-32 min-w-0 disabled:opacity-40" />
                                    <span className="text-xs" style={{ color: 'var(--text-3)' }}>até</span>
                                    <input type="time" aria-label={`Fim do intervalo de ${DIAS[i]}`} value={row.intervalo_fim ?? ''} onChange={e => setField(i, 'intervalo_fim', e.target.value)} disabled={!row.ativo} className="input w-32 min-w-0 disabled:opacity-40" />
                                </>
                            )}
                        </div>
                    </div>
                ))}
            </div>
            {erro && (
                <p role="alert" className="mt-3 rounded-lg px-3 py-2 text-sm" style={{ background: 'var(--danger-btn-bg)', color: 'var(--danger-text)' }}>
                    {erro}
                </p>
            )}
            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row">
                <button onClick={salvar} disabled={saving} className="btn-primary">{saving ? 'Salvando…' : 'Salvar horários'}</button>
                <button onClick={onClose} className="btn-secondary">Fechar</button>
            </div>
        </div>
    );
}

// ─── ServicosEditor ──────────────────────────────────────────────────────────

const moeda = (v: string | number | null | undefined) =>
    v === null || v === undefined || v === '' ? null : Number(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function precoPadrao(servico: ServicoCatalogo): string {
    const min = moeda(servico.valor_min);
    const max = moeda(servico.valor_max);
    if (!min) return 'sem preço';
    return max && max !== min ? `${min} a ${max}` : min;
}

interface LinhaServico { servico_id: number; ativo: boolean; valor: string; duracao_minutos: string }

function ServicosEditor({ profissional, catalogo, onClose }: { profissional: Profissional; catalogo: ServicoCatalogo[]; onClose: () => void }) {
    const [linhas, setLinhas] = useState<LinhaServico[]>(() => catalogo.map(servico => {
        const vinculo = profissional.servicos?.find(v => v.id === servico.id);
        return {
            servico_id: servico.id,
            ativo: !!vinculo,
            valor: vinculo?.pivot.valor !== null && vinculo?.pivot.valor !== undefined ? String(Number(vinculo.pivot.valor)) : '',
            duracao_minutos: vinculo?.pivot.duracao_minutos ? String(vinculo.pivot.duracao_minutos) : '',
        };
    }));
    const [saving, setSaving] = useState(false);
    const [erros, setErros] = useState<Record<string, string>>({});

    const alterar = (idx: number, campo: keyof LinhaServico, valor: string | boolean) =>
        setLinhas(ls => ls.map((l, i) => i === idx ? { ...l, [campo]: valor } : l));

    const salvar = () => {
        setSaving(true);
        const servicos = linhas.filter(l => l.ativo).map(l => ({
            servico_id: l.servico_id,
            valor: l.valor === '' ? null : Number(l.valor.replace(',', '.')),
            duracao_minutos: l.duracao_minutos === '' ? null : parseInt(l.duracao_minutos),
        }));
        router.post(route('tenant.profissionais.servicos.sync', profissional.id), { servicos }, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: e => setErros(e),
            onFinish: () => setSaving(false),
        });
    };

    if (catalogo.length === 0) {
        return (
            <div className="px-4 pb-5 pt-4 text-sm sm:px-6" style={{ borderTop: '1px solid var(--border)', color: 'var(--text-3)' }}>
                Cadastre os serviços em Configurações → Serviços para escolher o que {profissional.nome} faz.
            </div>
        );
    }

    return (
        <div className="px-4 pb-5 pt-4 sm:px-6" style={{ borderTop: '1px solid var(--border)', background: 'var(--bg-surface-2)' }}>
            <h4 className="text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>Serviços e preços</h4>
            <p className="mb-4 mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                Marque o que {profissional.nome} faz. Preencha preço ou duração só quando forem diferentes do padrão do serviço. O bot usa esses valores ao agendar.
            </p>
            <div className="space-y-2">
                {catalogo.map((servico, i) => {
                    const linha = linhas[i];
                    return (
                        <div key={servico.id} className="grid grid-cols-2 items-end gap-2 rounded-lg p-3 sm:grid-cols-[1fr_140px_140px] sm:gap-3" style={{ border: '1px solid var(--border)' }}>
                            <label className="col-span-2 flex items-center gap-2 self-center text-sm sm:col-span-1" style={{ color: 'var(--text-1)' }}>
                                <input type="checkbox" checked={linha.ativo} onChange={e => alterar(i, 'ativo', e.target.checked)} />
                                <span>
                                    {servico.nome}
                                    <span className="block text-xs" style={{ color: 'var(--text-3)' }}>Padrão: {precoPadrao(servico)} · {servico.duracao_minutos} min</span>
                                </span>
                            </label>
                            <label>
                                <span className="label mb-1">Preço próprio (R$)</span>
                                <input id={`prof-${profissional.id}-servico-${servico.id}-valor`} inputMode="decimal" value={linha.valor} onChange={e => alterar(i, 'valor', e.target.value)} disabled={!linha.ativo} placeholder="padrão" className="input w-full min-w-0 disabled:opacity-40" />
                            </label>
                            <label>
                                <span className="label mb-1">Duração própria (min)</span>
                                <input id={`prof-${profissional.id}-servico-${servico.id}-duracao`} inputMode="numeric" value={linha.duracao_minutos} onChange={e => alterar(i, 'duracao_minutos', e.target.value.replace(/\D/g, ''))} disabled={!linha.ativo} placeholder="padrão" className="input w-full min-w-0 disabled:opacity-40" />
                            </label>
                        </div>
                    );
                })}
            </div>
            {Object.keys(erros).length > 0 && (
                <p role="alert" className="mt-3 rounded-lg px-3 py-2 text-sm" style={{ background: 'var(--danger-btn-bg)', color: 'var(--danger-text)' }}>
                    {Object.values(erros)[0]}
                </p>
            )}
            <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row">
                <button onClick={salvar} disabled={saving} className="btn-primary">{saving ? 'Salvando…' : 'Salvar serviços'}</button>
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

export default function ProfissionaisIndex({ profissionais, servicos_disponiveis }: Props) {
    const [novoAberto, setNovoAberto] = useState(false);
    const [editando, setEditando] = useState<number | null>(null);
    const [expandido, setExpandido] = useState<number | null>(null);
    const [servicosAbertos, setServicosAbertos] = useState<number | null>(null);

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

            <Toolbar className="mb-5">
                <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                    {profissionais.length} profissional{profissionais.length !== 1 ? 'is' : ''} cadastrado{profissionais.length !== 1 ? 's' : ''}
                </p>
                {!novoAberto && (
                    <button onClick={() => { setNovoAberto(true); setEditando(null); }} className="btn-primary">
                        + Novo profissional
                    </button>
                )}
            </Toolbar>

            <div className="space-y-3">
                {/* Formulário novo profissional inline */}
                {novoAberto && (
                    <NovoProfissionalForm onClose={() => setNovoAberto(false)} />
                )}

                {profissionais.length === 0 && !novoAberto && (
                    <div className="card overflow-hidden">
                        <EmptyState
                            title="Cadastre seu primeiro profissional"
                            description="Defina quem atende, especialidades e horários disponíveis."
                            action={<button type="button" onClick={() => setNovoAberto(true)} className="btn-primary min-h-11">Criar profissional</button>}
                        />
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
                                                        <span key={i} className="rounded-sm px-1.5 py-0.5 text-[11px]" style={{ background: 'rgba(255,255,255,0.06)', color: 'var(--text-3)' }}>
                                                            {esp}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                            <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                                {p.horarios.length > 0
                                                    ? `${p.horarios.length} dia${p.horarios.length !== 1 ? 's' : ''} configurado${p.horarios.length !== 1 ? 's' : ''}`
                                                    : 'Sem horários definidos'}
                                                {' · '}
                                                {p.servicos?.length
                                                    ? `${p.servicos.length} serviço${p.servicos.length !== 1 ? 's' : ''}`
                                                    : 'faz todos os serviços'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="grid w-full grid-cols-2 gap-2 pl-14 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:pl-0">
                                        <StatusBadge tone={p.ativo ? 'success' : 'neutral'} dot>{p.ativo ? 'Ativo' : 'Inativo'}</StatusBadge>
                                        <button
                                            onClick={() => { setExpandido(expandido === p.id ? null : p.id); setServicosAbertos(null); }}
                                            className="btn-secondary min-h-10 justify-center py-1.5 text-xs"
                                        >
                                            {expandido === p.id ? 'Fechar' : 'Horários'}
                                        </button>
                                        <button
                                            onClick={() => { setServicosAbertos(servicosAbertos === p.id ? null : p.id); setExpandido(null); }}
                                            className="btn-secondary min-h-10 justify-center py-1.5 text-xs"
                                        >
                                            {servicosAbertos === p.id ? 'Fechar' : 'Serviços'}
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
                                {servicosAbertos === p.id && (
                                    <ServicosEditor profissional={p} catalogo={servicos_disponiveis} onClose={() => setServicosAbertos(null)} />
                                )}
                            </>
                        )}
                    </div>
                ))}
            </div>
        </ConfiguracoesLayout>
    );
}
