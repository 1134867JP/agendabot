@php $codigo = 404; @endphp
@component('errors.layout', ['codigo' => $codigo])
    @slot('slot')
        <div class="error-code">404</div>
        <div class="icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                <line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
            </svg>
        </div>
        <h1 class="title">Página não encontrada</h1>
        <div class="divider"></div>
        <p class="description">
            O endereço que você digitou não existe ou foi movido.<br>
            Verifique a URL ou volte para o início.
        </p>
        <div class="actions">
            <a href="{{ url('/') }}" class="btn-primary">← Ir para o início</a>
            @auth
            <a href="{{ url('/dashboard') }}" class="btn-secondary">Meu painel</a>
            @endauth
        </div>
    @endslot
@endcomponent
