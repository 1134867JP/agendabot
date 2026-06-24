@php $codigo = 419; @endphp
@component('errors.layout', ['codigo' => $codigo])
    @slot('slot')
        <div class="error-code">419</div>
        <div class="icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
        </div>
        <h1 class="title">Sessão expirada</h1>
        <div class="divider"></div>
        <p class="description">
            Sua sessão expirou por inatividade.<br>
            Volte à página anterior e tente novamente.
        </p>
        <div class="actions">
            <a href="javascript:history.back()" class="btn-primary">← Voltar e tentar novamente</a>
        </div>
    @endslot
@endcomponent
