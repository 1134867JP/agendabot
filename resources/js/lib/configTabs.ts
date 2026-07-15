import { TipoServico } from '@/types';
import { usaRecursos, usaProfissionais } from '@/lib/tenantNav';

// Define as abas do hub de configurações. Type/admin-aware, reaproveitando a
// mesma lógica de catálogo por tipo usada na Sidebar e no Dashboard.

export interface ConfigTab {
    label: string;
    routeName: string;
    path: string;
}

export function buildConfigTabs(tipo: TipoServico, isAdmin: boolean): ConfigTab[] {
    const tabs: ConfigTab[] = [
        { label: 'Estabelecimento', routeName: 'tenant.configuracoes.index', path: '/painel/configuracoes' },
    ];

    if (usaRecursos(tipo)) {
        tabs.push({ label: 'Recursos', routeName: 'tenant.recursos.index', path: '/painel/recursos' });
    }
    if (usaProfissionais(tipo)) {
        tabs.push({ label: 'Profissionais', routeName: 'tenant.profissionais.index', path: '/painel/profissionais' });
        tabs.push({ label: 'Serviços',      routeName: 'tenant.servicos.index',      path: '/painel/servicos' });
    }

    tabs.push({ label: 'Regras',   routeName: 'tenant.regras-agendamento.index', path: '/painel/regras-agendamento' });
    tabs.push({ label: 'Triagem',  routeName: 'tenant.triagem.index',            path: '/painel/triagem' });
    tabs.push({ label: 'WhatsApp', routeName: 'tenant.whatsapp',                 path: '/painel/whatsapp' });

    if (isAdmin) {
        tabs.push({ label: 'Equipe', routeName: 'tenant.equipe.index', path: '/painel/equipe' });
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
