import { useState, useCallback } from 'react';
import ConfirmModal from '@/Components/ConfirmModal';

interface ConfirmOptions {
    title: string;
    message: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'danger' | 'warning' | 'default';
}

export function useConfirm() {
    const [state, setState] = useState<(ConfirmOptions & { resolve: (v: boolean) => void }) | null>(null);

    const confirm = useCallback((opts: ConfirmOptions): Promise<boolean> => {
        return new Promise(resolve => setState({ ...opts, resolve }));
    }, []);

    const handleConfirm = () => { state?.resolve(true);  setState(null); };
    const handleCancel  = () => { state?.resolve(false); setState(null); };

    const modal = state ? (
        <ConfirmModal
            open
            title={state.title}
            message={state.message}
            confirmLabel={state.confirmLabel}
            cancelLabel={state.cancelLabel}
            variant={state.variant}
            onConfirm={handleConfirm}
            onCancel={handleCancel}
        />
    ) : null;

    return { confirm, modal };
}
