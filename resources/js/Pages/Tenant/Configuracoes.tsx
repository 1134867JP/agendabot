import { Head, useForm } from '@inertiajs/react';
import ConfiguracoesLayout from '@/Layouts/ConfiguracoesLayout';
import { PageProps, Tenant, TipoServico, HorarioAtendimentoDia, HorarioFuncionamentoDia } from '@/types';
import TipoServicoSelector from '@/Components/TipoServicoSelector';
import Toggle from '@/Components/Toggle';
import { TONS_VOZ, TomVoz } from '@/constants/bot';

interface Props extends PageProps {
    tenant: Tenant;
}

// ─── Bot & IA form ────────────────────────────────────────────────────────────

type ModoBot = 'agendamento' | 'triagem';

const MODOS: { value: ModoBot; label: string; desc: string }[] = [
    { value: 'agendamento', label: 'Agendamento automático', desc: 'O bot consulta a agenda e fecha o agendamento sozinho.' },
    { value: 'triagem',     label: 'Triagem / Pré-atendimento', desc: 'O bot só coleta os dados e transfere para uma atendente concluir.' },
];

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
const DIAS_COMPLETOS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];

function buildHorarioRows(h?: HorarioAtendimentoDia[] | null): HorarioAtendimentoDia[] {
    return DIAS.map((_, i) => {
        const dia = h?.[i];
        return {
            ativo:      dia?.ativo      ?? (i >= 1 && i <= 5), // Seg–Sex por padrão
            abertura:   dia?.abertura   ?? '08:00',
            fechamento: dia?.fechamento ?? '18:00',
        };
    });
}

function buildFuncionamentoRows(
    horarios?: HorarioFuncionamentoDia[] | null,
    legado?: string | null,
): HorarioFuncionamentoDia[] {
    if (horarios?.length === 7) {
        return horarios.map(dia => ({
            ativo: Boolean(dia.ativo),
            periodos: dia.periodos?.length
                ? dia.periodos.map(periodo => ({ ...periodo }))
                : [{ abertura: '09:00', fechamento: '18:00' }],
        }));
    }

    const rows: HorarioFuncionamentoDia[] = DIAS.map(() => ({
        ativo: false,
        periodos: [{ abertura: '09:00', fechamento: '18:00' }],
    }));
    let encontrouHorario = false;

    for (const trecho of (legado ?? '').split(',')) {
        const match = trecho.trim().match(/^(Dom|Seg|Ter|Qua|Qui|Sex|Sáb)(?:\s*[–-]\s*(Dom|Seg|Ter|Qua|Qui|Sex|Sáb))?\s+(.+)$/);
        if (!match) continue;

        const inicio = DIAS.indexOf(match[1]);
        const fim = match[2] ? DIAS.indexOf(match[2]) : inicio;
        const periodos = Array.from(match[3].matchAll(/(\d{2}:\d{2})\s*[–-]\s*(\d{2}:\d{2})/g))
            .map(periodo => ({ abertura: periodo[1], fechamento: periodo[2] }));
        if (inicio < 0 || fim < inicio || periodos.length === 0) continue;

        encontrouHorario = true;
        for (let dia = inicio; dia <= fim; dia++) {
            rows[dia] = { ativo: true, periodos: periodos.map(periodo => ({ ...periodo })) };
        }
    }

    if (encontrouHorario) return rows;

    return rows.map((dia, indice) => ({ ...dia, ativo: indice >= 1 && indice <= 5 }));
}

