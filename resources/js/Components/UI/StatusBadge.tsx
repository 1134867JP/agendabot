import { ReactNode } from 'react';

type Tone = 'success' | 'danger' | 'warning' | 'info' | 'neutral' | 'brand';

interface StatusBadgeProps {
    children: ReactNode;
    tone?: Tone;
    dot?: boolean;
}

const toneClasses: Record<Tone, string> = {
    success: 'badge-green',
    danger: 'badge-red',
    warning: 'badge-amber',
    info: 'badge-blue',
    neutral: 'badge-gray',
    brand: 'badge-brand',
};

export default function StatusBadge({ children, tone = 'neutral', dot = false }: StatusBadgeProps) {
    return (
        <span className={`badge ${toneClasses[tone]}`}>
            {dot && <span className="h-1.5 w-1.5 rounded-full bg-current" aria-hidden />}
            {children}
        </span>
    );
}
