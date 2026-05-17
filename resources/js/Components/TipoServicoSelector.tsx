interface TipoItem {
    value: string;
    label: string;
    emoji: string;
    desc: string;
}

const TIPOS: TipoItem[] = [
    { value: 'barbeiro',      label: 'Barbearia',        emoji: '✂️',  desc: 'Barbeiros, salões masculinos' },
    { value: 'quadra',        label: 'Quadra esportiva', emoji: '🏟️', desc: 'Futsal, beach tennis, padel' },
    { value: 'estetica',      label: 'Estética',         emoji: '💆',  desc: 'Manicure, massagem, depilação' },
    { value: 'clinica',       label: 'Clínica',          emoji: '🩺',  desc: 'Fisioterapia, psicologia, nutrição' },
    { value: 'studio',        label: 'Estúdio',          emoji: '🎨',  desc: 'Tatuagem, fotografia, música' },
    { value: 'personalizado', label: 'Outro',            emoji: '⚙️',  desc: 'Digite o tipo do seu negócio' },
];

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
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {TIPOS.map(t => (
                    <button
                        key={t.value}
                        type="button"
                        onClick={() => onChange(t.value)}
                        className="flex flex-col items-start gap-0.5 rounded-xl border-2 px-3 py-2.5 text-left text-sm transition-all"
                        style={value === t.value
                            ? { borderColor: 'var(--accent)', background: 'var(--accent-light)', color: 'white' }
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
