import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Tenant } from '@/types';

interface Props extends PageProps {
    tenant: Tenant;
    webhook_url: string;
}

export default function WhatsAppPage({ tenant }: Props) {
    const [conectado, setConectado] = useState(tenant.whatsapp_conectado);
    const [qrcode, setQrcode] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [erro, setErro] = useState('');
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopPolling = () => {
        if (pollingRef.current) clearInterval(pollingRef.current);
        pollingRef.current = null;
    };

    const verificarStatus = async () => {
        try {
            const res = await fetch(route('tenant.whatsapp.status'), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json();
            if (json.status === 'open') {
                setConectado(true);
                setQrcode(null);
            } else {
                setConectado(false);
            }
        } catch {
            // silently ignore
        }
    };

    // Polling contínuo sempre ativo: detecta conexão (após QR) e desconexão (celular desconectado)
    useEffect(() => {
        pollingRef.current = setInterval(verificarStatus, 5000);
        return () => stopPolling();
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const conectar = async () => {
        setErro('');
        setLoading(true);
        try {
            const res = await fetch(route('tenant.whatsapp.qrcode'), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const json = await res.json();
            if (json.connected) {
                setConectado(true);
            } else if (json.qrcode) {
                setQrcode(json.qrcode);
            } else {
                setErro('Não foi possível gerar o QR Code. Tente novamente em alguns instantes.');
            }
        } catch {
            setErro('Erro de conexão. Verifique sua internet e tente novamente.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout title="WhatsApp" subtitle="Conecte seu número para receber agendamentos automáticos">
            <Head title="WhatsApp" />

            <div className="mx-auto max-w-lg">
                {/* Status card */}
                <div className="card p-8 text-center">
                    {/* Status indicator */}
                    <div className="mb-6 flex flex-col items-center gap-3">
                        <div
                            className="flex h-20 w-20 items-center justify-center rounded-full transition-colors"
                            style={{ background: conectado ? 'rgba(110,231,183,0.1)' : 'var(--bg-surface-2)', border: '1px solid var(--border)' }}
                        >
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"
                                    fill={conectado ? 'var(--emerald)' : 'var(--text-3)'}
                                />
                                <path
                                    d="M12 2C6.48 2 2 6.48 2 12c0 1.793.478 3.476 1.315 4.93L2 22l5.23-1.298A9.953 9.953 0 0012 22c5.52 0 10-4.48 10-10S17.52 2 12 2zm0 18a8 8 0 01-4.065-1.107l-.291-.173-3.011.748.792-2.944-.19-.302A7.97 7.97 0 014 12c0-4.418 3.582-8 8-8s8 3.582 8 8-3.582 8-8 8z"
                                    fill={conectado ? 'var(--emerald)' : 'var(--text-3)'}
                                />
                            </svg>
                        </div>

                        <div>
                            <h2
                                className="text-xl font-semibold text-primary"
                                style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}
                            >
                                {conectado ? 'WhatsApp conectado' : 'WhatsApp desconectado'}
                            </h2>
                            <p className="mt-1 text-sm" style={{ color: 'var(--text-3)' }}>
                                {conectado
                                    ? 'Seu estabelecimento está recebendo agendamentos via WhatsApp.'
                                    : 'Conecte seu WhatsApp para começar a receber agendamentos automaticamente.'}
                            </p>
                        </div>

                        {/* Status badge */}
                        <span className={`badge ${conectado ? 'badge-green' : 'badge-red'}`}>
                            <span className={`h-1.5 w-1.5 rounded-full ${conectado ? 'bg-emerald-500' : 'bg-red-400'}`} />
                            {conectado ? 'Conectado' : 'Desconectado'}
                        </span>
                    </div>

                    {/* Error */}
                    {erro && (
                        <div className="mb-4 rounded-lg px-4 py-3 text-sm text-red-400" style={{ background: 'rgba(239,68,68,0.08)', border: '1px solid rgba(239,68,68,0.2)' }}>
                            {erro}
                        </div>
                    )}

                    {/* Action */}
                    {!conectado && (
                        <button
                            onClick={conectar}
                            disabled={loading}
                            className="btn-primary w-full justify-center py-3 text-base"
                            style={{ background: '#25D366', borderRadius: '0.75rem' }}
                        >
                            {loading ? (
                                <>
                                    <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                    Gerando QR Code…
                                </>
                            ) : (
                                'Conectar WhatsApp'
                            )}
                        </button>
                    )}

                    {conectado && (
                        <p className="text-sm" style={{ color: 'var(--text-3)' }}>
                            Os clientes já podem enviar mensagens para o seu número para agendar.
                        </p>
                    )}
                </div>

                {/* How it works */}
                <div className="card mt-6 p-6">
                    <h3 className="mb-4 text-xs font-semibold uppercase tracking-wide" style={{ color: 'var(--text-3)' }}>
                        Como funciona
                    </h3>
                    <ol className="space-y-3">
                        {[
                            { n: '1', text: 'Clique em "Conectar WhatsApp" e escaneie o QR Code com o seu celular.' },
                            { n: '2', text: 'Seus clientes mandam mensagem no seu WhatsApp normalmente, como se fosse falar com você.' },
                            { n: '3', text: 'O assistente inteligente responde automaticamente, pergunta o serviço, a data e o horário.' },
                            { n: '4', text: 'O agendamento aparece aqui no painel em tempo real.' },
                        ].map(item => (
                            <li key={item.n} className="flex items-start gap-3">
                                <span
                                    className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold text-primary"
                                    style={{ background: 'var(--accent)' }}
                                >
                                    {item.n}
                                </span>
                                <p className="pt-0.5 text-sm" style={{ color: 'var(--text-2)' }}>{item.text}</p>
                            </li>
                        ))}
                    </ol>
                </div>
            </div>

            {/* QR Code Modal */}
            {qrcode && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 backdrop-blur-sm">
                    <div className="w-full max-w-xs rounded-2xl p-7 text-center shadow-2xl" style={{ background: 'var(--bg-surface)', border: '1px solid var(--border-strong)' }}>
                        <h3
                            className="mb-2 text-xl font-semibold text-primary"
                            style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}
                        >
                            Escaneie com seu celular
                        </h3>
                        <p className="mb-5 text-sm" style={{ color: 'var(--text-3)' }}>
                            Abra o WhatsApp → Aparelhos conectados → Conectar um aparelho
                        </p>
                        <img
                            src={qrcode}
                            alt="QR Code"
                            className="mx-auto h-56 w-56 rounded-xl"
                            style={{ border: '1px solid var(--border)' }}
                        />
                        <div className="mt-4 flex items-center justify-center gap-1.5 text-sm" style={{ color: 'var(--text-3)' }}>
                            <svg className="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            Aguardando conexão…
                        </div>
                        <button
                            onClick={() => { setQrcode(null); stopPolling(); }}
                            className="btn-secondary mt-5 w-full justify-center"
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
