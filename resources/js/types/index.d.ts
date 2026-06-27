export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    is_super_admin: boolean;
}

export type TipoServico = 'barbeiro' | 'quadra' | 'estetica' | 'clinica' | 'studio' | 'personalizado';
export type StatusWhatsApp = 'open' | 'close' | 'desconhecido';
export type StatusAgendamento = 'confirmado' | 'agendado' | 'cancelado' | 'concluido';

export type SubscriptionStatus = 'trial' | 'active' | 'past_due' | 'blocked' | 'canceled';

export interface Tenant {
    id: number;
    nome: string;
    slug: string;
    tipo_servico: TipoServico;
    tipo_servico_personalizado?: string | null;
    whatsapp_conectado: boolean;
    evolution_instance: string | null;
    ativo: boolean;
    subscription_status: SubscriptionStatus;
    trial_ends_at: string | null;
    subscription_ends_at: string | null;
    plano: 'basico' | 'profissional' | 'ilimitado';
    // Bot & IA fields
    nome_agente?: string | null;
    tom_voz?: 'formal' | 'semiformal' | 'descontraido' | null;
    instrucoes_extras?: string | null;
    bot_ativo?: boolean | null;
    ramo_negocio?: string | null;
    descricao_negocio?: string | null;
    cidade?: string | null;
    endereco?: string | null;
    horarios_funcionamento?: string | null;
}

export interface SubscriptionInfo {
    status: SubscriptionStatus;
    trial_ends_at: string | null;
    subscription_ends_at: string | null;
    plano: string;
    isento_cobranca: boolean;
}

export interface HorarioFuncionamento {
    id: number;
    dia_semana: number;
    abertura: string;
    fechamento: string;
}

export interface Recurso {
    id: number;
    nome: string;
    descricao: string | null;
    valor_hora: number;
    duracao_padrao_minutos: number;
    ativo: boolean;
    horarios_funcionamento: HorarioFuncionamento[];
}

export interface Agendamento {
    id: number;
    recurso: Recurso;
    cliente_nome: string;
    cliente_telefone: string;
    inicio: string;
    fim: string;
    status: StatusAgendamento;
    valor_total: number | null;
    origem: 'bot' | 'whatsapp' | 'manual';
    observacoes: string | null;
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    subscription?: SubscriptionInfo | null;
};
