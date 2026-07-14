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

];
