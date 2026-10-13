<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Debounce de mensagens do bot
    |--------------------------------------------------------------------------
    |
    | Tempo (em segundos) que o processamento de uma mensagem recebida aguarda
    | na fila antes de rodar. Se o cliente enviar outra mensagem nesse intervalo,
    | o job da mensagem antiga detecta que existe uma mais nova e não responde —
    | apenas o job da última mensagem chama o Claude, com o histórico completo.
    |
    | 0 = desabilitado (processa imediatamente).
    |
    */

    'debounce_seconds' => (int) env('BOT_DEBOUNCE_SECONDS', 5),

    /*
    |--------------------------------------------------------------------------
    | Retomada do bot após atendimento humano
    |--------------------------------------------------------------------------
    |
    | Quando alguém da equipe assume a conversa (pelo painel ou respondendo
    | direto no WhatsApp do celular), o bot pausa. Depois deste tempo sem
    | nenhuma mensagem na conversa, ela é encerrada e o bot volta a atender
    | o próximo contato do cliente.
    |
    | 0 = nunca retomar automaticamente.
    |
    */

    'retomar_apos_humano_minutos' => (int) env('BOT_RETOMAR_APOS_HUMANO_MINUTOS', 120),

];
