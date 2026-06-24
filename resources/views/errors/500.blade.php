@php $codigo = 500; @endphp
@component('errors.layout', ['codigo' => $codigo])
    @slot('slot')
        <div class="error-code">500</div>
        <div class="icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 22 20 2 20"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>
        <h1 class="title">Algo deu errado</h1>
        <div class="divider"></div>
        <p class="description">
            Ocorreu um erro interno no servidor.<br>
            Nossa equipe já foi notificada. Tente novamente em alguns instantes.
        </p>
        <div class="actions">
            <a href="javascript:location.reload()" class="btn-primary">↺ Tentar novamente</a>
            <a href="{{ url('/') }}" class="btn-secondary">Ir para o início</a>
        </div>
    @endslot
@endcomponent