function resumirFuncionamento(horarios: HorarioFuncionamentoDia[]): string {
    const grupos: { inicio: number; fim: number; faixa: string }[] = [];

    horarios.forEach((dia, indice) => {
        if (!dia.ativo || dia.periodos.length === 0) return;
        const faixa = [...dia.periodos]
            .sort((a, b) => a.abertura.localeCompare(b.abertura))
            .map(periodo => `${periodo.abertura}–${periodo.fechamento}`)
            .join(' e ');
        const ultimo = grupos[grupos.length - 1];

        if (ultimo && ultimo.faixa === faixa && ultimo.fim === indice - 1) {
            ultimo.fim = indice;
        } else {
            grupos.push({ inicio: indice, fim: indice, faixa });
        }
    });

    return grupos.map(grupo => {
        const dias = grupo.inicio === grupo.fim
            ? DIAS[grupo.inicio]
            : `${DIAS[grupo.inicio]}–${DIAS[grupo.fim]}`;
        return `${dias} ${grupo.faixa}`;
    }).join(', ');
}

function validarFuncionamento(horarios: HorarioFuncionamentoDia[]): Record<number, string> {
    const erros: Record<number, string> = {};

    horarios.forEach((dia, indice) => {
        if (!dia.ativo) return;
        if (dia.periodos.length === 0) {
            erros[indice] = 'Adicione pelo menos um período.';
            return;
        }

        const periodos = [...dia.periodos].sort((a, b) => a.abertura.localeCompare(b.abertura));
        for (let i = 0; i < periodos.length; i++) {
            const periodo = periodos[i];
            if (!periodo.abertura || !periodo.fechamento || periodo.fechamento <= periodo.abertura) {
                erros[indice] = 'O fechamento deve ser depois da abertura.';
                return;
            }
            if (i > 0 && periodo.abertura < periodos[i - 1].fechamento) {
                erros[indice] = 'Os períodos não podem se sobrepor.';
                return;
            }
        }
    });

    return erros;
}

