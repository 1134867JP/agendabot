import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import ForceDark from '@/Components/ForceDark';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <ForceDark>
            <div
                className="relative flex min-h-[100dvh] items-center justify-center overflow-hidden px-4 py-8"
                style={{
                    background:
                        'radial-gradient(circle at 50% -20%, rgba(45,212,191,0.10), transparent 34%), var(--bg-app)',
                }}
            >
                <div
                    className="pointer-events-none absolute inset-x-0 top-0 h-px"
                    style={{ background: 'linear-gradient(90deg, transparent, rgba(45,212,191,0.55), transparent)' }}
                />

                <div className="relative w-full max-w-md">
                    <Link href={route('home')} className="mb-7 flex items-center justify-center gap-2.5">
                        <span
                            className="flex h-9 w-9 items-center justify-center rounded-lg text-sm font-bold text-white shadow-sm"
                            style={{ background: 'var(--accent)' }}
                        >
                            A
                        </span>
                        <span
                            className="text-2xl text-primary"
                            style={{ fontFamily: 'Instrument Serif, Georgia, serif', letterSpacing: '-0.02em' }}
                        >
                            Agendou
                        </span>
                    </Link>

                    <main className="card overflow-hidden p-5 sm:p-7">
                        {children}
                    </main>

                    <p className="mt-5 text-center text-xs text-muted">© {new Date().getFullYear()} Agendou</p>
                </div>
            </div>
        </ForceDark>
    );
}
