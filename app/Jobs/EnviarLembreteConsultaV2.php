<?php

namespace App\Jobs;

use App\Models\Agendamento;
use App\Services\EvolutionApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnviarLembreteConsultaV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public readonly Agendamento $agendamento,
    ) {}

    public function handle(EvolutionApiService $evolution): void
    {
        $tenant = $this->agendamento->tenant;
        if (! $tenant->evolution_instance) {
            return;
        }

        $inicio = Carbon::parse($this->agendamento->data_hora ?? $this->agendamento->inicio);
        $recurso = optional($this->agendamento->recurso)->nome
                 ?? optional($this->agendamento->servico)->nome
                 ?? 'serviço';

        $mensagem = "Olá, {$this->agendamento->cliente_nome}! 👋\n"
            . "Lembramos que você tem um agendamento amanhã:\n"
            . "📍 *{$recurso}*\n"
            . "⏰ *{$inicio->format('H:i')}*\n"
            . "Até lá! 😊";

        $evolution->enviarMensagem(
            $tenant->evolution_instance,
            $this->agendamento->cliente_telefone,
            $mensagem,
        );
    }
}
