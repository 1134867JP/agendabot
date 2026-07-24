import { Link } from '@inertiajs/react';

type AgendaView = 'calendar' | 'list';

interface AgendaViewTabsProps {
    active: AgendaView;
}

const VIEWS: { id: AgendaView; label: string; routeName: string }[] = [
    { id: 'calendar', label: 'Calendário', routeName: 'tenant.agenda' },
    { id: 'list', label: 'Lista', routeName: 'tenant.agendamentos.index' },
];

export default function AgendaViewTabs({ active }: AgendaViewTabsProps) {
    return (
        <nav
            aria-label="Visualização da agenda"
            className="flex shrink-0 items-center gap-1 overflow-x-auto px-4 py-2 sm:px-6"
            style={{ background: 'var(--bg-surface)', borderBottom: '1px solid var(--border)' }}
        >
            {VIEWS.map(view => {
                const selected = view.id === active;

                return (
                    <Link
                        key={view.id}
                        href={route(view.routeName)}
                        aria-current={selected ? 'page' : undefined}
                        className="min-h-9 whitespace-nowrap rounded-lg px-3 py-2 text-xs font-medium transition-colors"
                        style={{
                            color: selected ? 'var(--text-1)' : 'var(--text-3)',
                            background: selected ? 'var(--bg-surface-2)' : 'transparent',
                            border: selected ? '1px solid var(--border-strong)' : '1px solid transparent',
                        }}
                    >
                        {view.label}
                    </Link>
                );
            })}
        </nav>
    );
}
