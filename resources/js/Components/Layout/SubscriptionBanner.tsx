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
                className="flex items-center justify-between px-5 py-2.5 text-sm"
                style={{ background: 'rgba(245,158,11,0.08)', borderBottom: '1px solid rgba(245,158,11,0.2)' }}
            >
                <span style={{ color: 'var(--amber-text)' }}>
                    ⏳ Seu trial termina em <strong>{dias} dia{dias !== 1 ? 's' : ''}</strong>.
                </span>
                <Link
                    href={route('onboarding.step2')}
                    className="ml-4 shrink-0 rounded-md px-3 py-1 text-xs font-semibold transition-all hover:brightness-110"
                    style={{ background: 'var(--amber-btn-bg)', color: 'var(--amber-text)', border: '1px solid var(--amber-btn-bdr)' }}
                >
                    Escolher plano →
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
                className="flex items-center justify-between px-5 py-2.5 text-sm"
                style={{ background: 'rgba(239,68,68,0.08)', borderBottom: '1px solid rgba(239,68,68,0.2)' }}
            >
                <span style={{ color: 'var(--danger-text)' }}>
                    ⚠️ Pagamento pendente. Acesso suspenso em <strong>{Math.max(0, 3 - dias)} dia{dias !== 1 ? 's' : ''}</strong>.
                </span>
                <Link
                    href={route('tenant.renovar')}
                    className="ml-4 shrink-0 rounded-md px-3 py-1 text-xs font-semibold transition-all hover:brightness-110"
                    style={{ background: 'var(--danger-btn-bg)', color: 'var(--danger-text)', border: '1px solid var(--danger-btn-bdr)' }}
                >
                    Renovar →
                </Link>
            </div>
        );
    }

    return null;
}
