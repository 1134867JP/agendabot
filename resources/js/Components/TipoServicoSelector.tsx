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
            <div className="grid grid-cols-2 gap-2 lg:grid-cols-3" role="group" aria-label="Tipo de atendimento">
                {TIPOS_SERVICO.map(t => (
                    <button
                        key={t.value}
                        type="button"
                        onClick={() => onChange(t.value)}
                        aria-pressed={value === t.value}
                        className="min-w-0 rounded-xl border-2 px-2.5 py-3 text-left text-sm transition-all sm:flex sm:flex-col sm:items-start sm:gap-0.5 sm:px-3 sm:py-2.5"
                        style={value === t.value
                            ? { borderColor: 'var(--accent)', background: 'var(--accent-light)', color: 'var(--accent)' }
                            : { borderColor: 'var(--border-strong)', background: 'var(--bg-surface-2)', color: 'var(--text-2)' }
                        }
                    >
                        <span className="mr-1 text-base sm:mr-0">{t.emoji}</span>
                        <span className="break-words font-medium leading-tight">{t.label}</span>
                        <span className="hidden text-[11px] leading-tight opacity-60 sm:block">{t.desc}</span>
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
