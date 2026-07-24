import { HTMLAttributes, ReactNode } from 'react';

interface CardProps extends HTMLAttributes<HTMLDivElement> {
    children: ReactNode;
    padding?: 'none' | 'sm' | 'md' | 'lg';
}

const paddingClasses = {
    none: '',
    sm: 'p-3 sm:p-4',
    md: 'p-4 sm:p-5',
    lg: 'p-5 sm:p-6',
};

export default function Card({ children, className = '', padding = 'md', ...props }: CardProps) {
    return (
        <div className={`card ${paddingClasses[padding]} ${className}`.trim()} {...props}>
            {children}
        </div>
    );
}
