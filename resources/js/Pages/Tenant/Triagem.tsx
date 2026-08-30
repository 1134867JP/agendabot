import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Toggle from '@/Components/Toggle';
import ConfiguracoesLayout from '@/Layouts/ConfiguracoesLayout';
import { PageProps } from '@/types';

interface TriagemConfig {
    palavras_chave_humano: string[];
    max_tentativas_sem_entender: number;
    transferir_fora_do_horario: boolean;
    mensagem_transferencia: string | null;
}

interface Props extends PageProps {
    config: TriagemConfig;
    horarioFuncionamento: {
        configurado: boolean;
        resumo: string;
    };
}

export default function Triagem({ config, horarioFuncionamento }: Props) {
    const [novaPalavra, setNovaPalavra] = useState('');
    const {
        data,
        setData,
        put,
        processing,
        errors,
        wasSuccessful,
        isDirty,
    } = useForm({
        palavras_chave_humano: config.palavras_chave_humano,
        max_tentativas_sem_entender: config.max_tentativas_sem_entender,
        transferir_fora_do_horario: config.transferir_fora_do_horario,
        mensagem_transferencia: config.mensagem_transferencia ?? '',
    });

    const adicionarPalavra = () => {
        const palavra = novaPalavra.trim().toLocaleLowerCase('pt-BR');
        if (!palavra || data.palavras_chave_humano.includes(palavra) || data.palavras_chave_humano.length >= 20) {
            setNovaPalavra('');
            return;
        }

        setData('palavras_chave_humano', [...data.palavras_chave_humano, palavra]);
        setNovaPalavra('');
    };

    const removerPalavra = (palavra: string) => {
        setData('palavras_chave_humano', data.palavras_chave_humano.filter(item => item !== palavra));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        put(route('tenant.triagem.update'), { preserveScroll: true });
    };

    const mensagemPreview = data.mensagem_transferencia.trim()
        || 'Vou encaminhar sua conversa para a equipe. Assim que possível, alguém continuará o atendimento.';

    return (
        <ConfiguracoesLayout title="Triagem" subtitle="Defina quando o bot deve passar a conversa para sua equipe">
            <Head title="Triagem" />

            <div className="mx-auto max-w-2xl space-y-5">
                {wasSuccessful && (
                    <div className="flex items-center gap-3 rounded-lg px-4 py-3 text-sm text-emerald-400" style={{ background: 'rgba(110,231,183,0.08)', border: '1px solid rgba(110,231,183,0.2)' }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden>
                            <path d="m5 12 4 4L19 6" />
                        </svg>
                        Regras de triagem salvas.
                    </div>
                )}

                <section className="card overflow-hidden">
                    <div className="p-4 sm:p-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p className="text-sm font-medium text-primary">Horário usado pela triagem</p>
                                <p className="mt-1 text-xs leading-relaxed" style={{ color: 'var(--text-3)' }}>
                                    A triagem utiliza o mesmo horário cadastrado para o estabelecimento. Não é necessário configurar novamente.
                                </p>
                            </div>
                            <span
                                className="w-fit rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide"
                                style={{
                                    color: horarioFuncionamento.configurado ? 'var(--jade)' : '#f59e0b',
                                    background: horarioFuncionamento.configurado ? 'var(--jade-light)' : 'rgba(245,158,11,.1)',
                                }}
                            >
                                {horarioFuncionamento.configurado ? 'Configurado' : 'Pendente'}
                            </span>
                        </div>
                        <div className="mt-4 rounded-xl p-3.5" style={{ background: 'var(--bg-surface-2)', border: '1px solid var(--border)' }}>
                            <p className="text-sm font-medium" style={{ color: horarioFuncionamento.configurado ? 'var(--text-1)' : '#f59e0b' }}>
                                {horarioFuncionamento.resumo}
                            </p>
                        </div>
                    </div>
                    <div className="flex justify-end px-4 py-3 sm:px-6" style={{ background: 'var(--bg-card)', borderTop: '1px solid var(--border)' }}>
                        <Link href={route('tenant.configuracoes.index')} className="btn-secondary min-h-10 text-xs">
                            Alterar horário do estabelecimento
                        </Link>
                    </div>
                </section>

                <form onSubmit={submit} className="card p-4 sm:p-7">
                    <div className="mb-6 flex items-center gap-3">
                        <span className="h-4 w-0.5 rounded-full" style={{ background: 'var(--accent)' }} />
                        <h2 className="text-xs font-semibold uppercase tracking-[0.08em]" style={{ color: 'var(--text-2)' }}>
                            Transferência para humano
                        </h2>
                    </div>

                    <div className="space-y-6">
                        <div>
                            <label className="label mb-1" htmlFor="triagem-nova-palavra">Palavras que chamam um atendente</label>
                            <div className="flex gap-2">
                                <input
                                    id="triagem-nova-palavra"
                                    value={novaPalavra}
                                    onChange={event => setNovaPalavra(event.target.value)}
                                    onKeyDown={event => {
                                        if (event.key === 'Enter' || event.key === ',') {
                                            event.preventDefault();
                                            adicionarPalavra();
                                        }
                                    }}
                                    className="input min-w-0 flex-1"
                                    placeholder="Ex.: atendente"
                                    maxLength={50}
                                />
                                <button type="button" onClick={adicionarPalavra} className="btn-secondary min-h-11 shrink-0">
                                    Adicionar
                                </button>
                            </div>
                            <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                Ao identificar uma destas palavras, o bot envia a conversa para a equipe.
                            </p>
                            {data.palavras_chave_humano.length > 0 ? (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {data.palavras_chave_humano.map(palavra => (
                                        <span key={palavra} className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs" style={{ background: 'var(--accent-light)', color: 'var(--accent)', border: '1px solid color-mix(in srgb, var(--accent) 28%, transparent)' }}>
                                            {palavra}
                                            <button type="button" onClick={() => removerPalavra(palavra)} className="rounded-full p-0.5 hover:bg-black/10" aria-label={`Remover ${palavra}`}>
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden>
                                                    <path d="M18 6 6 18M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </span>
                                    ))}
                                </div>
                            ) : (
                                <p className="mt-3 rounded-lg px-3 py-2 text-xs" style={{ background: 'var(--bg-surface-2)', color: 'var(--text-3)' }}>
                                    Nenhuma palavra configurada. O cliente ainda pode ser transferido pelas outras regras.
                                </p>
                            )}
                            {errors.palavras_chave_humano && <p className="mt-2 text-xs text-red-400">{errors.palavras_chave_humano}</p>}
                        </div>

                        <div className="rounded-xl p-4" style={{ border: '1px solid var(--border)', background: 'var(--bg-card)' }}>
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <p className="text-sm font-medium text-primary">Transferir quando estiver fechado</p>
                                    <p className="mt-0.5 text-xs leading-relaxed" style={{ color: 'var(--text-3)' }}>
                                        Aplica automaticamente o horário do estabelecimento exibido acima.
                                    </p>
                                </div>
                                <Toggle
                                    checked={data.transferir_fora_do_horario}
                                    onChange={value => setData('transferir_fora_do_horario', value)}
                                    ariaLabel="Transferir para a equipe quando o estabelecimento estiver fechado"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="label mb-1" htmlFor="triagem-mensagem">
                                Mensagem de transferência
                                <span className="ml-1 font-normal" style={{ color: 'var(--text-3)' }}>(opcional)</span>
                            </label>
                            <textarea
                                id="triagem-mensagem"
                                value={data.mensagem_transferencia}
                                onChange={event => setData('mensagem_transferencia', event.target.value)}
                                rows={3}
                                className="input resize-none"
                                placeholder="Informe ao cliente que a equipe continuará o atendimento."
                                maxLength={300}
                            />
                            <div className="mt-1 flex justify-between gap-3 text-xs" style={{ color: 'var(--text-3)' }}>
                                <span>Se ficar vazio, o sistema utiliza a mensagem padrão.</span>
                                <span>{data.mensagem_transferencia.length}/300</span>
                            </div>
                            {errors.mensagem_transferencia && <p className="mt-1 text-xs text-red-400">{errors.mensagem_transferencia}</p>}
                        </div>

                        <div className="rounded-xl p-4" style={{ background: 'var(--bg-surface-2)', border: '1px solid var(--border)' }}>
                            <p className="label mb-2">Como o cliente verá</p>
                            <p className="text-sm leading-relaxed" style={{ color: 'var(--text-2)' }}>{mensagemPreview}</p>
                        </div>

                        <details className="rounded-xl" style={{ border: '1px solid var(--border)' }}>
                            <summary className="cursor-pointer select-none px-4 py-3 text-sm font-medium text-primary">
                                Regra avançada
                            </summary>
                            <div className="px-4 pb-4" style={{ borderTop: '1px solid var(--border)' }}>
                                <label className="label mb-1 mt-4" htmlFor="triagem-tentativas">Tentativas sem entender antes de transferir</label>
                                <input
                                    id="triagem-tentativas"
                                    type="number"
                                    min={1}
                                    max={10}
                                    value={data.max_tentativas_sem_entender}
                                    onChange={event => setData('max_tentativas_sem_entender', Number(event.target.value))}
                                    className="input max-w-[140px]"
                                />
                                <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                    Evita que o cliente fique preso em uma conversa que o bot não consegue resolver.
                                </p>
                                {errors.max_tentativas_sem_entender && <p className="mt-1 text-xs text-red-400">{errors.max_tentativas_sem_entender}</p>}
                            </div>
                        </details>

                        <button type="submit" disabled={processing || !isDirty} className="btn-primary w-full justify-center py-2.5 disabled:cursor-not-allowed disabled:opacity-50">
                            {processing ? 'Salvando…' : isDirty ? 'Salvar regras de triagem' : 'Regras atualizadas'}
                        </button>
                    </div>
                </form>
            </div>
        </ConfiguracoesLayout>
    );
}
