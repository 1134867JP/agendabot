import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { PropsWithChildren } from 'react';

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
}: PropsWithChildren<{
    show: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    closeable?: boolean;
    onClose: CallableFunction;
}>) {
    const close = () => {
        if (closeable) onClose();
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[maxWidth];

    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                id="modal"
                className="fixed inset-0 z-50 flex transform items-end overflow-y-auto p-0 transition-all sm:items-center sm:px-4 sm:py-6"
                onClose={close}
            >
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="absolute inset-0 bg-black/60 backdrop-blur-[2px]" aria-hidden />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0"
                    enterTo="translate-y-0 sm:scale-100 sm:opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="translate-y-0 sm:scale-100 sm:opacity-100"
                    leaveTo="translate-y-full sm:translate-y-0 sm:scale-95 sm:opacity-0"
                >
                    <DialogPanel
                        className={`relative max-h-[92dvh] w-full transform overflow-y-auto rounded-t-2xl shadow-2xl transition-all sm:mx-auto sm:mb-6 sm:w-full sm:rounded-xl ${maxWidthClass}`}
                        style={{
                            background: 'var(--bg-surface)',
                            color: 'var(--text-1)',
                            border: '1px solid var(--border-strong)',
                            paddingBottom: 'env(safe-area-inset-bottom)',
                        }}
                    >
                        <div className="sticky top-0 z-10 flex h-5 items-center justify-center bg-[var(--bg-surface)] sm:hidden" aria-hidden>
                            <span className="h-1 w-10 rounded-full" style={{ background: 'var(--border-strong)' }} />
                        </div>
                        {children}
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
