// Serviços extras de um mesmo atendimento (ex.: corte + barba) e o total
// de duração e valor para o profissional escolhido.

export interface ServicoComboOpcao {
    id: number;
    nome: string;
    duracao_minutos: number;
    valor_min?: string | number | null;
    valor_max?: string | number | null;
    profissional_ids: number[];
    personalizado_por_profissional?: Record<string, { valor: number | null; duracao_minutos: number | null }>;
}

function valorFixo(servico: ServicoComboOpcao): number | null {
    if (servico.valor_min === null || servico.valor_min === undefined || servico.valor_min === '') return null;
    const min = Number(servico.valor_min);
    const max = servico.valor_max === null || servico.valor_max === undefined || servico.valor_max === '' ? null : Number(servico.valor_max);
    return max !== null && Math.abs(max - min) >= 0.01 ? null : min;
}

/** Mesma regra do backend (CatalogoServicos): preço/duração do profissional quando houver. */
export function resumoServicos(ids: number[], servicos: ServicoComboOpcao[], profissionalId?: number | string | null) {
    let duracao = 0;
    let valor: number | null = 0;
    const nomes: string[] = [];
    ids.forEach(id => {
        const servico = servicos.find(s => s.id === id);
        if (!servico) return;
        const proprio = profissionalId ? servico.personalizado_por_profissional?.[String(profissionalId)] : undefined;
        duracao += proprio?.duracao_minutos || servico.duracao_minutos || 30;
        const v = proprio?.valor ?? valorFixo(servico);
        valor = valor === null || v === null ? null : valor + v;
        nomes.push(servico.nome);
    });
    return { duracao, valor, nomes };
}

export default function ServicosCombo({
    idPrefix,
    principalId,
    extras,
    onChange,
    opcoes,
    todos,
    profissionalId,
}: {
    idPrefix: string;
    principalId: number | null;
    extras: number[];
    onChange: (extras: number[]) => void;
    opcoes: ServicoComboOpcao[];
    todos: ServicoComboOpcao[];
    profissionalId?: number | string | null;
}) {
    if (!principalId) return null;

    const selecionados = [principalId, ...extras];
    const restantes = opcoes.filter(s => !selecionados.includes(s.id));
    const resumo = resumoServicos(selecionados, todos, profissionalId);

    return (
        <div className="mt-2 space-y-2">
            {extras.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                    {extras.map(id => {
                        const servico = todos.find(s => s.id === id);
                        return (
                            <span key={id} className="flex items-center gap-1 rounded-full px-2.5 py-1 text-xs" style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}>
                                + {servico?.nome}
                                <button type="button" onClick={() => onChange(extras.filter(e => e !== id))} className="ml-0.5 transition-opacity hover:opacity-70" aria-label={`Remover ${servico?.nome}`}>×</button>
                            </span>
                        );
                    })}
                </div>
            )}
            {restantes.length > 0 && extras.length < 4 && (
                <select
                    id={`${idPrefix}-servico-extra`}
                    aria-label="Adicionar outro serviço no mesmo horário"
                    value=""
                    onChange={e => e.target.value && onChange([...extras, Number(e.target.value)])}
                    className="input text-sm"
                >
                    <option value="">+ Adicionar outro serviço no mesmo horário</option>
                    {restantes.map(s => <option key={s.id} value={s.id}>{s.nome}</option>)}
                </select>
            )}
            <p className="text-xs" style={{ color: 'var(--text-3)' }}>
                Total: {resumo.duracao} min
                {resumo.valor !== null && ` · ${resumo.valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`}
            </p>
        </div>
    );
}
