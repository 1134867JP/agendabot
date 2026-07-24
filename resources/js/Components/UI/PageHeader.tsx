import { ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    subtitle?: string;
    eyebrow?: string;
    actions?: ReactNode;
}

export default function PageHeader({ title, subtitle, eyebrow, actions }: PageHeaderProps) {
    return (
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                {eyebrow && (
                    <p className="mb-1 text-[11px] font-semibold uppercase tracking-[0.1em]" style={{ color: 'var(--accent)' }}>
                        {eyebrow}
                    </p>
                )}
                <h1 className="page-title text-2xl leading-tight sm:text-[28px]">{title}</h1>
                {subtitle && (
                    <p className="mt-1.5 max-w-2xl text-sm leading-6 text-secondary">{subtitle}</p>
                )}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
