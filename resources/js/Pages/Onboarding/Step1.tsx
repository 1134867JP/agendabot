import { Head, Link, useForm } from '@inertiajs/react';
import { TipoServico } from '@/types';
import { FormEventHandler } from 'react';
import TipoServicoSelector from '@/Components/TipoServicoSelector';

export default function OnboardingStep1() {
    const { data, setData, post, processing, errors } = useForm({
        nome_usuario:               '',
        email:                      '',
        senha:                      '',
        senha_confirmation:         '',
        nome_estabelecimento:       '',
        tipo_servico:               'barbeiro' as TipoServico,
        tipo_servico_personalizado: '',
        telefone:                   '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('onboarding.step1'));
    };

    return (
        <div className="flex min-h-screen flex-col" style={{ background: 'var(--bg-app)' }}>
            <Head title="Criar conta" />

            {/* Progress bar */}
            <div className="h-0.5 w-full" style={{ background: 'var(--border)' }}>
                <div className="h-0.5 w-1/3 transition-all" style={{ background: 'var(--accent)' }} />
            </div>

            <div className="flex flex-1 items-start justify-center px-4 py-10">
                <div className="w-full max-w-md">
                    {/* Header */}
                    <div className="mb-8">
                        <p className="mb-1 text-[10px] font-medium uppercase tracking-widest" style={{ color: 'var(--accent)' }}>
                            Passo 1 de 3
                        </p>
                        <h1 className="text-3xl text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                            Crie sua conta
                        </h1>
                        <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>14 dias grátis. Sem cartão para começar.</p>
                    </div>

                    <form onSubmit={submit} className="card p-7 space-y-5">
                        <div className="space-y-4">
                            <div>
                                <label className="label mb-1" htmlFor="nome_usuario">Nome completo</label>
                                <input id="nome_usuario" type="text" value={data.nome_usuario}
                                    onChange={e => setData('nome_usuario', e.target.value)}
                                    className="input" autoFocus required />
                                {errors.nome_usuario && <p className="mt-1 text-xs text-red-400">{errors.nome_usuario}</p>}
                            </div>

                            <div>
                                <label className="label mb-1" htmlFor="email">E-mail</label>
                                <input id="email" type="email" value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                    className="input" required />
                                {errors.email && <p className="mt-1 text-xs text-red-400">{errors.email}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="label mb-1" htmlFor="senha">Senha</label>
                                    <input id="senha" type="password" value={data.senha}
                                        onChange={e => setData('senha', e.target.value)}
                                        className="input" required />
                                    {errors.senha && <p className="mt-1 text-xs text-red-400">{errors.senha}</p>}
                                </div>
                                <div>
                                    <label className="label mb-1" htmlFor="senha_confirmation">Confirmar</label>
                                    <input id="senha_confirmation" type="password" value={data.senha_confirmation}
                                        onChange={e => setData('senha_confirmation', e.target.value)}
                                        className="input" required />
                                </div>
                            </div>
                        </div>

                        <div className="space-y-4 pt-4" style={{ borderTop: '1px solid var(--border)' }}>
                            <p className="text-[10px] font-medium uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>
                                Seu estabelecimento
                            </p>

                            <div>
                                <label className="label mb-1" htmlFor="nome_estabelecimento">Nome</label>
                                <input id="nome_estabelecimento" type="text" value={data.nome_estabelecimento}
                                    onChange={e => setData('nome_estabelecimento', e.target.value)}
                                    className="input" placeholder="Ex: Barbearia do Carlos" required />
                                {errors.nome_estabelecimento && <p className="mt-1 text-xs text-red-400">{errors.nome_estabelecimento}</p>}
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
                                <label className="label mb-1" htmlFor="telefone">Telefone</label>
                                <input id="telefone" type="tel" value={data.telefone}
                                    onChange={e => setData('telefone', e.target.value)}
                                    className="input" placeholder="(51) 99999-9999" required />
                                {errors.telefone && <p className="mt-1 text-xs text-red-400">{errors.telefone}</p>}
                            </div>
                        </div>

                        <button type="submit" disabled={processing} className="btn-primary w-full justify-center py-3 text-base">
                            {processing ? 'Criando conta…' : 'Continuar →'}
                        </button>
                    </form>

                    <p className="mt-5 text-center text-sm" style={{ color: 'var(--text-3)' }}>
                        Já tem conta?{' '}
                        <Link href={route('login')} className="transition-colors" style={{ color: 'var(--accent)' }}>
                            Entrar
                        </Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
