import { useEffect } from 'react';

export default function ForceDark({ children }: { children: React.ReactNode }) {
    useEffect(() => {
        const root = document.documentElement;
        const prev = root.classList.contains('light') ? 'light' : 'dark';
        root.classList.remove('light');
        root.classList.add('dark');
        return () => {
            if (prev === 'light') {
                root.classList.remove('dark');
                root.classList.add('light');
            }
        };
    }, []);

    return <>{children}</>;
}
