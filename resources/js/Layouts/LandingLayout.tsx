import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ForceDark from '@/Components/ForceDark';

const DARK  = '#0d1012';
const INDIGO = '#14b8a6';

interface Props {
    children: React.ReactNode;
    currentPage?: 'home' | 'precos';
}

function LandingNavbar({ currentPage, scrolled }: { currentPage?: string; scrolled: boolean }) {
    return (
        <header
            className="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
            style={{
                background:    scrolled ? 'rgba(13,16,18,0.92)' : 'transparent',
                backdropFilter: scrolled ? 'blur(12px)' : 'none',
                borderBottom:  scrolled ? '1px solid rgba(110,215,189,0.14)' : 'none',
            }}
        >
            <div className="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6 sm:py-4">
                <Link
                    href={route('home')}
                    className="shrink-0 text-xl italic"
                    style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(232,230,225,0.95)' }}
                >
                    Agendou
                </Link>

                <nav className="hidden items-center gap-6 md:flex">
                    <a
                        href={route('home') + '#como-funciona'}
                        className="text-[14px] transition-colors"
                        style={{ color: currentPage === 'home' ? 'rgba(255,255,255,0.8)' : 'rgba(255,255,255,0.5)' }}
                        onMouseEnter={e => (e.currentTarget.style.color = 'rgba(255,255,255,0.9)')}
                        onMouseLeave={e => (e.currentTarget.style.color = currentPage === 'home' ? 'rgba(255,255,255,0.8)' : 'rgba(255,255,255,0.5)')}
                    >
                        Como funciona
                    </a>
                    <Link
                        href={route('precos')}
                        className="text-[14px] transition-colors"
                        style={{ color: currentPage === 'precos' ? 'white' : 'rgba(255,255,255,0.5)' }}
                        onMouseEnter={e => (e.currentTarget.style.color = 'rgba(255,255,255,0.9)')}
                        onMouseLeave={e => (e.currentTarget.style.color = currentPage === 'precos' ? 'white' : 'rgba(255,255,255,0.5)')}
                    >
                        Preços
                    </Link>

                    <span className="h-4 w-px" style={{ background: 'rgba(255,255,255,0.1)' }} />

                    <Link
                        href={route('login')}
                        className="text-[14px] font-medium transition-colors"
                        style={{ color: 'rgba(255,255,255,0.6)' }}
                        onMouseEnter={e => (e.currentTarget.style.color = 'white')}
                        onMouseLeave={e => (e.currentTarget.style.color = 'rgba(255,255,255,0.6)')}
                    >
                        Entrar
                    </Link>
                </nav>

                <div className="flex items-center gap-3 md:hidden">
                    <Link
                        href={route('precos')}
                        className="text-[12px] font-medium transition-colors"
                        style={{ color: 'rgba(255,255,255,0.65)' }}
                    >
                        Preços
                    </Link>
                    <Link
                        href={route('login')}
                        className="text-[12px] font-medium transition-colors"
                        style={{ color: 'rgba(255,255,255,0.65)' }}
                    >
                        Entrar
                    </Link>
                </div>

                <Link
                    href={route('onboarding.step1')}
                    className="hidden md:inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-[13px] font-semibold text-white transition-all hover:brightness-110 hover:-translate-y-px"
                    style={{ background: INDIGO, boxShadow: '0 4px 14px rgba(45,157,130,0.22)' }}
                >
                    Começar grátis
                    <svg width={11} height={11} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </Link>
                <Link
                    href={route('onboarding.step1')}
                    className="shrink-0 md:hidden rounded-lg px-3 py-1.5 text-[12px] font-semibold text-white transition-all"
                    style={{ background: INDIGO }}
                >
                    Começar
                </Link>
            </div>
        </header>
    );
}

function LandingFooter() {
    return (
        <footer className="px-6 py-8" style={{ borderTop: '1px solid rgba(255,255,255,0.06)' }}>
            <div className="mx-auto flex max-w-6xl flex-col items-center gap-4 text-center sm:flex-row sm:justify-between sm:text-left">
                <span
                    className="text-lg font-semibold italic"
                    style={{ fontFamily: 'Instrument Serif, Georgia, serif', color: 'rgba(255,255,255,0.6)' }}
                >
                    Agendou
                </span>
                <div className="flex gap-5 text-xs" style={{ color: 'rgba(255,255,255,0.3)' }}>
                    <Link href={route('precos')}           className="hover:text-primary transition-colors">Preços</Link>
                    <Link href={route('login')}            className="hover:text-primary transition-colors">Entrar</Link>
                    <Link href={route('onboarding.step1')} className="hover:text-primary transition-colors">Criar conta</Link>
                </div>
                <p className="text-xs" style={{ color: 'rgba(255,255,255,0.25)' }}>
                    © {new Date().getFullYear()} Agendou
                </p>
            </div>
        </footer>
    );
}

export default function LandingLayout({ children, currentPage }: Props) {
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const handler = () => setScrolled(window.scrollY > 20);
        window.addEventListener('scroll', handler, { passive: true });
        return () => window.removeEventListener('scroll', handler);
    }, []);

    return (
        <ForceDark>
            <div style={{ background: DARK, color: 'white', fontFamily: "'DM Sans', sans-serif", minHeight: '100vh' }}>
                <LandingNavbar currentPage={currentPage} scrolled={scrolled} />
                {children}
                <LandingFooter />
            </div>
        </ForceDark>
    );
}
