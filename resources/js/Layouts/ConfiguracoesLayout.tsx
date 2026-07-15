import AppLayout from '@/Layouts/AppLayout';
import ConfiguracoesTabs from '@/Components/Layout/ConfiguracoesTabs';

interface Props {
    children: React.ReactNode;
    title?: string;
    subtitle?: string;
}

/**
 * Envolve as páginas de configuração no hub: mantém o AppLayout (sidebar,
 * header, banners) e adiciona a navegação por abas entre as sub-páginas.
 */
export default function ConfiguracoesLayout({ children, title, subtitle }: Props) {
    return (
        <AppLayout title={title} subtitle={subtitle}>
            <ConfiguracoesTabs />
            {children}
        </AppLayout>
    );
}
