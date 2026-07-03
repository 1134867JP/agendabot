import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';

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

function Toggle({ checked, onChange }: { checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            onClick={() => onChange(!checked)}
            className="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none"
            style={{ background: checked ? 'var(--jade)' : 'rgba(255,255,255,0.12)' }}
        >
            <span
                className={`inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform ${
                    checked ? 'translate-x-5' : 'translate-x-0.5'
                }`}
            />
        </button>
    );
}

export default function RegrasAgendamento({ config }: Props) {
    const { data, setData, put, processing, errors, wasSuccessful } = useForm({
        antecedencia_minima_minutos:       config.antecedencia_minima_minutos,
        antecedencia_maxima_dias:          config.antecedencia_maxima_dias,
        buffer_entre_agendamentos_minutos: config.buffer_entre_agendamentos_minutos,
        permite_cliente_remarcar:          config.permite_cliente_remarcar,
        permite_cliente_cancelar:          config.permite_cliente_cancelar,
        politica_cancelamento:             config.politica_cancelamento ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('tenant.regras-agendamento.update'));
    };

    return (
        <AppLayout title="Regras de agendamento" subtitle="Antecedência, intervalo entre horários e política de cancelamento">
            <Head title="Regras de agendamento" />

            <div className="mx-auto max-w-2xl space-y-6">
                {wasSuccessful && (
                    <div className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-emerald-400" style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)' }}>
                        <span>✓</span>
                        Regras de agendamento salvas com sucesso.
                    </div>
                )}

                <div className="card p-7">
                    <div className="mb-6 flex items-center gap-3">
                        <span className="h-4 w-0.5 rounded-full" style={{ background: 'var(--accent)' }} />
                        <h2 className="text-xs font-semibold uppercase tracking-[0.08em]" style={{ color: 'var(--text-2)' }}>
                            Janela de agendamento
                        </h2>
                    </div>

                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label className="label mb-1">Antecedência mínima (minutos)</label>
                                <input
                                    type="number"
                                    min={0}
                                    max={10080}
                                    value={data.antecedencia_minima_minutos}
                                    onChange={e => setData('antecedencia_minima_minutos', Number(e.target.value))}
                                    className="input"
                                />
                                <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                    Tempo mínimo entre agora e o horário do agendamento.
                                </p>
                                {errors.antecedencia_minima_minutos && (
                                    <p className="mt-1 text-xs text-red-400">{errors.antecedencia_minima_minutos}</p>
                                )}
                            </div>
                            <div>
                                <label className="label mb-1">Antecedência máxima (dias)</label>
                                <input
                                    type="number"
                                    min={1}
                                    max={365}
                                    value={data.antecedencia_maxima_dias}
                                    onChange={e => setData('antecedencia_maxima_dias', Number(e.target.value))}
                                    className="input"
                                />
                                <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                    Quantos dias no futuro o cliente pode agendar.
                                </p>
                                {errors.antecedencia_maxima_dias && (
                                    <p className="mt-1 text-xs text-red-400">{errors.antecedencia_maxima_dias}</p>
                                )}
                            </div>
                        </div>

                        <div>
                            <label className="label mb-1">Intervalo entre agendamentos (minutos)</label>
                            <input
                                type="number"
                                min={0}
                                max={240}
                                value={data.buffer_entre_agendamentos_minutos}
                                onChange={e => setData('buffer_entre_agendamentos_minutos', Number(e.target.value))}
                                className="input max-w-[180px]"
                            />
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                Tempo de folga adicionado entre um agendamento e o próximo do mesmo profissional/recurso.
                            </p>
                            {errors.buffer_entre_agendamentos_minutos && (
                                <p className="mt-1 text-xs text-red-400">{errors.buffer_entre_agendamentos_minutos}</p>
                            )}
                        </div>

                        <div className="rounded-xl p-4 space-y-4" style={{ border: '1px solid var(--border)', background: 'var(--bg-card)' }}>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-primary">Cliente pode remarcar pelo WhatsApp</p>
                                    <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                        Permite que o próprio cliente altere data/horário sem falar com um atendente.
                                    </p>
                                </div>
                                <Toggle
                                    checked={data.permite_cliente_remarcar}
                                    onChange={v => setData('permite_cliente_remarcar', v)}
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-primary">Cliente pode cancelar pelo WhatsApp</p>
                                    <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                        Permite que o próprio cliente cancele o agendamento pelo bot.
                                    </p>
                                </div>
                                <Toggle
                                    checked={data.permite_cliente_cancelar}
                                    onChange={v => setData('permite_cliente_cancelar', v)}
                                />
                            </div>
                        </div>

                        <div>
                            <label className="label mb-1">
                                Política de cancelamento{' '}
                                <span className="font-normal" style={{ color: 'var(--text-3)' }}>(opcional)</span>
                            </label>
                            <textarea
                                value={data.politica_cancelamento}
                                onChange={e => setData('politica_cancelamento', e.target.value)}
                                rows={3}
                                className="input resize-none"
                                placeholder="Ex: Cancelamentos com menos de 2h de antecedência não são reembolsados."
                                maxLength={500}
                            />
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                {(data.politica_cancelamento || '').length}/500
                            </p>
                            {errors.politica_cancelamento && (
                                <p className="mt-1 text-xs text-red-400">{errors.politica_cancelamento}</p>
                            )}
                        </div>

                        <div className="pt-2">
                            <button type="submit" disabled={processing} className="btn-primary w-full justify-center py-2.5">
                                {processing ? 'Salvando…' : 'Salvar regras de agendamento'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
