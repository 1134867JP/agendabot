import { Link } from '@inertiajs/react';
import { SubscriptionInfo } from '@/types';

interface Props {
    subscription: SubscriptionInfo;
}

export default function SubscriptionBanner({ subscription }: Props) {
    const { status, trial_ends_at, subscription_ends_at } = subscription;

    if (status === 'trial' && trial_ends_at) {
        const dias = Math.max(0, Math.ceil(
            (new Date(trial_ends_at).getTime() - Date.now()) / (1000 * 60 * 60 * 24)
        ));
        return (
            <div
                className="flex items-center justify-between px-5 py-2.5 text-sm"
                style={{ background: 'rgba(245,158,11,0.08)', borderBottom: '1px solid rgba(245,158,11,0.2)' }}
            >
                <span style={{ color: 'rgba(252,211,77,0.9)' }}>
                    ⏳ Seu trial termina em <strong>{dias} dia{dias !== 1 ? 's' : ''}</strong>.
                </span>
                <Link
                    href={route('onboarding.step2')}
                    className="ml-4 shrink-0 rounded-md px-3 py-1 text-xs font-semibold transition-all hover:brightness-110"
                    style={{ background: 'rgba(245,158,11,0.2)', color: '#fcd34d', border: '1px solid rgba(245,158,11,0.3)' }}
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
                <span style={{ color: 'rgba(252,165,165,0.9)' }}>
                    ⚠️ Pagamento pendente. Acesso suspenso em <strong>{Math.max(0, 3 - dias)} dia{dias !== 1 ? 's' : ''}</strong>.
                </span>
                <Link
                    href={route('tenant.renovar')}
                    className="ml-4 shrink-0 rounded-md px-3 py-1 text-xs font-semibold transition-all hover:brightness-110"
                    style={{ background: 'rgba(239,68,68,0.2)', color: '#fca5a5', border: '1px solid rgba(239,68,68,0.3)' }}
                >
                    Renovar →
                </Link>
            </div>
        );
    }

    return null;
}
