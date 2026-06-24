import { Switch } from '@headlessui/react';

interface ToggleProps {
    checked: boolean;
    onChange: (value: boolean) => void;
    label?: string;
    id?: string;
    disabled?: boolean;
}

export default function Toggle({ checked, onChange, label, id, disabled }: ToggleProps) {
    return (
        <Switch.Group as="div" className="flex cursor-pointer items-center gap-3">
            <Switch
                id={id}
                checked={checked}
                onChange={onChange}
                disabled={disabled}
                className="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-transparent disabled:opacity-40"
                style={{ background: checked ? 'var(--accent)' : 'rgba(255,255,255,0.15)' }}
            >
                <span
                    aria-hidden="true"
                    className={`inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform ${
                        checked ? 'translate-x-4' : 'translate-x-1'
                    }`}
                />
            </Switch>
            {label && (
                <Switch.Label className="cursor-pointer select-none text-sm" style={{ color: 'var(--text-2)' }}>
                    {label}
                </Switch.Label>
            )}
        </Switch.Group>
    );
}
