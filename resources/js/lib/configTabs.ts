import { TipoServico } from '@/types';
import { rotuloRecursos, usaRecursos, usaProfissionais } from '@/lib/tenantNav';

// Define as abas do hub de configurações. Type/admin-aware, reaproveitando a
// mesma lógica de catálogo por tipo usada na Sidebar e no Dashboard.

export interface ConfigTab {
    label: string;
    routeName: string;
    path: string;
    /** SVG path (viewBox 0 0 24 24, stroke) para o ícone da aba. */
    icon: string;
}

const ICONS = {
    estabelecimento: 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-5a3 3 0 016 0v5',
    recursos:        'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    profissionais:   'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    servicos:        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    regras:          'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    triagem:         'M22 12h-4l-3 9L9 3l-3 9H2',
    whatsapp:        'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z',
    equipe:          'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
};

export function buildConfigTabs(tipo: TipoServico, isAdmin: boolean): ConfigTab[] {
    const tabs: ConfigTab[] = [
        { label: 'Estabelecimento', routeName: 'tenant.configuracoes.index', path: '/painel/configuracoes', icon: ICONS.estabelecimento },
    ];

    if (usaRecursos(tipo)) {
        tabs.push({ label: rotuloRecursos(tipo), routeName: 'tenant.recursos.index', path: '/painel/recursos', icon: ICONS.recursos });
    }
    if (usaProfissionais(tipo)) {
        tabs.push({ label: 'Profissionais', routeName: 'tenant.profissionais.index', path: '/painel/profissionais', icon: ICONS.profissionais });
        tabs.push({ label: 'Serviços',      routeName: 'tenant.servicos.index',      path: '/painel/servicos',      icon: ICONS.servicos });
    }

    tabs.push({ label: 'Regras',   routeName: 'tenant.regras-agendamento.index', path: '/painel/regras-agendamento', icon: ICONS.regras });
    tabs.push({ label: 'Triagem',  routeName: 'tenant.triagem.index',            path: '/painel/triagem',            icon: ICONS.triagem });
    tabs.push({ label: 'WhatsApp', routeName: 'tenant.whatsapp',                 path: '/painel/whatsapp',           icon: ICONS.whatsapp });

    if (isAdmin) {
        tabs.push({ label: 'Equipe', routeName: 'tenant.equipe.index', path: '/painel/equipe', icon: ICONS.equipe });
    }

    return tabs;
}

// Todos os caminhos que pertencem ao hub — usado pela Sidebar para marcar a
// entrada única "Configurações" como ativa em qualquer sub-página.
export const CONFIG_PATHS = [
    '/painel/configuracoes',
    '/painel/recursos',
    '/painel/profissionais',
    '/painel/servicos',
    '/painel/regras-agendamento',
    '/painel/triagem',
    '/painel/whatsapp',
    '/painel/equipe',
];
