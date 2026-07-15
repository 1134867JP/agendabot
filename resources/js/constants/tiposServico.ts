import { TipoServico } from '@/types';

// Fonte única de verdade dos tipos de estabelecimento (rótulos, emojis, descrições).
// Consumida pelo seletor do onboarding/config (TipoServicoSelector) e pela criação
// de tenant no SuperAdmin, evitando listas divergentes (antes o SuperAdmin só
// conhecia 4 dos 6 tipos).

export interface TipoServicoItem {
    value: TipoServico;
    label: string;
    emoji: string;
    desc: string;
}

export const TIPOS_SERVICO: TipoServicoItem[] = [
    { value: 'barbeiro',      label: 'Barbearia',        emoji: '✂️',  desc: 'Barbeiros, salões masculinos' },
    { value: 'quadra',        label: 'Quadra esportiva', emoji: '🏟️', desc: 'Futsal, beach tennis, padel' },
    { value: 'estetica',      label: 'Estética',         emoji: '💆',  desc: 'Manicure, massagem, depilação' },
    { value: 'clinica',       label: 'Clínica',          emoji: '🩺',  desc: 'Fisioterapia, psicologia, nutrição' },
    { value: 'studio',        label: 'Estúdio',          emoji: '🎨',  desc: 'Tatuagem, fotografia, música' },
    { value: 'personalizado', label: 'Outro',            emoji: '⚙️',  desc: 'Digite o tipo do seu negócio' },
];
