import { TipoServico } from '@/types';

// Fonte única de verdade sobre qual "catálogo" cada tipo de estabelecimento usa.
// Reaproveitado pela navegação (Sidebar) e pelo checklist/quick links do Dashboard,
// evitando que as telas divirjam sobre o que cada tipo deve configurar.

/** Tipos que agendam por Recurso genérico (ex.: quadras). */
export const TIPOS_COM_RECURSOS: TipoServico[] = ['quadra', 'personalizado'];

/** Tipos que agendam por Profissional + Serviço (ex.: barbearia, clínica). */
export const TIPOS_COM_PROFISSIONAIS: TipoServico[] = ['barbeiro', 'estetica', 'clinica', 'studio', 'personalizado'];

export const usaRecursos = (tipo: TipoServico): boolean => TIPOS_COM_RECURSOS.includes(tipo);
export const usaProfissionais = (tipo: TipoServico): boolean => TIPOS_COM_PROFISSIONAIS.includes(tipo);

/**
 * Mantém "recurso" como conceito técnico, mas apresenta ao usuário o nome
 * natural para o seu negócio. Quadras continuam sendo recursos reserváveis.
 */
export const rotuloRecursos = (tipo: TipoServico): string =>
    tipo === 'quadra' ? 'Quadras' : 'Espaços e recursos';
