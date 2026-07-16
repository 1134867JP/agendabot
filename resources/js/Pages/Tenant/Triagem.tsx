import { Head, Link, useForm } from '@inertiajs/react';
import ConfiguracoesLayout from '@/Layouts/ConfiguracoesLayout';
import { PageProps } from '@/types';
import Toggle from '@/Components/Toggle';

interface TriagemConfig {
    palavras_chave_humano: string[];
    max_tentativas_sem_entender: number;
    transferir_fora_do_horario: boolean;
    mensagem_transferencia: string | null;
}

interface Props extends PageProps {
    config: TriagemConfig;
}

export default function Triagem({ config }: Props) {
    const { data, setData, put, transform, processing, errors, wasSuccessful } = useForm({
        palavras_chave_texto: config.palavras_chave_humano.join(', '),
        max_tentativas_sem_entender: config.max_tentativas_sem_entender,
        transferir_fora_do_horario: config.transferir_fora_do_horario,
        mensagem_transferencia: config.mensagem_transferencia ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        transform(d => ({
            ...d,
            palavras_chave_humano: d.palavras_chave_texto
                .split(',')
                .map((p: string) => p.trim())
                .filter(Boolean),
        }));
        put(route('tenant.triagem.update'));
    };

    return (
        <ConfiguracoesLayout title="Triagem" subtitle="Defina somente quando o bot deve chamar uma pessoa">
            <Head title="Triagem" />

            <div className="mx-auto max-w-2xl space-y-6">
                {wasSuccessful && (
                    <div className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-emerald-400" style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)' }}>
                        Regras de triagem salvas com sucesso.
                    </div>
                )}

                <div className="rounded-xl p-4" style={{ border: '1px solid var(--border)', background: 'var(--bg-surface)' }}>
                    <p className="text-sm font-medium text-primary">O horário não é configurado aqui</p>
                    <p className="mt-1 text-xs leading-5" style={{ color: 'var(--text-3)' }}>
                        A triagem usa automaticamente o horário de funcionamento já cadastrado no estabelecimento. Assim, você altera o horário uma única vez e o bot, a agenda e a triagem permanecem sincronizados.
                    </p>
                    <Link href={route('tenant.configuracoes.index')} className="mt-3 inline-flex text-xs font-medium" style={{ color: 'var(--accent)' }}>
                        Revisar horário do estabelecimento
                    </Link>
                </div>

                <div className="card p-7">
                    <div className="mb-6 flex items-center gap-3">
                        <span className="h-4 w-0.5 rounded-full" style={{ background: 'var(--accent)' }} />
                        <h2 className="text-xs font-semibold uppercase tracking-[0.08em]" style={{ color: 'var(--text-2)' }}>
                            Quando chamar um atendente
                        </h2>
                    </div>

                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <label className="label mb-1">Palavras que pedem atendimento humano</label>
                            <input
                                value={data.palavras_chave_texto}
                                onChange={e => setData('palavras_chave_texto', e.target.value)}
                                className="input"
                                placeholder="Ex: atendente, humano, reclamação"
                            />
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                Separe por vírgulas. Deixe vazio para não usar essa regra.
                            </p>
                        </div>

                        <div>
                            <label className="label mb-1">Quantas vezes o bot pode não entender</label>
                            <input
                                type="number"
                                min={1}
                                max={10}
                                value={data.max_tentativas_sem_entender}
                                onChange={e => setData('max_tentativas_sem_entender', Number(e.target.value))}
                                className="input max-w-[140px]"
                            />
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                Depois desse limite, a conversa é encaminhada para uma pessoa.
                            </p>
                            {errors.max_tentativas_sem_entender && (
                                <p className="mt-1 text-xs text-red-400">{errors.max_tentativas_sem_entender}</p>
                            )}
                        </div>

                        <div className="rounded-xl p-4" style={{ border: '1px solid var(--border)', background: 'var(--bg-card)' }}>
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-medium text-primary">Encaminhar quando o estabelecimento estiver fechado</p>
                                    <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>
                                        Usa o horário central do estabelecimento, sem repetir configuração nesta tela.
                                    </p>
                                </div>
                                <Toggle
                                    checked={data.transferir_fora_do_horario}
                                    onChange={v => setData('transferir_fora_do_horario', v)}
                                />
                            </div>
                        </div>

                        <div>
                            <label className="label mb-1">
                                Mensagem antes de encaminhar{' '}
                                <span className="font-normal" style={{ color: 'var(--text-3)' }}>(opcional)</span>
                            </label>
                            <textarea
                                value={data.mensagem_transferencia}
                                onChange={e => setData('mensagem_transferencia', e.target.value)}
                                rows={3}
                                className="input resize-none"
                                placeholder="Ex: Vou encaminhar sua conversa para nossa equipe."
                                maxLength={300}
                            />
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                {(data.mensagem_transferencia || '').length}/300
                            </p>
                            {errors.mensagem_transferencia && (
                                <p className="mt-1 text-xs text-red-400">{errors.mensagem_transferencia}</p>
                            )}
                        </div>

                        <div className="pt-2">
                            <button type="submit" disabled={processing} className="btn-primary w-full justify-center py-2.5">
                                {processing ? 'Salvando…' : 'Salvar regras de triagem'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </ConfiguracoesLayout>
    );
}
