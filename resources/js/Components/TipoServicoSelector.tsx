import { TIPOS_SERVICO } from '@/constants/tiposServico';

interface Props {
    value: string;
    onChange: (v: string) => void;
    customValue: string;
    onChangeCustom: (v: string) => void;
    error?: string;
}

export default function TipoServicoSelector({ value, onChange, customValue, onChangeCustom, error }: Props) {
    return (
        <div>
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {TIPOS_SERVICO.map(t => (
                    <button
                        key={t.value}
                        type="button"
                        onClick={() => onChange(t.value)}
                        className="flex flex-col items-start gap-0.5 rounded-xl border-2 px-3 py-2.5 text-left text-sm transition-all"
                        style={value === t.value
                            ? { borderColor: 'var(--accent)', background: 'var(--accent-light)', color: 'var(--accent)' }
                            : { borderColor: 'var(--border-strong)', background: 'var(--bg-surface-2)', color: 'var(--text-2)' }
                        }
                    >
                        <span className="text-base">{t.emoji}</span>
                        <span className="font-medium leading-tight">{t.label}</span>
                        <span className="text-[11px] opacity-60 leading-tight">{t.desc}</span>
                    </button>
                ))}
            </div>

            {value === 'personalizado' && (
                <input
                    type="text"
                    value={customValue}
                    onChange={e => onChangeCustom(e.target.value)}
                    className="input mt-2"
                    placeholder="Ex: Clínica veterinária, Estúdio de pilates…"
                    required
                />
            )}

            {error && <p className="mt-1 text-xs text-red-400">{error}</p>}
        </div>
    );
}
