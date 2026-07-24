import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import ForceDark from '@/Components/ForceDark';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <ForceDark>
            <div className="relative flex min-h-[100dvh] items-center justify-center overflow-hidden px-4 py-8" style={{ background: 'var(--bg-app)' }}>
                <div className="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-indigo-500/15 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-24 -right-24 h-72 w-72 rounded-full bg-emerald-500/10 blur-3xl" />

                <div className="relative w-full max-w-md">
                    <Link href={route('home')} className="mb-6 flex items-center justify-center gap-2.5">
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl text-sm font-bold text-white" style={{ background: 'var(--accent)' }}>
                            A
                        </span>
                        <span className="text-2xl text-primary" style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}>Agendou</span>
                    </Link>

                    <main className="card overflow-hidden p-5 shadow-2xl sm:p-7">
                        {children}
                    </main>

                    <p className="mt-5 text-center text-xs text-muted">© {new Date().getFullYear()} Agendou</p>
                </div>
            </div>
        </ForceDark>
    );
}
