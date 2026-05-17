<x-mail::message>
# Pagamento pendente

Olá, **{{ $tenant->nome }}**!

Identificamos que o pagamento da sua assinatura AgendaBot não foi processado.

**Você tem 3 dias** antes do sistema ser suspenso.

<x-mail::button :url="route('tenant.renovar')" color="red">
Renovar agora
</x-mail::button>

Se já pagou, aguarde alguns minutos para a confirmação automática.

Qualquer dúvida, responda este e-mail.

— Equipe AgendaBot
</x-mail::message>
