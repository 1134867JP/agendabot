// Fonte única de verdade para as opções de tom de voz do bot.
// Consumida tanto pelo onboarding (Onboarding/Step3) quanto pelas configurações
// (Tenant/Configuracoes), para que os rótulos não divirjam entre as telas.

export type TomVoz = 'formal' | 'semiformal' | 'descontraido';

export interface TomVozOption {
    value: TomVoz;
    label: string;
    /** Descrição curta do estilo. */
    desc: string;
    /** Exemplo de fala, usado nas telas que mostram um preview. */
    exemplo: string;
}

export const TONS_VOZ: TomVozOption[] = [
    {
        value: 'formal',
        label: 'Formal',
        desc: 'Profissional, sem emojis, "Senhor/Senhora"',
        exemplo: '"Olá! Seja bem-vindo. Em que posso ajudá-lo hoje?"',
    },
    {
        value: 'semiformal',
        label: 'Semiformal',
        desc: 'Claro e amigável, emojis moderados',
        exemplo: '"Oi! Que bom ter você por aqui 😊 Como posso ajudar?"',
    },
    {
        value: 'descontraido',
        label: 'Descontraído',
        desc: 'Leve, emojis liberados, gírias suaves',
        exemplo: '"Eaí! Bora agendar? Me diz o que você precisa 🤙"',
    },
];
