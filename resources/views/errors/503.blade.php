@php $codigo = 503; @endphp
@component('errors.layout', ['codigo' => $codigo])
    @slot('slot')
        <div class="error-code">503</div>
        <div class="icon-wrap">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/>
                <path d="M12 6v6l4 2"/>
            </svg>
        </div>
        <h1 class="title">Em manutenção</h1>
        <div class="divider"></div>
        <p class="description">
            O Agendou está temporariamente fora do ar para manutenção.<br>
            Voltaremos em breve. Obrigado pela paciência!
        </p>
        <div class="actions">
            <a href="javascript:location.reload()" class="btn-primary">↺ Tentar novamente</a>
        </div>
    @endslot
@endcomponent
