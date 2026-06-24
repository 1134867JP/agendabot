import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import ForceDark from '@/Components/ForceDark';

const DARK  = '#08090f';
const INDIGO = '#6366F1';

interface Props {
    children: React.ReactNode;
    currentPage?: 'home' | 'precos';
}

function LandingNavbar({ currentPage, scrolled }: { currentPage?: string; scrolled: boolean }) {
    return (
        <header
            className="fixed top-0 left-0 right-0 z-50 transition-all duration-300"
            style={{
                background:    scrolled ? 'rgba(8,9,15,0.88)' : 'transparent',
                backdropFilter: scrolled ? 'blur(12px)' : 'none',
                borderBottom:  scrolled ? '1px solid rgba(99,102,241,0.15)' : 'none',
            }}
        >
            <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <Link
                    href={route('home')}
                    className="text-xl text-primary italic"
                    style={{ fontFamily: 'Instrument Serif, Georgia, serif' }}
                >
                    AgendaBot
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

                <Link
                    href={route('onboarding.step1')}
                    className="rounded-lg px-4 py-2 text-[13px] font-medium transition-all hover:brightness-90"
                    style={{ background: 'white', color: DARK }}
                >
                    Começar grátis
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
                    AgendaBot
                </span>
                <div className="flex gap-5 text-xs" style={{ color: 'rgba(255,255,255,0.3)' }}>
                    <Link href={route('precos')}           className="hover:text-primary transition-colors">Preços</Link>
                    <Link href={route('login')}            className="hover:text-primary transition-colors">Entrar</Link>
                    <Link href={route('onboarding.step1')} className="hover:text-primary transition-colors">Criar conta</Link>
                </div>
                <p className="text-xs" style={{ color: 'rgba(255,255,255,0.25)' }}>
                    © {new Date().getFullYear()} AgendaBot
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
