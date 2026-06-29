import { useEffect, useRef, useState, useCallback } from 'react';

export interface ConversaPreview {
    id: number;
    nome: string;
    telefone: string;
    preview: string;
    tipo: string;
    remetente: string;
    em: string | null;
}

interface Notificacoes {
    conversas_nao_lidas: number;
    preview: ConversaPreview[];
}

interface UseNotificacoesReturn {
    conversasNaoLidas: number;
    conversasPreview: ConversaPreview[];
    novaMensagem: boolean;
    resetarNovaMensagem: () => void;
}

const INTERVALO_MS = 8000;

export function useNotificacoes(ativo: boolean): UseNotificacoesReturn {
    const [conversasNaoLidas, setConversasNaoLidas] = useState(0);
    const [conversasPreview,  setConversasPreview]  = useState<ConversaPreview[]>([]);
    const [novaMensagem,      setNovaMensagem]      = useState(false);
    const anteriorRef = useRef<number | null>(null);
    const timeoutRef  = useRef<ReturnType<typeof setTimeout> | null>(null);

    const resetarNovaMensagem = useCallback(() => setNovaMensagem(false), []);

    const buscar = useCallback(async () => {
        if (!ativo) return;

        try {
            const res = await fetch(route('tenant.conversas.notificacoes'), {
                credentials: 'include',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!res.ok) return;

            const data: Notificacoes = await res.json();
            const atual = data.conversas_nao_lidas ?? 0;

            setConversasNaoLidas(atual);
            setConversasPreview(data.preview ?? []);

            if (anteriorRef.current !== null && atual > anteriorRef.current) {
                setNovaMensagem(true);
            }

            anteriorRef.current = atual;
        } catch {
            // silencioso — não interrompe UX se falhar
        }
    }, [ativo]);

    useEffect(() => {
        if (!ativo) return;

        buscar();

        const agendar = () => {
            timeoutRef.current = setTimeout(() => {
                buscar().finally(agendar);
            }, INTERVALO_MS);
        };

        agendar();

        return () => {
            if (timeoutRef.current) clearTimeout(timeoutRef.current);
        };
    }, [ativo, buscar]);

    return { conversasNaoLidas, conversasPreview, novaMensagem, resetarNovaMensagem };
}
