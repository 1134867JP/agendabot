import { HTMLAttributes, ReactNode } from 'react';

interface ToolbarProps extends HTMLAttributes<HTMLDivElement> {
    children: ReactNode;
}

export default function Toolbar({ children, className = '', ...props }: ToolbarProps) {
    return (
        <div
            className={`flex flex-col gap-3 rounded-xl p-3 sm:flex-row sm:flex-wrap sm:items-end sm:p-4 ${className}`.trim()}
            style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
            {...props}
        >
            {children}
        </div>
    );
}
