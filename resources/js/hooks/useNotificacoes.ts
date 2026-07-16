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
    ultima_conversa_id: number | null;
    ultima_mensagem_id: number | null;
    ultima_mensagem_em: string | null;
}

interface UseNotificacoesReturn {
    conversasNaoLidas: number;
    conversasPreview: ConversaPreview[];
    novaMensagem: boolean;
    ultimaConversaId: number | null;
    resetarNovaMensagem: () => void;
}

const INTERVALO_MS = 5000;

export function useNotificacoes(ativo: boolean): UseNotificacoesReturn {
    const [conversasNaoLidas, setConversasNaoLidas] = useState(0);
    const [conversasPreview, setConversasPreview] = useState<ConversaPreview[]>([]);
    const [novaMensagem, setNovaMensagem] = useState(false);
    const [ultimaConversaId, setUltimaConversaId] = useState<number | null>(null);
    const assinaturaAnteriorRef = useRef<string | null>(null);
    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const resetarNovaMensagem = useCallback(() => setNovaMensagem(false), []);

    const buscar = useCallback(async () => {
        if (!ativo) return;

        try {
            const res = await fetch(route('tenant.conversas.notificacoes'), {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!res.ok) return;

            const data: Notificacoes = await res.json();
            const atual = data.conversas_nao_lidas ?? 0;
            const ultimaId = data.ultima_conversa_id ?? null;
            const assinatura = `${data.ultima_mensagem_id ?? ''}:${ultimaId ?? ''}:${data.ultima_mensagem_em ?? ''}`;

            setConversasNaoLidas(atual);
            setConversasPreview(data.preview ?? []);
            setUltimaConversaId(ultimaId);

            // O total de não lidas pode permanecer igual quando uma conversa aberta
            // recebe mensagem. A assinatura da mensagem mais recente detecta esse caso.
            if (assinaturaAnteriorRef.current !== null && assinatura !== assinaturaAnteriorRef.current) {
                setNovaMensagem(true);
            }

            assinaturaAnteriorRef.current = assinatura;
        } catch {
            // A tela continua funcional se uma consulta de atualização falhar.
        }
    }, [ativo]);

    useEffect(() => {
        if (!ativo) return;

        void buscar();

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

    return {
        conversasNaoLidas,
        conversasPreview,
        novaMensagem,
        ultimaConversaId,
        resetarNovaMensagem,
    };
}
