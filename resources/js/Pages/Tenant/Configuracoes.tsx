import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Tenant, TipoServico, HorarioAtendimentoDia } from '@/types';
import TipoServicoSelector from '@/Components/TipoServicoSelector';
import Toggle from '@/Components/Toggle';

interface Props extends PageProps {
    tenant: Tenant;
}

// ─── Bot & IA form ────────────────────────────────────────────────────────────

type TomVoz = 'formal' | 'semiformal' | 'descontraido';
type ModoBot = 'agendamento' | 'triagem';

const TONS: { value: TomVoz; label: string; desc: string }[] = [
    { value: 'formal',       label: 'Formal',       desc: 'Profissional, sem emojis, "Senhor/Senhora"' },
    { value: 'semiformal',   label: 'Semiformal',   desc: 'Claro e amigável, emojis moderados' },
    { value: 'descontraido', label: 'Descontraído', desc: 'Leve, emojis liberados, gírias suaves' },
];

const MODOS: { value: ModoBot; label: string; desc: string }[] = [
    { value: 'agendamento', label: 'Agendamento automático', desc: 'O bot consulta a agenda e fecha o agendamento sozinho.' },
    { value: 'triagem',     label: 'Triagem / Pré-atendimento', desc: 'O bot só coleta os dados e transfere para uma atendente concluir.' },
];

const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

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

function BotConfigForm({ tenant }: { tenant: Tenant }) {
    const { data, setData, put, processing, errors, wasSuccessful } = useForm({
        ramo_negocio:      tenant.ramo_negocio      ?? '',
        descricao_negocio: tenant.descricao_negocio ?? '',
        cidade:            tenant.cidade            ?? '',
        endereco:          tenant.endereco          ?? '',
        nome_agente:       tenant.nome_agente       ?? 'Bia',
        tom_voz:           (tenant.tom_voz          ?? 'semiformal') as TomVoz,
        instrucoes_extras: tenant.instrucoes_extras ?? '',
        bot_ativo:         tenant.bot_ativo         ?? true,
        modo_bot:          (tenant.modo_bot         ?? 'agendamento') as ModoBot,
        horario_atendimento:   buildHorarioRows(tenant.horario_atendimento),
        mensagem_fora_horario: tenant.mensagem_fora_horario ?? '',
        lembrete_ativo:    (tenant as any).lembrete_ativo ?? true,
        lembrete_texto:    (tenant as any).lembrete_texto ?? '',
    });

    const setDia = (idx: number, campo: keyof HorarioAtendimentoDia, valor: string | boolean) =>
        setData('horario_atendimento', data.horario_atendimento.map((d, i) => i === idx ? { ...d, [campo]: valor } : d));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('tenant.configuracoes.bot'));
    };

    return (
        <div className="card p-7">
            <div className="mb-6 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <span className="h-4 w-0.5 rounded-full" style={{ background: 'var(--jade)' }} />
                    <h2 className="text-xs font-semibold uppercase tracking-[0.08em]" style={{ color: 'var(--text-2)' }}>
                        Bot &amp; IA
                    </h2>
                </div>
                {/* Bot ativo toggle */}
                <label className="flex cursor-pointer items-center gap-2">
                    <span className="text-sm" style={{ color: 'var(--text-2)' }}>
                        {data.bot_ativo ? 'Bot ativo' : 'Bot inativo'}
                    </span>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={data.bot_ativo}
                        onClick={() => setData('bot_ativo', !data.bot_ativo)}
                        className="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
                        style={{ background: data.bot_ativo ? 'var(--jade)' : 'rgba(255,255,255,0.12)' }}
                    >
                        <span
                            className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform ${
                                data.bot_ativo ? 'translate-x-5' : 'translate-x-0.5'
                            }`}
                        />
                    </button>
                </label>
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

                {/* Tom de voz */}
                <div>
                    <label className="label mb-3">Tom de voz</label>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        {TONS.map(ton => {
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
                                <div key={i} className="flex flex-wrap items-center gap-4">
                                    <div className="w-24">
                                        <Toggle checked={dia.ativo} onChange={() => setDia(i, 'ativo', !dia.ativo)} label={DIAS[i]} />
                                    </div>
                                    <input type="time" value={dia.abertura} onChange={e => setDia(i, 'abertura', e.target.value)} disabled={!dia.ativo} className="input w-28 disabled:opacity-40" />
                                    <span className="text-xs" style={{ color: 'var(--text-3)' }}>até</span>
                                    <input type="time" value={dia.fechamento} onChange={e => setDia(i, 'fechamento', e.target.value)} disabled={!dia.ativo} className="input w-28 disabled:opacity-40" />
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
                    <div className="mb-3 flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-primary">Lembrete automático</p>
                            <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                Envia uma mensagem WhatsApp 24h antes do agendamento
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            aria-checked={data.lembrete_ativo}
                            onClick={() => setData('lembrete_ativo', !data.lembrete_ativo)}
                            className="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none"
                            style={{ background: data.lembrete_ativo ? 'var(--jade)' : 'rgba(255,255,255,0.12)' }}
                        >
                            <span
                                className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform ${
                                    data.lembrete_ativo ? 'translate-x-5' : 'translate-x-0.5'
                                }`}
                            />
                        </button>
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
        horarios_funcionamento:     (tenant as any).horarios_funcionamento ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('tenant.configuracoes.update'));
    };

    return (
        <AppLayout title="Configurações" subtitle="Nome do estabelecimento, tipo de serviço e personalização do bot">
            <Head title="Configurações" />

            <div className="mx-auto max-w-2xl space-y-6">
                {wasSuccessful && (
                    <div className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-emerald-400" style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)' }}>
                        <span>✓</span>
                        Configurações salvas com sucesso.
                    </div>
                )}

                <div className="card p-7">
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

                        <div>
                            <label className="label mb-1">
                                Horários de funcionamento{' '}
                                <span className="font-normal" style={{ color: 'var(--text-3)' }}>(opcional)</span>
                            </label>
                            <input
                                value={data.horarios_funcionamento}
                                onChange={e => setData('horarios_funcionamento', e.target.value)}
                                className="input"
                                placeholder="Ex: Seg–Sex 09:00–19:00, Sáb 09:00–13:00"
                                maxLength={255}
                            />
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                Informação exibida para o cliente quando perguntar sobre horários.
                            </p>
                            {errors.horarios_funcionamento && (
                                <p className="mt-1 text-xs text-red-400">{errors.horarios_funcionamento}</p>
                            )}
                        </div>

                        <div className="pt-2">
                            <button type="submit" disabled={processing} className="btn-primary w-full justify-center py-2.5">
                                {processing ? 'Salvando…' : 'Salvar configurações'}
                            </button>
                        </div>
                    </form>
                </div>

                {/* Bot & IA section */}
                <BotConfigForm tenant={tenant} />
            </div>
        </AppLayout>
    );
}
