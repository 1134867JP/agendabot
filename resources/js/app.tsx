import '../css/app.css';
import '../css/ui.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { ThemeProvider } from '@/Components/ThemeProvider';
import AppErrorBoundary from '@/Components/UI/AppErrorBoundary';

const appName = import.meta.env.VITE_APP_NAME || 'Agendou';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <AppErrorBoundary>
                <ThemeProvider>
                    <App {...props} />
                </ThemeProvider>
            </AppErrorBoundary>
        );
    },
    progress: {
        color: '#14b8a6',
    },
});
