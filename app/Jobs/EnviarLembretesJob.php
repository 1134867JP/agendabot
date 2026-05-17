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
use Illuminate\Support\Facades\Log;

class EnviarLembretesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(EvolutionApiService $evolution): void
    {
        $amanha = now()->addDay();

        $agendamentos = Agendamento::query()
            ->with(['recurso', 'tenant'])
            ->where('status', 'confirmado')
            ->whereDate('inicio', $amanha->toDateString())
            ->where('lembrete_enviado', false)
            ->whereHas('tenant', fn ($q) =>
                $q->where('whatsapp_conectado', true)
                  ->whereIn('subscription_status', ['trial', 'active'])
            )
            ->get();

        foreach ($agendamentos as $agendamento) {
            try {
                $mensagem = $this->montarMensagemLembrete($agendamento);

                $evolution->enviarMensagem(
                    $agendamento->tenant->evolution_instance,
                    $agendamento->cliente_telefone,
                    $mensagem,
                );

                $agendamento->update(['lembrete_enviado' => true]);

            } catch (\Throwable $e) {
                Log::error("Falha ao enviar lembrete agendamento #{$agendamento->id}: {$e->getMessage()}");
            }
        }
    }

    private function montarMensagemLembrete(Agendamento $agendamento): string
    {
        $data    = Carbon::parse($agendamento->inicio)->locale('pt_BR');
        $horario = $data->format('H:i');
        $recurso = $agendamento->recurso->nome;
        $tenant  = $agendamento->tenant->nome;

        return "👋 *Olá, {$agendamento->cliente_nome}!*\n\n"
             . "Lembrando que você tem um agendamento *amanhã*:\n\n"
             . "📅 *Data:* {$data->translatedFormat('l, d \d\e F')}\n"
             . "⏰ *Horário:* {$horario}\n"
             . "📍 *Local/Serviço:* {$recurso} — {$tenant}\n\n"
             . "Para *confirmar*, responda: ✅ *CONFIRMO*\n"
             . "Para *cancelar*, responda: ❌ *CANCELAR*\n\n"
             . "_Até amanhã!_";
    }
}
