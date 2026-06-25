import { Head, router, useForm } from '@inertiajs/react';
import { PageProps } from '@/types';
import { FormEventHandler } from 'react';
import ForceDark from '@/Components/ForceDark';

interface Props extends PageProps {
    tenant: {
        nome: string;
        tipo_servico: string;
        nome_agente: string | null;
        tom_voz: string | null;
        instrucoes_extras: string | null;
    };
}

const TOM_OPTIONS = [
    {
        value: 'semiformal',
        label: 'Casual',
        desc: 'Simpático e próximo',
        exemplo: '"Oi! Que bom ter você por aqui 😊 Como posso ajudar?"',
    },
    {
        value: 'formal',
        label: 'Profissional',
        desc: 'Cordial e objetivo',
        exemplo: '"Olá! Seja bem-vindo. Em que posso ajudá-lo hoje?"',
    },
    {
        value: 'descontraido',
        label: 'Descontraído',
        desc: 'Bem-humorado e informal',
        exemplo: '"Eaí! Bora agendar? Me diz o que você precisa 🤙"',
    },
];

export default function OnboardingStep3({ tenant }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        bot_nome:     tenant.nome_agente     || `Assistente da ${tenant.nome}`,
        bot_saudacao: tenant.instrucoes_extras || `Olá! Bem-vindo à ${tenant.nome}. Como posso ajudar?`,
        bot_tom:      tenant.tom_voz         || 'semiformal',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('onboarding.step3.store'));
    };

    const pular = () => {
        router.post(route('onboarding.step3.store'), data);
    };

    return (
        <ForceDark>
        <div className="flex min-h-screen flex-col" style={{ background: 'var(--bg-app)' }}>
            <Head title="Personalize seu bot" />

            <div className="h-0.5 w-full" style={{ background: 'var(--border)' }}>
                <div className="h-0.5 w-full transition-all" style={{ background: 'var(--accent)' }} />
            </div>

            <div className="flex flex-1 items-start justify-center px-4 py-10">
                <div className="w-full max-w-lg">
                    <div className="mb-8">
                        <p className="mb-1 text-[10px] font-medium uppercase tracking-widest" style={{ color: 'var(--accent)' }}>
                            Passo 3 de 3
                        </p>
                        <h1 className="text-3xl text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                            Personalize seu bot
                        </h1>
                        <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>
                            É a primeira coisa que seus clientes vão ver. Você pode mudar depois.
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-6">

                        {/* Nome do bot */}
                        <div className="card p-6 space-y-4">
                            <div>
                                <label className="label mb-1" htmlFor="bot_nome">
                                    Nome do assistente
                                </label>
                                <input
                                    id="bot_nome"
                                    type="text"
                                    value={data.bot_nome}
                                    onChange={e => setData('bot_nome', e.target.value)}
                                    className="input"
                                    placeholder={`Assistente da ${tenant.nome}`}
                                    maxLength={80}
                                    required
                                />
                                <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                                    Como o bot se apresenta para o cliente ("Olá, sou a Ana…")
                                </p>
                                {errors.bot_nome && <p className="mt-1 text-xs text-red-400">{errors.bot_nome}</p>}
                            </div>

                            <div>
                                <label className="label mb-1" htmlFor="bot_saudacao">
                                    Mensagem de boas-vindas
                                </label>
                                <textarea
                                    id="bot_saudacao"
                                    value={data.bot_saudacao}
                                    onChange={e => setData('bot_saudacao', e.target.value)}
                                    className="input min-h-[80px] resize-none"
                                    placeholder="Olá! Como posso ajudar?"
                                    maxLength={500}
                                    required
                                />
                                <p className="mt-1 flex justify-between text-xs" style={{ color: 'var(--text-3)' }}>
                                    <span>Enviada quando o cliente manda a primeira mensagem</span>
                                    <span>{data.bot_saudacao.length}/500</span>
                                </p>
                                {errors.bot_saudacao && <p className="mt-1 text-xs text-red-400">{errors.bot_saudacao}</p>}
                            </div>
                        </div>

                        {/* Tom */}
                        <div className="card p-6">
                            <p className="label mb-3">Tom de conversa</p>
                            <div className="space-y-2">
                                {TOM_OPTIONS.map(opt => (
                                    <label
                                        key={opt.value}
                                        className="flex cursor-pointer items-start gap-3 rounded-lg p-3 transition-colors"
                                        style={{
                                            background: data.bot_tom === opt.value ? 'var(--accent-light)' : 'var(--bg-surface-2)',
                                            border: `1px solid ${data.bot_tom === opt.value ? 'var(--accent)' : 'var(--border)'}`,
                                        }}
                                    >
                                        <input
                                            type="radio"
                                            name="bot_tom"
                                            value={opt.value}
                                            checked={data.bot_tom === opt.value}
                                            onChange={() => setData('bot_tom', opt.value)}
                                            className="mt-0.5 accent-[var(--accent)]"
                                        />
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-medium text-primary">{opt.label}</span>
                                                <span className="text-xs" style={{ color: 'var(--text-3)' }}>{opt.desc}</span>
                                            </div>
                                            <p
                                                className="mt-1 truncate text-xs italic"
                                                style={{ color: 'var(--text-3)' }}
                                            >
                                                {opt.exemplo}
                                            </p>
                                        </div>
                                    </label>
                                ))}
                            </div>
                            {errors.bot_tom && <p className="mt-2 text-xs text-red-400">{errors.bot_tom}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="btn-primary w-full justify-center py-3 text-base"
                        >
                            {processing ? 'Salvando…' : 'Finalizar configuração →'}
                        </button>
                    </form>

                    <button
                        onClick={pular}
                        disabled={processing}
                        className="mt-4 w-full text-center text-sm transition-colors disabled:opacity-50"
                        style={{ color: 'var(--text-3)' }}
                        onMouseEnter={e => (e.currentTarget.style.color = 'var(--text-2)')}
                        onMouseLeave={e => (e.currentTarget.style.color = 'var(--text-3)')}
                    >
                        Pular e usar padrão →
                    </button>
                </div>
            </div>
        </div>
        </ForceDark>
    );
}
