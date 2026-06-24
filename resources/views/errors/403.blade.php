@php $codigo = 403; @endphp
@component('errors.layout', ['codigo' => $codigo])
    @slot('slot')
        <div class="error-code">403</div>
        <div class="icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>
        <h1 class="title">Acesso negado</h1>
        <div class="divider"></div>
        <p class="description">
            Você não tem permissão para acessar esta página.<br>
            {{ $exception->getMessage() ?: 'Entre em contato com o administrador se acredita que isso é um erro.' }}
        </p>
        <div class="actions">
            <a href="javascript:history.back()" class="btn-primary">← Voltar</a>
            @auth
            <a href="{{ url('/dashboard') }}" class="btn-secondary">Meu painel</a>
            @endauth
        </div>
    @endslot
@endcomponent