function BotConfigForm({ tenant }: { tenant: Tenant }) {
    const { data, setData, put, processing, errors, wasSuccessful } = useForm({
        ramo_negocio:      tenant.ramo_negocio      ?? '',
        descricao_negocio: tenant.descricao_negocio ?? '',
        cidade:            tenant.cidade            ?? '',
        endereco:          tenant.endereco          ?? '',
        nome_agente:       tenant.nome_agente       ?? 'Bia',
        tom_voz:           (tenant.tom_voz          ?? 'semiformal') as TomVoz,
        bot_saudacao:      tenant.bot_saudacao      ?? '',
        instrucoes_extras: tenant.instrucoes_extras ?? '',
        bot_ativo:         tenant.bot_ativo         ?? true,
        modo_bot:          (tenant.modo_bot         ?? 'agendamento') as ModoBot,
        horario_atendimento:   buildHorarioRows(tenant.horario_atendimento),
        mensagem_fora_horario: tenant.mensagem_fora_horario ?? '',
        lembrete_ativo:    tenant.lembrete_ativo ?? true,
        lembrete_texto:    tenant.lembrete_texto ?? '',
    });

    const setDia = (idx: number, campo: keyof HorarioAtendimentoDia, valor: string | boolean) =>
        setData('horario_atendimento', data.horario_atendimento.map((d, i) => i === idx ? { ...d, [campo]: valor } : d));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('tenant.configuracoes.bot'));
    };

    return (
        <div className="card p-4 sm:p-7">
            <div className="mb-6 flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex items-center gap-3">
                    <span className="h-4 w-0.5 rounded-full" style={{ background: 'var(--jade)' }} />
                    <h2 className="text-xs font-semibold uppercase tracking-[0.08em]" style={{ color: 'var(--text-2)' }}>
                        Bot &amp; IA
                    </h2>
                </div>
                {/* Bot ativo toggle */}
                <Toggle
                    checked={data.bot_ativo}
                    onChange={v => setData('bot_ativo', v)}
                    label={data.bot_ativo ? 'Bot ativo' : 'Bot inativo'}
                />
            </div>

            {wasSuccessful && (
                <div
                    className="mb-5 flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-emerald-400"
                    style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)' }}
                >
                    <span>✓</span>
                    Configurações do bot salvas com sucesso.
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                {/* Nome do agente + Ramo do negócio */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label className="label mb-1">Nome do agente</label>
                        <input
                            value={data.nome_agente}
                            onChange={e => setData('nome_agente', e.target.value)}
                            className="input"
                            placeholder="Ex: Bia, Max, Duda"
                        />
                        {errors.nome_agente && <p className="mt-1 text-xs text-red-400">{errors.nome_agente}</p>}
                    </div>
                    <div>
                        <label className="label mb-1">Ramo do negócio</label>
                        <input
                            value={data.ramo_negocio}
                            onChange={e => setData('ramo_negocio', e.target.value)}
                            className="input"
                            placeholder="Ex: Clínica odontológica, Barbearia"
                        />
                        {errors.ramo_negocio && <p className="mt-1 text-xs text-red-400">{errors.ramo_negocio}</p>}
                    </div>
                </div>

                {/* Descrição do negócio */}
                <div>
                    <label className="label mb-1">Descrição do negócio</label>
                    <textarea
                        value={data.descricao_negocio}
                        onChange={e => setData('descricao_negocio', e.target.value)}
                        rows={2}
                        className="input resize-none"
                        placeholder="Ex: Clínica especializada em odontologia estética e preventiva, atendendo famílias há 15 anos."
                    />
                    <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                        Essa descrição é incluída no prompt do bot.
                    </p>
                    {errors.descricao_negocio && <p className="mt-1 text-xs text-red-400">{errors.descricao_negocio}</p>}
                </div>

                {/* Cidade + Endereço */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label className="label mb-1">Cidade</label>
                        <input
                            value={data.cidade}
                            onChange={e => setData('cidade', e.target.value)}
                            className="input"
                            placeholder="Ex: Porto Alegre, RS"
                        />
                        {errors.cidade && <p className="mt-1 text-xs text-red-400">{errors.cidade}</p>}
                    </div>
                    <div>
                        <label className="label mb-1">Endereço</label>
                        <input
                            value={data.endereco}
                            onChange={e => setData('endereco', e.target.value)}
                            className="input"
                            placeholder="Ex: Rua das Flores, 42 — Centro"
                        />
                        {errors.endereco && <p className="mt-1 text-xs text-red-400">{errors.endereco}</p>}
                    </div>
                </div>

                {/* Mensagem de boas-vindas */}
                <div>
                    <label className="label mb-1">Mensagem de boas-vindas</label>
                    <textarea
                        value={data.bot_saudacao}
                        onChange={e => setData('bot_saudacao', e.target.value)}
                        rows={2}
                        className="input resize-none"
                        placeholder="Ex: Olá! Bem-vindo à nossa clínica. Como posso ajudar?"
                        maxLength={500}
                    />
                    <p className="mt-1 flex justify-between text-xs" style={{ color: 'var(--text-3)' }}>
                        <span>Referência que o bot usa ao saudar o cliente na primeira mensagem.</span>
                        <span>{(data.bot_saudacao || '').length}/500</span>
                    </p>
                    {errors.bot_saudacao && <p className="mt-1 text-xs text-red-400">{errors.bot_saudacao}</p>}
                </div>

                {/* Tom de voz */}
                <div>
                    <label className="label mb-3">Tom de voz</label>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        {TONS_VOZ.map(ton => {
                            const ativo = data.tom_voz === ton.value;
                            return (
                                <button
                                    key={ton.value}
                                    type="button"
                                    onClick={() => setData('tom_voz', ton.value)}
                                    className="rounded-xl p-3 text-left transition-all"
                                    style={{
                                        border: ativo ? '1px solid var(--accent)' : '1px solid var(--border-strong)',
                                        background: ativo ? 'var(--accent-light)' : 'transparent',
                                        boxShadow: ativo ? '0 0 0 1px var(--accent)' : 'none',
                                    }}
                                >
                                    <p className="text-sm font-medium" style={{ color: ativo ? 'var(--accent)' : 'var(--text-1)' }}>{ton.label}</p>
                                    <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>{ton.desc}</p>
                                </button>
                            );
                        })}
                    </div>
                    {errors.tom_voz && <p className="mt-1 text-xs text-red-400">{errors.tom_voz}</p>}
                </div>

                {/* Modo de atendimento */}
                <div>
                    <label className="label mb-3">Modo de atendimento</label>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {MODOS.map(modo => {
                            const ativo = data.modo_bot === modo.value;
                            return (
                                <button
                                    key={modo.value}
                                    type="button"
                                    onClick={() => setData('modo_bot', modo.value)}
                                    className="rounded-xl p-3 text-left transition-all"
                                    style={{
                                        border: ativo ? '1px solid var(--accent)' : '1px solid var(--border-strong)',
                                        background: ativo ? 'var(--accent-light)' : 'transparent',
                                        boxShadow: ativo ? '0 0 0 1px var(--accent)' : 'none',
                                    }}
                                >
                                    <p className="text-sm font-medium" style={{ color: ativo ? 'var(--accent)' : 'var(--text-1)' }}>{modo.label}</p>
                                    <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>{modo.desc}</p>
                                </button>
                            );
                        })}
                    </div>
                    {errors.modo_bot && <p className="mt-1 text-xs text-red-400">{errors.modo_bot}</p>}
                </div>

                {/* Horário de atendimento — só na triagem */}
                {data.modo_bot === 'triagem' && (
                    <div className="rounded-xl p-4" style={{ border: '1px solid var(--border)', background: 'var(--bg-card)' }}>
                        <p className="text-sm font-medium text-primary">Horário de atendimento</p>
                        <p className="mt-0.5 mb-3 text-xs" style={{ color: 'var(--text-3)' }}>
                            Dias e horas em que a atendente está disponível. Fora desse horário o bot ainda coleta os dados, mas avisa que o retorno será nesse período.
                        </p>
                        <div className="space-y-2.5">
                            {data.horario_atendimento.map((dia, i) => (
                                <div key={i} className="grid grid-cols-2 items-center gap-2 rounded-lg p-2 sm:flex sm:flex-wrap sm:gap-4" style={{ background: 'var(--bg-surface-2)' }}>
                                    <div className="col-span-2 min-w-0 sm:col-span-1 sm:w-24">
                                        <Toggle checked={dia.ativo} onChange={() => setDia(i, 'ativo', !dia.ativo)} label={DIAS[i]} />
                                    </div>
                                    <input type="time" value={dia.abertura} onChange={e => setDia(i, 'abertura', e.target.value)} disabled={!dia.ativo} className="input w-full min-w-0 disabled:opacity-40 sm:w-28" />
                                    <span className="hidden text-xs sm:inline" style={{ color: 'var(--text-3)' }}>até</span>
                                    <input type="time" value={dia.fechamento} onChange={e => setDia(i, 'fechamento', e.target.value)} disabled={!dia.ativo} className="input w-full min-w-0 disabled:opacity-40 sm:w-28" />
                                </div>
                            ))}
                        </div>

                        <div className="mt-4">
                            <label className="label mb-1">
                                Mensagem fora do horário{' '}
                                <span className="font-normal" style={{ color: 'var(--text-3)' }}>(opcional)</span>
                            </label>
                            <textarea
                                value={data.mensagem_fora_horario}
                                onChange={e => setData('mensagem_fora_horario', e.target.value)}
                                rows={2}
                                className="input resize-none"
                                placeholder="Deixe vazio para o bot montar automaticamente com base no horário configurado."
                                maxLength={500}
                            />
                            {errors.mensagem_fora_horario && <p className="mt-1 text-xs text-red-400">{errors.mensagem_fora_horario}</p>}
                        </div>
                    </div>
                )}

                {/* Instruções extras */}
                <div>
                    <label className="label mb-1">Instruções extras</label>
                    <textarea
                        value={data.instrucoes_extras}
                        onChange={e => setData('instrucoes_extras', e.target.value)}
                        rows={4}
                        className="input resize-none"
                        placeholder="Ex: Não agendar segunda de manhã. Sempre perguntar se é retorno ou primeira consulta. Aceitar apenas Unimed e Bradesco Saúde."
                        maxLength={3000}
                    />
                    <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                        {(data.instrucoes_extras || '').length}/3000 caracteres
                    </p>
                    {errors.instrucoes_extras && <p className="mt-1 text-xs text-red-400">{errors.instrucoes_extras}</p>}
                </div>

                {/* Lembretes automáticos */}
                <div className="rounded-xl p-4" style={{ border: '1px solid var(--border)', background: 'var(--bg-card)' }}>
                    <div className="mb-3 flex items-start justify-between gap-3">
                        <div>
                            <p className="text-sm font-medium text-primary">Lembrete automático</p>
                            <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                Envia uma mensagem WhatsApp 24h antes do agendamento
                            </p>
                        </div>
                        <Toggle checked={data.lembrete_ativo} onChange={v => setData('lembrete_ativo', v)} />
                    </div>

                    {data.lembrete_ativo && (
                        <div className="mt-3">
                            <label className="label mb-1">
                                Mensagem personalizada{' '}
                                <span className="font-normal" style={{ color: 'var(--text-3)' }}>(opcional)</span>
                            </label>
                            <textarea
                                value={data.lembrete_texto}
                                onChange={e => setData('lembrete_texto', e.target.value)}
                                rows={3}
                                className="input resize-none"
                                placeholder={'Deixe vazio para usar o padrão:\n"Para confirmar, responda: ✅ CONFIRMO\nPara cancelar, responda: ❌ CANCELAR"'}
                                maxLength={500}
                            />
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                {(data.lembrete_texto || '').length}/500 — O cabeçalho com nome, data e horário é sempre incluído.
                            </p>
                        </div>
                    )}
                </div>

                <div className="pt-2">
                    <button type="submit" disabled={processing} className="btn-primary w-full justify-center py-2.5">
                        {processing ? 'Salvando…' : 'Salvar configurações do bot'}
                    </button>
                </div>
            </form>
        </div>
    );
}

// ─── Main page ────────────────────────────────────────────────────────────────

export default function Configuracoes({ tenant }: Props) {
    const { data, setData, put, processing, errors, wasSuccessful } = useForm({
        nome:                       tenant.nome,
        tipo_servico:               tenant.tipo_servico,
        tipo_servico_personalizado: tenant.tipo_servico_personalizado ?? '',
        horarios_funcionamento_semana: buildFuncionamentoRows(
            tenant.horarios_funcionamento_semana,
            tenant.horarios_funcionamento,
        ),
    });

    const errosFuncionamento = validarFuncionamento(data.horarios_funcionamento_semana);
    const resumoFuncionamento = resumirFuncionamento(data.horarios_funcionamento_semana);

    const setHorarioDia = (dia: number, patch: Partial<HorarioFuncionamentoDia>) => {
        setData('horarios_funcionamento_semana', data.horarios_funcionamento_semana.map((config, indice) =>
            indice === dia ? { ...config, ...patch } : config
        ));
    };

    const setPeriodo = (dia: number, periodo: number, campo: 'abertura' | 'fechamento', valor: string) => {
        setHorarioDia(dia, {
            periodos: data.horarios_funcionamento_semana[dia].periodos.map((faixa, indice) =>
                indice === periodo ? { ...faixa, [campo]: valor } : faixa
            ),
        });
    };

    const adicionarPeriodo = (dia: number) => {
        const config = data.horarios_funcionamento_semana[dia];
        const ultimo = config.periodos[config.periodos.length - 1];
        setHorarioDia(dia, {
            periodos: [...config.periodos, {
                abertura: ultimo?.fechamento ?? '13:00',
                fechamento: (ultimo?.fechamento ?? '13:00') < '18:00' ? '18:00' : '19:00',
            }],
        });
    };

    const removerPeriodo = (dia: number, periodo: number) => {
        const periodos = data.horarios_funcionamento_semana[dia].periodos.filter((_, indice) => indice !== periodo);
        setHorarioDia(dia, { periodos });
    };

    const aplicarPreset = (tipo: 'semana' | 'todos' | 'fechado') => {
        setData('horarios_funcionamento_semana', data.horarios_funcionamento_semana.map((dia, indice) => ({
            ...dia,
            ativo: tipo === 'todos' || (tipo === 'semana' && indice >= 1 && indice <= 5),
            periodos: [{ abertura: '09:00', fechamento: '18:00' }],
        })));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (Object.keys(errosFuncionamento).length > 0) return;
        put(route('tenant.configuracoes.update'));
    };

    return (
        <ConfiguracoesLayout title="Configurações" subtitle="Nome do estabelecimento, tipo de serviço e personalização do bot">
            <Head title="Configurações" />

            <div className="mx-auto max-w-2xl space-y-6">
                {wasSuccessful && (
                    <div className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-emerald-400" style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)' }}>
                        <span>✓</span>
                        Configurações salvas com sucesso.
                    </div>
                )}

                <div className="card p-4 sm:p-7">
                    <div className="mb-6 flex items-center gap-3">
                        <span className="h-4 w-0.5 rounded-full" style={{ background: 'var(--accent)' }} />
                        <h2 className="text-xs font-semibold uppercase tracking-[0.08em]" style={{ color: 'var(--text-2)' }}>
                            Dados do estabelecimento
                        </h2>
                    </div>
                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <label className="label mb-1">Nome do estabelecimento</label>
                            <input
                                value={data.nome}
                                onChange={e => setData('nome', e.target.value)}
                                className="input"
                                required
                            />
                            {errors.nome && <p className="mt-1 text-xs text-red-400">{errors.nome}</p>}
                        </div>

                        <div>
                            <label className="label mb-2">Tipo de serviço</label>
                            <TipoServicoSelector
                                value={data.tipo_servico}
                                onChange={v => setData('tipo_servico', v as TipoServico)}
                                customValue={data.tipo_servico_personalizado}
                                onChangeCustom={v => setData('tipo_servico_personalizado', v)}
                                error={errors.tipo_servico || errors.tipo_servico_personalizado}
                            />
                        </div>

                        <div className="overflow-hidden rounded-xl" style={{ border: '1px solid var(--border-strong)' }}>
                            <div className="p-4 sm:p-5" style={{ background: 'var(--bg-card)', borderBottom: '1px solid var(--border)' }}>
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-primary">Horários de funcionamento</p>
                                        <p className="mt-1 max-w-md text-xs leading-relaxed" style={{ color: 'var(--text-3)' }}>
                                            Configure quando o estabelecimento está aberto. O bot usa esse resumo ao responder perguntas sobre horários.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-1.5">
                                        <button type="button" onClick={() => aplicarPreset('semana')} className="btn-secondary px-2.5 py-1.5 text-xs">
                                            Seg–Sex
                                        </button>
                                        <button type="button" onClick={() => aplicarPreset('todos')} className="btn-secondary px-2.5 py-1.5 text-xs">
                                            Todos os dias
                                        </button>
                                        <button type="button" onClick={() => aplicarPreset('fechado')} className="btn-secondary px-2.5 py-1.5 text-xs">
                                            Fechar todos
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div className="divide-y" style={{ borderColor: 'var(--border)' }}>
                                {data.horarios_funcionamento_semana.map((dia, diaIndex) => (
                                    <div
                                        key={DIAS_COMPLETOS[diaIndex]}
                                        className="p-3.5 sm:p-4"
                                        style={{ background: dia.ativo ? 'var(--bg-surface-2)' : 'transparent' }}
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <Toggle
                                                checked={dia.ativo}
                                                onChange={ativo => setHorarioDia(diaIndex, { ativo })}
                                                label={DIAS_COMPLETOS[diaIndex]}
                                            />
                                            <span
                                                className="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-wide"
                                                style={{
                                                    color: dia.ativo ? 'var(--jade)' : 'var(--text-3)',
                                                    background: dia.ativo ? 'var(--jade-light)' : 'var(--bg-card)',
                                                }}
                                            >
                                                {dia.ativo ? 'Aberto' : 'Fechado'}
                                            </span>
                                        </div>

                                        {dia.ativo && (
                                            <div className="mt-3 space-y-2 sm:pl-8">
                                                {dia.periodos.map((periodo, periodoIndex) => (
                                                    <div key={periodoIndex} className="flex flex-wrap items-center gap-2">
                                                        <input
                                                            type="time"
                                                            value={periodo.abertura}
                                                            onChange={e => setPeriodo(diaIndex, periodoIndex, 'abertura', e.target.value)}
                                                            aria-label={`Abertura de ${DIAS_COMPLETOS[diaIndex]}`}
                                                            className="input min-w-0 flex-1 sm:w-32 sm:flex-none"
                                                        />
                                                        <span className="text-xs" style={{ color: 'var(--text-3)' }}>às</span>
                                                        <input
                                                            type="time"
                                                            value={periodo.fechamento}
                                                            onChange={e => setPeriodo(diaIndex, periodoIndex, 'fechamento', e.target.value)}
                                                            aria-label={`Fechamento de ${DIAS_COMPLETOS[diaIndex]}`}
                                                            className="input min-w-0 flex-1 sm:w-32 sm:flex-none"
                                                        />
                                                        {dia.periodos.length > 1 && (
                                                            <button
                                                                type="button"
                                                                onClick={() => removerPeriodo(diaIndex, periodoIndex)}
                                                                className="min-h-10 rounded-lg px-2 text-xs transition-colors hover:bg-red-500/10"
                                                                style={{ color: '#f87171' }}
                                                                aria-label={`Remover período de ${DIAS_COMPLETOS[diaIndex]}`}
                                                            >
                                                                Remover
                                                            </button>
                                                        )}
                                                    </div>
                                                ))}

                                                <div className="flex items-center justify-between gap-3">
                                                    {dia.periodos.length < 2 ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => adicionarPeriodo(diaIndex)}
                                                            className="text-xs font-medium transition-opacity hover:opacity-75"
                                                            style={{ color: 'var(--jade)' }}
                                                        >
                                                            + Adicionar intervalo
                                                        </button>
                                                    ) : <span />}
                                                    {errosFuncionamento[diaIndex] && (
                                                        <p className="text-right text-xs text-red-400">{errosFuncionamento[diaIndex]}</p>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <div className="p-4" style={{ background: 'var(--bg-card)', borderTop: '1px solid var(--border)' }}>
                                <p className="label mb-1">Como o cliente verá</p>
                                <p className="text-sm leading-relaxed" style={{ color: resumoFuncionamento ? 'var(--text-2)' : 'var(--text-3)' }}>
                                    {resumoFuncionamento || 'Estabelecimento fechado todos os dias'}
                                </p>
                                <p className="mt-2 text-xs" style={{ color: 'var(--text-3)' }}>
                                    A disponibilidade de cada profissional continua sendo configurada separadamente.
                                </p>
                                {errors.horarios_funcionamento_semana && (
                                    <p className="mt-2 text-xs text-red-400">{errors.horarios_funcionamento_semana}</p>
                                )}
                            </div>
                        </div>

                        <div className="pt-2">
                            <button type="submit" disabled={processing || Object.keys(errosFuncionamento).length > 0} className="btn-primary w-full justify-center py-2.5">
                                {processing ? 'Salvando…' : 'Salvar configurações'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Bot & IA section */}
                <BotConfigForm tenant={tenant} />
            </div>
        </ConfiguracoesLayout>
    );
}
