interface SkeletonProps {
    className?: string;
    lines?: number;
}

export default function Skeleton({ className = '', lines = 1 }: SkeletonProps) {
    if (lines > 1) {
        return (
            <div className={`space-y-2.5 ${className}`.trim()} aria-hidden>
                {Array.from({ length: lines }).map((_, index) => (
                    <span
                        key={index}
                        className="ui-skeleton block h-3 rounded-full"
                        style={{ width: index === lines - 1 ? '68%' : '100%' }}
                    />
                ))}
            </div>
        );
    }

    return <span className={`ui-skeleton block h-4 rounded-md ${className}`.trim()} aria-hidden />;
}
