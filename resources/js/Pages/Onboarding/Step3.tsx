import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import ForceDark from '@/Components/ForceDark';
import { PageProps, TipoServico } from '@/types';

type DiasAtendimento = 'segunda_sexta' | 'segunda_sabado' | 'todos';
type PerfilRegras = 'flexivel' | 'equilibrado' | 'protegido';

interface ConfiguracaoExpressa {
    nome_item: string;
    nome_servico: string;
    duracao_minutos: number;
    valor: number;
    dias_atendimento: DiasAtendimento;
    hora_abertura: string;
    hora_fechamento: string;
    perfil_regras: PerfilRegras;
}

interface Props extends PageProps {
    tenant: {
        nome: string;
        tipo_servico: TipoServico;
    };
    defaults: ConfiguracaoExpressa;
}

const DIAS: { value: DiasAtendimento; label: string }[] = [
    { value: 'segunda_sexta', label: 'Segunda a sexta' },
    { value: 'segunda_sabado', label: 'Segunda a sábado' },
    { value: 'todos', label: 'Todos os dias' },
];

const REGRAS: { value: PerfilRegras; label: string; desc: string }[] = [
    { value: 'flexivel', label: 'Mais flexível', desc: 'Aceita horários de última hora e abre 60 dias.' },
    { value: 'equilibrado', label: 'Recomendado', desc: '30 min de antecedência e agenda aberta por 30 dias.' },
    { value: 'protegido', label: 'Mais protegido', desc: '2h de antecedência e 15 min entre atendimentos.' },
];

