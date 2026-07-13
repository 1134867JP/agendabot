<x-mail::message>
# Seu período de teste encerrou

Olá! Obrigado por testar o **Agendou** com o estabelecimento **{{ $tenant->nome }}**.

Seu trial de 14 dias chegou ao fim. Para continuar recebendo agendamentos pelo WhatsApp sem interrupção, escolha um plano.

<x-mail::button :url="config('app.url') . '/renovar'" color="primary">
Escolher meu plano
</x-mail::button>

**Planos a partir de R$79,90/mês** — sem contrato, cancele quando quiser.

Se tiver qualquer dúvida, responda este e-mail.

— Equipe Agendou
</x-mail::message>
