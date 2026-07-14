<x-mail::message>
# 🐛 Erro em produção

**Classe:** {{ $classe }}

**Mensagem:** {{ $mensagem }}

**Local:** {{ $arquivo }}:{{ $linha }}

@if($url)
**URL:** {{ $url }}
@endif

<x-mail::panel>
{{ $trace }}
</x-mail::panel>

<x-mail::button :url="config('app.url') . '/superadmin/logs'" color="primary">
Ver logs no painel
</x-mail::button>

— Agendou
</x-mail::message>