export default function OnboardingStep3({ tenant, defaults }: Props) {
    const { data, setData, post, processing, errors } = useForm<ConfiguracaoExpressa>(defaults);
    const usaRecurso = tenant.tipo_servico === 'quadra';
    const itemLabel = usaRecurso ? 'Nome do espaço principal' : 'Quem fará o primeiro atendimento?';
    const itemHint = usaRecurso ? 'Ex: Quadra de futsal' : 'Você poderá adicionar toda a equipe depois.';

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('onboarding.step3.store'));
    };

    return (
        <ForceDark>
            <div className="min-h-screen" style={{ background: 'var(--bg-app)' }}>
                <Head title="Configuração expressa" />

                <div className="mx-auto flex min-h-screen w-full max-w-5xl flex-col px-4 py-6 sm:px-6 sm:py-10">
                    <div className="mb-8 flex items-center justify-between gap-3">
                        <span className="text-lg text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                            Agendou
                        </span>
                        <span className="shrink-0 rounded-full px-3 py-1 text-xs" style={{ background: 'var(--jade-light)', color: 'var(--jade)' }}>
                            Etapa 2 de 2 · cerca de 2 minutos
                        </span>
                    </div>

                    <div className="grid flex-1 gap-8 lg:grid-cols-[minmax(0,1fr)_300px]">
                        <main className="min-w-0">
                            <div className="mb-6">
                                <p className="mb-2 text-xs font-semibold uppercase tracking-[0.12em]" style={{ color: 'var(--jade)' }}>
                                    Vamos deixar tudo pronto
                                </p>
                                <h1 className="text-3xl text-primary sm:text-4xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                                    Como funciona a agenda da {tenant.nome}?
                                </h1>
                                <p className="mt-2 max-w-2xl text-sm leading-6" style={{ color: 'var(--text-3)' }}>
                                    Já sugerimos uma configuração para o seu tipo de negócio. Confirme o básico agora; os detalhes ficam para depois.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-4">
                                <div className="card p-4 lg:hidden">
                                    <p className="text-sm font-medium text-primary">As sugestões abaixo já estão prontas para uso.</p>
                                    <p className="mt-1 text-xs leading-5" style={{ color: 'var(--text-3)' }}>Você pode aceitar agora e alterar qualquer detalhe depois no painel.</p>
                                    <button type="submit" disabled={processing} className="btn-primary mt-3 w-full justify-center py-3">
                                        {processing ? 'Preparando sua agenda…' : 'Usar configuração sugerida'}
                                    </button>
                                </div>

                                <section className="card p-4 sm:p-6">
                                    <div className="mb-4">
                                        <p className="text-sm font-semibold text-primary">Primeiro item da agenda</p>
                                        <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>O suficiente para você testar o primeiro agendamento.</p>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <label>
                                            <span className="label mb-1">{itemLabel}</span>
                                            <input className="input" value={data.nome_item} onChange={e => setData('nome_item', e.target.value)} />
                                            <span className="mt-1 block text-xs" style={{ color: 'var(--text-3)' }}>{itemHint}</span>
                                            {errors.nome_item && <span className="mt-1 block text-xs text-red-400">{errors.nome_item}</span>}
                                        </label>
                                        <label>
                                            <span className="label mb-1">{usaRecurso ? 'Tipo de reserva' : 'Primeiro serviço'}</span>
                                            <input className="input" value={data.nome_servico} onChange={e => setData('nome_servico', e.target.value)} />
                                            {errors.nome_servico && <span className="mt-1 block text-xs text-red-400">{errors.nome_servico}</span>}
                                        </label>
                                        <label>
                                            <span className="label mb-1">Duração</span>
                                            <select className="input" value={data.duracao_minutos} onChange={e => setData('duracao_minutos', Number(e.target.value))}>
                                                {[15, 30, 45, 50, 60, 90, 120].map(minutos => <option key={minutos} value={minutos}>{minutos} minutos</option>)}
                                            </select>
                                        </label>
                                        <label>
                                            <span className="label mb-1">Valor</span>
                                            <div className="relative">
                                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm" style={{ color: 'var(--text-3)' }}>R$</span>
                                                <input className="input pl-10" type="number" min={0} step="0.01" value={data.valor} onChange={e => setData('valor', Number(e.target.value))} />
                                            </div>
                                            {errors.valor && <span className="mt-1 block text-xs text-red-400">{errors.valor}</span>}
                                        </label>
                                    </div>
                                </section>

                                <section className="card p-4 sm:p-6">
                                    <p className="mb-4 text-sm font-semibold text-primary">Quando você atende?</p>
                                    <div className="grid gap-4 sm:grid-cols-[1fr_140px_140px]">
                                        <label>
                                            <span className="label mb-1">Dias</span>
                                            <select className="input" value={data.dias_atendimento} onChange={e => setData('dias_atendimento', e.target.value as DiasAtendimento)}>
                                                {DIAS.map(opcao => <option key={opcao.value} value={opcao.value}>{opcao.label}</option>)}
                                            </select>
                                        </label>
                                        <label>
                                            <span className="label mb-1">Das</span>
                                            <input className="input" type="time" value={data.hora_abertura} onChange={e => setData('hora_abertura', e.target.value)} />
                                        </label>
                                        <label>
                                            <span className="label mb-1">Até</span>
                                            <input className="input" type="time" value={data.hora_fechamento} onChange={e => setData('hora_fechamento', e.target.value)} />
                                            {errors.hora_fechamento && <span className="mt-1 block text-xs text-red-400">{errors.hora_fechamento}</span>}
                                        </label>
                                    </div>
                                </section>

                                <section className="card p-4 sm:p-6">
                                    <p className="text-sm font-semibold text-primary">Como prefere receber agendamentos?</p>
                                    <p className="mb-4 mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>Sem números técnicos. Escolha o estilo e cuidamos das regras.</p>
                                    <div className="grid gap-2 sm:grid-cols-3">
                                        {REGRAS.map(opcao => {
                                            const active = data.perfil_regras === opcao.value;
                                            return (
                                                <button
                                                    key={opcao.value}
                                                    type="button"
                                                    onClick={() => setData('perfil_regras', opcao.value)}
                                                    className="rounded-xl p-3 text-left transition-colors"
                                                    style={{
                                                        background: active ? 'var(--accent-light)' : 'var(--bg-surface-2)',
                                                        border: `1px solid ${active ? 'var(--accent)' : 'var(--border)'}`,
                                                    }}
                                                >
                                                    <span className="block text-sm font-medium text-primary">{opcao.label}</span>
                                                    <span className="mt-1 block text-xs leading-5" style={{ color: 'var(--text-3)' }}>{opcao.desc}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </section>

                                <button type="submit" disabled={processing} className="btn-primary w-full justify-center py-3 text-base">
                                    {processing ? 'Preparando seu Agendou…' : 'Criar minha agenda →'}
                                </button>
                            </form>
                        </main>

                        <aside className="hidden lg:block lg:pt-24">
                            <div className="sticky top-6 rounded-2xl p-5" style={{ background: 'var(--jade-light)', border: '1px solid rgba(0,168,132,.2)' }}>
                                <p className="text-sm font-semibold" style={{ color: 'var(--jade)' }}>O Agendou fará por você</p>
                                <ul className="mt-4 space-y-3 text-sm" style={{ color: 'var(--text-2)' }}>
                                    {[
                                        'Criar o primeiro item agendável',
                                        'Montar os horários da semana',
                                        'Aplicar regras recomendadas',
                                        'Escrever a saudação do assistente',
                                        'Liberar o simulador para teste',
                                    ].map(item => (
                                        <li key={item} className="flex gap-2">
                                            <span style={{ color: 'var(--jade)' }}>✓</span>
                                            <span>{item}</span>
                                        </li>
                                    ))}
                                </ul>
                                <p className="mt-5 text-xs leading-5" style={{ color: 'var(--text-3)' }}>
                                    Nada será enviado aos seus clientes até você conectar o WhatsApp.
                                </p>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </ForceDark>
    );
}
