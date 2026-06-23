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
    public array $backoff = [30, 60, 120];

    public function handle(EvolutionApiService $evolution): void
    {
        $amanha = Carbon::tomorrow();

        $agendamentos = Agendamento::with(['tenant', 'profissional', 'servico', 'cliente'])
            ->whereDate('data_hora', $amanha)
            ->whereIn('status', ['agendado', 'confirmado'])
            ->where('lembrete_enviado', false)
            ->whereHas('tenant', fn ($q) => $q->where('ativo', true)->where('bot_ativo', true))
            ->get();

        foreach ($agendamentos as $ag) {
            $tenant   = $ag->tenant;
            $telefone = $ag->cliente?->telefone ?? $ag->cliente_telefone;

            if (! $telefone) continue;

            $horario      = Carbon::parse($ag->data_hora)->format('H:i');
            $profissional = $ag->profissional?->nome ?? '';
            $servico      = $ag->servico?->nome ?? '';
            $nomeCliente  = $ag->cliente?->nome ?? $ag->cliente_nome ?? 'cliente';
            $nomeNegocio  = $tenant->nome;

            $mensagem = "Olá {$nomeCliente}! 😊\n\n"
                . "Lembrando que você tem " . ($servico ? "*{$servico}*" : "um agendamento") . " amanhã"
                . ($horario ? " às *{$horario}*" : '')
                . ($profissional ? " com *{$profissional}*" : '')
                . " na *{$nomeNegocio}*.\n\n"
                . "Confirma sua presença? Responda *SIM* para confirmar ou nos avise caso precise remarcar. Até amanhã! 👋";

            $evolution->enviarMensagem($tenant->evolution_instance, $telefone, $mensagem);
            $ag->update(['lembrete_enviado' => true]);
        }
    }
}
