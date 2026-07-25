import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfiguracoesLayout from '@/Layouts/ConfiguracoesLayout';
import { PageProps } from '@/types';
import Toggle from '@/Components/Toggle';

interface RegrasAgendamentoConfig {
    antecedencia_minima_minutos: number;
    antecedencia_maxima_dias: number;
    buffer_entre_agendamentos_minutos: number;
    permite_cliente_remarcar: boolean;
    permite_cliente_cancelar: boolean;
    politica_cancelamento: string | null;
}

interface Props extends PageProps {
    config: RegrasAgendamentoConfig;
}

const PRESETS = [
    {
        id: 'flexivel',
        label: 'Mais flexível',
        description: 'Aceita pedidos para agora e mantém 60 dias disponíveis.',
        values: { antecedencia_minima_minutos: 0, antecedencia_maxima_dias: 60, buffer_entre_agendamentos_minutos: 0 },
    },
    {
        id: 'equilibrado',
        label: 'Recomendado',
        description: '30 min de antecedência e agenda aberta por 30 dias.',
        values: { antecedencia_minima_minutos: 30, antecedencia_maxima_dias: 30, buffer_entre_agendamentos_minutos: 0 },
    },
    {
        id: 'protegido',
        label: 'Mais protegido',
        description: '2h de antecedência e 15 min de respiro entre clientes.',
        values: { antecedencia_minima_minutos: 120, antecedencia_maxima_dias: 30, buffer_entre_agendamentos_minutos: 15 },
    },
] as const;

const formatarAntecedencia = (minutos: number) => {
    if (minutos === 0) return 'até na hora';
    if (minutos < 60) return `${minutos} min antes`;
    if (minutos % 60 === 0) return `${minutos / 60}h antes`;
    return `${minutos} min antes`;
};

