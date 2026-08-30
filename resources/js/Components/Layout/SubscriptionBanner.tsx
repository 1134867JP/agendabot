import { Link } from '@inertiajs/react';
import { SubscriptionInfo } from '@/types';

interface Props {
    subscription: SubscriptionInfo;
}

export default function SubscriptionBanner({ subscription }: Props) {
    const { status, trial_ends_at, subscription_ends_at, isento_cobranca } = subscription;

    if (isento_cobranca) return null;

    if (status === 'trial' && trial_ends_at) {
        const dias = Math.max(0, Math.ceil(
            (new Date(trial_ends_at).getTime() - Date.now()) / (1000 * 60 * 60 * 24)
        ));
        return (
            <div
                className="flex min-h-12 items-center justify-between gap-3 px-4 py-2 text-sm sm:px-5"
                style={{ background: 'rgba(245,158,11,0.08)', borderBottom: '1px solid rgba(245,158,11,0.2)' }}
                role="status"
            >
                <span className="min-w-0 truncate leading-tight" style={{ color: 'var(--amber-text)' }}>
                    <span aria-hidden="true">⏳ </span>
                    <span className="sm:hidden">Trial · <strong>{dias} dia{dias !== 1 ? 's' : ''}</strong></span>
                    <span className="hidden sm:inline">Trial: <strong>{dias} dia{dias !== 1 ? 's' : ''} restante{dias !== 1 ? 's' : ''}</strong></span>
                </span>
                <Link
                    href={route('tenant.renovar')}
                    className="inline-flex min-h-10 shrink-0 items-center rounded-md px-3 py-1.5 text-xs font-semibold transition-all hover:brightness-110 sm:ml-4"
                    style={{ background: 'var(--amber-btn-bg)', color: 'var(--amber-text)', border: '1px solid var(--amber-btn-bdr)' }}
                >
                    <span className="sm:hidden">Planos</span>
                    <span className="hidden sm:inline">Ver planos</span>
                </Link>
            </div>
        );
    }

    if (status === 'past_due' && subscription_ends_at) {
        const dias = Math.max(0, Math.ceil(
            (Date.now() - new Date(subscription_ends_at).getTime()) / (1000 * 60 * 60 * 24)
        ));
        return (
            <div
                className="flex min-h-12 items-center justify-between gap-3 px-4 py-2 text-sm sm:px-5"
                style={{ background: 'rgba(239,68,68,0.08)', borderBottom: '1px solid rgba(239,68,68,0.2)' }}
                role="alert"
            >
                <span className="min-w-0 truncate leading-tight" style={{ color: 'var(--danger-text)' }}>
                    <span aria-hidden="true">⚠️ </span>
                    <span className="sm:hidden">Pendente · <strong>{Math.max(0, 3 - dias)} dia{Math.max(0, 3 - dias) !== 1 ? 's' : ''}</strong></span>
                    <span className="hidden sm:inline">Pagamento pendente · <strong>{Math.max(0, 3 - dias)} dia{Math.max(0, 3 - dias) !== 1 ? 's' : ''}</strong> para suspensão</span>
                </span>
                <Link
                    href={route('tenant.renovar')}
                    className="inline-flex min-h-10 shrink-0 items-center rounded-md px-3 py-1.5 text-xs font-semibold transition-all hover:brightness-110 sm:ml-4"
                    style={{ background: 'var(--danger-btn-bg)', color: 'var(--danger-text)', border: '1px solid var(--danger-btn-bdr)' }}
                >
                    <span className="sm:hidden">Pagar</span>
                    <span className="hidden sm:inline">Regularizar</span>
                </Link>
            </div>
        );
    }

    return null;
}
