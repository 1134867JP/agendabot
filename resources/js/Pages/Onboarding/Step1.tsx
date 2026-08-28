import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import ForceDark from '@/Components/ForceDark';
import TipoServicoSelector from '@/Components/TipoServicoSelector';
import { TipoServico } from '@/types';

function formatarTelefone(valor: string): string {
    let numeros = valor.replace(/\D/g, '');
    if (numeros.startsWith('55') && numeros.length > 11) numeros = numeros.slice(2);
    numeros = numeros.slice(0, 11);

    if (numeros.length === 0) return '';
    if (numeros.length <= 2) return `(${numeros}`;

    const ddd = numeros.slice(0, 2);
    const local = numeros.slice(2);
    if (local.length <= 4) return `(${ddd}) ${local}`;
    if (local.length <= 8) return `(${ddd}) ${local.slice(0, 4)}-${local.slice(4)}`;

    return `(${ddd}) ${local.slice(0, 5)}-${local.slice(5)}`;
}

export default function OnboardingStep1() {
    const [mostrarSenha, setMostrarSenha] = useState(false);
    const { data, setData, transform, post, processing, errors } = useForm({
        nome_usuario: '',
        email: '',
        senha: '',
        senha_confirmation: '',
        nome_estabelecimento: '',
        tipo_servico: 'barbeiro' as TipoServico,
        tipo_servico_personalizado: '',
        telefone: '',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        transform(values => ({ ...values, senha_confirmation: values.senha }));
        post(route('onboarding.step1'), {
            preserveScroll: true,
            onError: () => window.requestAnimationFrame(() => {
                const primeiroInvalido = document.querySelector<HTMLElement>('[aria-invalid="true"]');
                primeiroInvalido?.focus();
            }),
        });
    };

    return (
        <ForceDark>
            <div className="min-h-screen w-full max-w-full overflow-x-hidden" style={{ background: 'var(--bg-app)' }}>
                <Head title="Criar conta" />

                <header className="border-b px-4 py-4 sm:px-6" style={{ borderColor: 'var(--border)' }}>
                    <div className="mx-auto flex w-full max-w-md items-center justify-between gap-4">
                        <Link href={route('home')} className="text-xl text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                            Agendou
                        </Link>
                        <span className="text-xs font-medium" style={{ color: 'var(--text-3)' }}>Etapa 1 de 2</span>
                    </div>
                    <div
                        className="mx-auto mt-3 h-1 w-full max-w-md overflow-hidden rounded-full"
                        style={{ background: 'var(--border)' }}
                        role="progressbar"
                        aria-label="Progresso do cadastro"
                        aria-valuemin={1}
                        aria-valuemax={2}
                        aria-valuenow={1}
                    >
                        <div className="h-full w-1/2 rounded-full" style={{ background: 'var(--accent)' }} />
                    </div>
                </header>

                <div className="mx-auto w-full max-w-md px-4 py-6 sm:py-10">
                    <div className="mb-6">
                        <h1 className="text-2xl text-primary sm:text-3xl" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                            Deixe seu estabelecimento pronto para agendar
                        </h1>
                        <p className="mt-2 text-sm leading-6" style={{ color: 'var(--text-3)' }}>
                            Leva menos de um minuto. O teste é gratuito por 14 dias e não pede cartão.
                        </p>
                    </div>

                    <form onSubmit={submit} className="card space-y-5 p-4 sm:p-7">
                        <section className="space-y-4">
                            <div>
                                <p className="text-sm font-semibold text-primary">Sobre o estabelecimento</p>
                                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>Esses dados personalizam sua agenda desde o primeiro acesso.</p>
                            </div>

                            <div>
                                <label className="label mb-1" htmlFor="nome_estabelecimento">Nome do estabelecimento</label>
                                <input
                                    id="nome_estabelecimento"
                                    type="text"
                                    value={data.nome_estabelecimento}
                                    onChange={event => setData('nome_estabelecimento', event.target.value)}
                                    className="input"
                                    placeholder="Ex: Barbearia do Carlos"
                                    autoComplete="organization"
                                    autoCapitalize="words"
                                    aria-invalid={Boolean(errors.nome_estabelecimento)}
                                    autoFocus
                                    required
                                />
                                {errors.nome_estabelecimento && <p className="mt-1 text-xs text-red-400">{errors.nome_estabelecimento}</p>}
                            </div>

                            <div>
                                <label className="label mb-2">Qual é o tipo de atendimento?</label>
                                <TipoServicoSelector
                                    value={data.tipo_servico}
                                    onChange={value => setData('tipo_servico', value as TipoServico)}
                                    customValue={data.tipo_servico_personalizado}
                                    onChangeCustom={value => setData('tipo_servico_personalizado', value)}
                                    error={errors.tipo_servico || errors.tipo_servico_personalizado}
                                />
                            </div>

                            <div>
                                <label className="label mb-1" htmlFor="telefone">WhatsApp do estabelecimento</label>
                                <input
                                    id="telefone"
                                    type="tel"
                                    inputMode="tel"
                                    value={data.telefone}
                                    onChange={event => setData('telefone', formatarTelefone(event.target.value))}
                                    className="input"
                                    placeholder="(51) 99999-9999"
                                    autoComplete="tel-national"
                                    aria-invalid={Boolean(errors.telefone)}
                                    maxLength={15}
                                    required
                                />
                                {errors.telefone
                                    ? <p className="mt-1 text-xs text-red-400">{errors.telefone}</p>
                                    : <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>Use o número que seus clientes já conhecem.</p>}
                            </div>
                        </section>

                        <section className="space-y-4 border-t pt-5" style={{ borderColor: 'var(--border)' }}>
                            <div>
                                <p className="text-sm font-semibold text-primary">Seus dados de acesso</p>
                                <p className="mt-0.5 text-xs" style={{ color: 'var(--text-3)' }}>Você usará o e-mail e a senha para entrar no painel.</p>
                            </div>

                            <div>
                                <label className="label mb-1" htmlFor="nome_usuario">Seu nome</label>
                                <input
                                    id="nome_usuario"
                                    type="text"
                                    value={data.nome_usuario}
                                    onChange={event => setData('nome_usuario', event.target.value)}
                                    className="input"
                                    placeholder="Nome e sobrenome"
                                    autoComplete="name"
                                    autoCapitalize="words"
                                    aria-invalid={Boolean(errors.nome_usuario)}
                                    required
                                />
                                {errors.nome_usuario && <p className="mt-1 text-xs text-red-400">{errors.nome_usuario}</p>}
                            </div>

                            <div>
                                <label className="label mb-1" htmlFor="email">E-mail</label>
                                <input
                                    id="email"
                                    type="email"
                                    inputMode="email"
                                    value={data.email}
                                    onChange={event => setData('email', event.target.value)}
                                    className="input"
                                    placeholder="voce@empresa.com.br"
                                    autoComplete="email"
                                    autoCapitalize="none"
                                    spellCheck={false}
                                    aria-invalid={Boolean(errors.email)}
                                    required
                                />
                                {errors.email && <p className="mt-1 text-xs text-red-400">{errors.email}</p>}
                            </div>

                            <div>
                                <label className="label mb-1" htmlFor="senha">Crie uma senha</label>
                                <div className="relative">
                                    <input
                                        id="senha"
                                        type={mostrarSenha ? 'text' : 'password'}
                                        value={data.senha}
                                        onChange={event => setData('senha', event.target.value)}
                                        className="input pr-20"
                                        autoComplete="new-password"
                                        minLength={8}
                                        aria-invalid={Boolean(errors.senha)}
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setMostrarSenha(value => !value)}
                                        className="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium"
                                        style={{ color: 'var(--accent)' }}
                                        aria-controls="senha"
                                        aria-pressed={mostrarSenha}
                                    >
                                        {mostrarSenha ? 'Ocultar' : 'Mostrar'}
                                    </button>
                                </div>
                                {errors.senha
                                    ? <p className="mt-1 text-xs text-red-400">{errors.senha}</p>
                                    : <p className="mt-1 text-xs" style={{ color: data.senha.length >= 8 ? 'var(--jade)' : 'var(--text-3)' }}>Use pelo menos 8 caracteres.</p>}
                            </div>
                        </section>

                        <button type="submit" disabled={processing} className="btn-primary min-h-12 w-full justify-center py-3 text-base">
                            {processing ? 'Salvando seus dados…' : 'Continuar para configurar a agenda'}
                        </button>
                    </form>

                    <p className="mt-5 text-center text-sm" style={{ color: 'var(--text-3)' }}>
                        Já tem conta?{' '}
                        <Link href={route('login')} className="font-medium transition-colors" style={{ color: 'var(--accent)' }}>
                            Entrar
                        </Link>
                    </p>
                </div>
            </div>
        </ForceDark>
    );
}