export default function RegrasAgendamento({ config }: Props) {
    const [advancedOpen, setAdvancedOpen] = useState(false);
    const { data, setData, put, processing, errors, wasSuccessful } = useForm({
        antecedencia_minima_minutos: config.antecedencia_minima_minutos,
        antecedencia_maxima_dias: config.antecedencia_maxima_dias,
        buffer_entre_agendamentos_minutos: config.buffer_entre_agendamentos_minutos,
        permite_cliente_remarcar: config.permite_cliente_remarcar,
        permite_cliente_cancelar: config.permite_cliente_cancelar,
        politica_cancelamento: config.politica_cancelamento ?? '',
    });

    const presetAtivo = PRESETS.find(preset =>
        preset.values.antecedencia_minima_minutos === data.antecedencia_minima_minutos
        && preset.values.antecedencia_maxima_dias === data.antecedencia_maxima_dias
        && preset.values.buffer_entre_agendamentos_minutos === data.buffer_entre_agendamentos_minutos
    )?.id;

    const aplicarPreset = (preset: typeof PRESETS[number]) => {
        setData(previous => ({ ...previous, ...preset.values }));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        put(route('tenant.regras-agendamento.update'));
    };

    return (
        <ConfiguracoesLayout title="Preferências da agenda" subtitle="Escolha um estilo simples ou personalize quando precisar">
            <Head title="Preferências da agenda" />

            <form onSubmit={submit} className="mx-auto max-w-3xl space-y-5">
                {wasSuccessful && (
                    <div className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-emerald-400" style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)' }}>
                        <span>✓</span>
                        Preferências salvas.
                    </div>
                )}

                <section className="card p-4 sm:p-6">
                    <h2 className="text-base font-semibold text-primary">Como você prefere trabalhar?</h2>
                    <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>Escolha uma opção. Você pode alterar os detalhes quando quiser.</p>

                    <div className="mt-5 grid gap-3 sm:grid-cols-3">
                        {PRESETS.map(preset => {
                            const active = presetAtivo === preset.id;
                            return (
                                <button
                                    key={preset.id}
                                    type="button"
                                    onClick={() => aplicarPreset(preset)}
                                    className="rounded-xl p-4 text-left transition-all hover:brightness-110"
                                    style={{
                                        background: active ? 'var(--accent-light)' : 'var(--bg-surface-2)',
                                        border: `1px solid ${active ? 'var(--accent)' : 'var(--border)'}`,
                                    }}
                                >
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-semibold text-primary">{preset.label}</span>
                                        {active && <span style={{ color: 'var(--accent)' }}>✓</span>}
                                    </span>
                                    <span className="mt-2 block text-xs leading-5" style={{ color: 'var(--text-3)' }}>{preset.description}</span>
                                </button>
                            );
                        })}
                    </div>
                </section>

                <section className="card p-4 sm:p-6">
                    <h2 className="text-sm font-semibold text-primary">Seu cliente poderá</h2>
                    <div className="mt-4 divide-y" style={{ borderColor: 'var(--border)' }}>
                        <div className="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p className="text-sm text-primary">Remarcar pelo WhatsApp</p>
                                <p className="text-xs" style={{ color: 'var(--text-3)' }}>Sem precisar falar com a equipe.</p>
                            </div>
                            <Toggle checked={data.permite_cliente_remarcar} onChange={value => setData('permite_cliente_remarcar', value)} />
                        </div>
                        <div className="flex items-center justify-between gap-4 py-3">
                            <div>
                                <p className="text-sm text-primary">Cancelar pelo WhatsApp</p>
                                <p className="text-xs" style={{ color: 'var(--text-3)' }}>O horário volta automaticamente para a agenda.</p>
                            </div>
                            <Toggle checked={data.permite_cliente_cancelar} onChange={value => setData('permite_cliente_cancelar', value)} />
                        </div>
                    </div>
                </section>

                <section className="card overflow-hidden">
                    <button
                        type="button"
                        onClick={() => setAdvancedOpen(open => !open)}
                        className="flex w-full items-center justify-between gap-4 p-4 text-left sm:p-6"
                    >
                        <span>
                            <span className="block text-sm font-semibold text-primary">Personalizar detalhes</span>
                            <span className="mt-1 block text-xs" style={{ color: 'var(--text-3)' }}>
                                Hoje: pedidos {formatarAntecedencia(data.antecedencia_minima_minutos)}, até {data.antecedencia_maxima_dias} dias, {data.buffer_entre_agendamentos_minutos || 'sem'} intervalo.
                            </span>
                        </span>
                        <span className="text-lg" style={{ color: 'var(--text-3)' }}>{advancedOpen ? '−' : '+'}</span>
                    </button>

                    {advancedOpen && (
                        <div className="space-y-5 px-4 pb-5 sm:px-6 sm:pb-6" style={{ borderTop: '1px solid var(--border)' }}>
                            <div className="grid gap-4 pt-5 sm:grid-cols-3">
                                <label>
                                    <span className="label mb-1">Aceitar a partir de</span>
                                    <select className="input" value={data.antecedencia_minima_minutos} onChange={e => setData('antecedencia_minima_minutos', Number(e.target.value))}>
                                        <option value={0}>Agora</option>
                                        <option value={30}>30 minutos</option>
                                        <option value={60}>1 hora</option>
                                        <option value={120}>2 horas</option>
                                        <option value={1440}>1 dia</option>
                                    </select>
                                </label>
                                <label>
                                    <span className="label mb-1">Agenda aberta por</span>
                                    <select className="input" value={data.antecedencia_maxima_dias} onChange={e => setData('antecedencia_maxima_dias', Number(e.target.value))}>
                                        {[7, 15, 30, 60, 90].map(dias => <option key={dias} value={dias}>{dias} dias</option>)}
                                    </select>
                                </label>
                                <label>
                                    <span className="label mb-1">Respiro entre clientes</span>
                                    <select className="input" value={data.buffer_entre_agendamentos_minutos} onChange={e => setData('buffer_entre_agendamentos_minutos', Number(e.target.value))}>
                                        {[0, 5, 10, 15, 30].map(minutos => <option key={minutos} value={minutos}>{minutos === 0 ? 'Sem intervalo' : `${minutos} minutos`}</option>)}
                                    </select>
                                </label>
                            </div>

                            <label className="block">
                                <span className="label mb-1">Mensagem sobre cancelamentos <span style={{ color: 'var(--text-3)' }}>(opcional)</span></span>
                                <textarea
                                    value={data.politica_cancelamento}
                                    onChange={e => setData('politica_cancelamento', e.target.value)}
                                    rows={3}
                                    className="input resize-none"
                                    placeholder="Ex: Se puder, avise com pelo menos 2 horas de antecedência."
                                    maxLength={500}
                                />
                            </label>
                            {Object.keys(errors).length > 0 && <p className="text-xs text-red-400">Revise os valores informados.</p>}
                        </div>
                    )}
                </section>

                <button type="submit" disabled={processing} className="btn-primary w-full justify-center py-3">
                    {processing ? 'Salvando…' : 'Salvar preferências'}
                </button>
            </form>
        </ConfiguracoesLayout>
    );
}
