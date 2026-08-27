import { Component, ErrorInfo, ReactNode } from 'react';

interface Props {
    children: ReactNode;
}

interface State {
    hasError: boolean;
}

export default class AppErrorBoundary extends Component<Props, State> {
    state: State = { hasError: false };

    static getDerivedStateFromError(): State {
        return { hasError: true };
    }

    componentDidCatch(error: Error, info: ErrorInfo) {
        console.error('Erro não tratado na interface', error, info);
    }

    private retry = () => {
        this.setState({ hasError: false });
        window.location.reload();
    };

    render() {
        if (!this.state.hasError) {
            return this.props.children;
        }

        return (
            <main
                className="flex min-h-[100dvh] items-center justify-center p-5"
                style={{ background: 'var(--bg-app)', color: 'var(--text-1)' }}
            >
                <section
                    className="w-full max-w-md rounded-2xl p-6 text-center sm:p-8"
                    style={{ background: 'var(--bg-surface)', border: '1px solid var(--border)' }}
                    role="alert"
                    aria-live="assertive"
                >
                    <div
                        className="mx-auto flex h-12 w-12 items-center justify-center rounded-full"
                        style={{ background: 'rgba(239,68,68,.09)', color: '#f87171' }}
                        aria-hidden
                    >
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 8v5M12 16h.01" />
                        </svg>
                    </div>

                    <h1 className="mt-4 text-lg font-semibold text-primary">Não foi possível carregar esta tela</h1>
                    <p className="mt-2 text-sm leading-6" style={{ color: 'var(--text-3)' }}>
                        O Agendou encontrou um erro inesperado. Seus dados não foram apagados. Recarregue a página para continuar.
                    </p>

                    <div className="mt-6 grid gap-2 sm:grid-cols-2">
                        <button type="button" onClick={this.retry} className="btn-primary min-h-11 justify-center">
                            Recarregar página
                        </button>
                        <a href="/painel" className="btn-secondary min-h-11 justify-center">
                            Voltar ao início
                        </a>
                    </div>
                </section>
            </main>
        );
    }
}
