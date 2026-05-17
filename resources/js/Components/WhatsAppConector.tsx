import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { StatusWhatsApp } from '@/types';

interface Props {
    status: StatusWhatsApp;
    instance: string | null;
}

export default function WhatsAppConector({ status: initialStatus, instance }: Props) {
    const [status, setStatus] = useState<StatusWhatsApp>(initialStatus);
    const [qrcode, setQrcode] = useState<string | null>(null);
    const [showModal, setShowModal] = useState(false);
    const [loadingQr, setLoadingQr] = useState(false);
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const stopPolling = () => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
        }
    };

    const startPolling = () => {
        stopPolling();
        pollingRef.current = setInterval(async () => {
            try {
                const res = await fetch(route('whatsapp.status'));
                const json = await res.json();
                setStatus(json.status);
                if (json.status === 'open') {
                    stopPolling();
                    setShowModal(false);
                    router.reload({ only: ['tenant'] });
                }
            } catch {
                // silencioso
            }
        }, 3000);
    };

    const conectar = async () => {
        setLoadingQr(true);
        setShowModal(true);
        try {
            const res = await fetch(route('whatsapp.qrcode'));
            const json = await res.json();
            setQrcode(json.qrcode ? `data:image/png;base64,${json.qrcode}` : null);
        } finally {
            setLoadingQr(false);
        }
        startPolling();
    };

    useEffect(() => () => stopPolling(), []);

    if (status === 'open') {
        return (
            <div className="flex items-center gap-3">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-700">
                    <span className="h-2 w-2 rounded-full bg-green-500" />
                    Conectado
                </span>
            </div>
        );
    }

    return (
        <>
            <button
                onClick={conectar}
                className="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
            >
                Conectar WhatsApp
            </button>

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <div className="rounded-xl bg-white p-8 shadow-xl w-80 text-center">
                        <h3 className="mb-4 font-semibold text-gray-800">Escanear QR Code</h3>
                        {loadingQr ? (
                            <p className="text-sm text-gray-500">Carregando QR Code...</p>
                        ) : qrcode ? (
                            <img src={qrcode} alt="QR Code WhatsApp" className="mx-auto w-56 h-56 rounded" />
                        ) : (
                            <p className="text-sm text-red-500">QR Code não disponível. Tente conectar novamente.</p>
                        )}
                        <p className="mt-3 text-xs text-gray-400">Aguardando conexão...</p>
                        <button
                            onClick={() => { stopPolling(); setShowModal(false); }}
                            className="mt-4 text-sm text-gray-500 underline"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}
