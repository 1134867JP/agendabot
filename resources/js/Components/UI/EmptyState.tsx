import { ReactNode } from 'react';

interface EmptyStateProps {
    title: string;
    description?: string;
    action?: ReactNode;
    icon?: ReactNode;
    compact?: boolean;
}

const DefaultIcon = () => (
    <svg width={22} height={22} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <rect x="3" y="5" width="18" height="16" rx="2" />
        <path d="M16 3v4M8 3v4M3 10h18" />
    </svg>
);

export default function EmptyState({ title, description, action, icon, compact = false }: EmptyStateProps) {
    return (
        <div className={`flex flex-col items-center justify-center px-5 text-center ${compact ? 'py-8' : 'py-14'}`}>
            <span
                className="mb-3 flex h-11 w-11 items-center justify-center rounded-xl"
                style={{ background: 'var(--accent-light)', color: 'var(--accent)' }}
            >
                {icon ?? <DefaultIcon />}
            </span>
            <h3 className="text-sm font-semibold text-primary">{title}</h3>
            {description && <p className="mt-1 max-w-sm text-sm leading-5 text-secondary">{description}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
