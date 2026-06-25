import { Head, router } from '@inertiajs/react';
import { PageProps, Tenant } from '@/types';
import { useState } from 'react';

interface Plano {
    nome: string;
    valor: number;
}

interface Props extends PageProps {
    tenant: Tenant;
    plano: Plano;
}

export default function Renovar({ tenant, plano }: Props) {
    const [processing, setProcessing] = useState(false);
    const [confirmarCancelamento, setConfirmarCancelamento] = useState(false);

    const renovar = () => {
        setProcessing(true);
        router.post(route('tenant.renovar.store'));
    };

    const cancelar = () => {
        router.get(route('tenant.cancelar'));
    };

    const valorStr = plano?.valor.toFixed(2).replace('.', ',') ?? '79,90';

    return (
        <div
            className="flex min-h-screen items-center justify-center px-4"
            style={{ background: 'var(--bg-app)' }}
        >
            <Head title="Acesso suspenso" />

            <div className="w-full max-w-md text-center">
                <div
                    className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full text-3xl"
                    style={{ background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.2)' }}
                >
                    🔒
                </div>

                <h1 className="text-3xl text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>
                    Acesso suspenso
                </h1>
                <p className="mt-2 text-sm" style={{ color: 'var(--text-3)' }}>
                    Renove sua assinatura para continuar usando o AgendaBot.
                </p>

                <div className="card mx-auto mt-6 max-w-xs p-5">
                    <p className="text-[10px] font-medium uppercase tracking-widest" style={{ color: 'var(--text-3)' }}>
                        {tenant.nome}
                    </p>
                    <p className="mt-1 font-medium text-primary">{plano?.nome ?? 'Básico'}</p>
                    <p className="mt-1 text-2xl font-bold text-primary">
                        R${valorStr}
                        <span className="text-sm font-normal" style={{ color: 'var(--text-3)' }}>/mês</span>
                    </p>
                </div>

                <p className="mt-4 text-xs" style={{ color: 'var(--text-3)' }}>
                    O bot de WhatsApp também está pausado durante a suspensão.
                </p>

                <button
                    onClick={renovar}
                    disabled={processing}
                    className="btn-primary mt-6 w-full justify-center py-3 text-base"
                >
                    {processing ? 'Aguarde…' : `Renovar agora — R$${valorStr}`}
                </button>

                {!confirmarCancelamento ? (
                    <button
                        onClick={() => setConfirmarCancelamento(true)}
                        className="mt-4 text-xs transition-colors hover:text-[var(--text-2)]"
                        style={{ color: 'var(--text-3)' }}
                    >
                        Cancelar assinatura definitivamente
                    </button>
                ) : (
                    <div
                        className="mt-4 rounded-xl p-4"
                        style={{ background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)' }}
                    >
                        <p className="text-sm font-medium text-red-400">Tem certeza?</p>
                        <p className="mt-1 text-xs" style={{ color: 'var(--text-3)' }}>
                            Todos os dados serão mantidos por 30 dias.
                        </p>
                        <div className="mt-3 flex gap-2">
                            <button
                                onClick={cancelar}
                                className="flex-1 rounded-lg py-2 text-sm text-red-400 transition-colors"
                                style={{ background: 'rgba(239,68,68,0.12)', border: '1px solid rgba(239,68,68,0.25)' }}
                            >
                                Sim, cancelar
                            </button>
                            <button
                                onClick={() => setConfirmarCancelamento(false)}
                                className="flex-1 rounded-lg py-2 text-sm transition-colors"
                                style={{ background: 'var(--bg-surface-2)', border: '1px solid var(--border-strong)', color: 'var(--text-2)' }}
                            >
                                Manter
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
