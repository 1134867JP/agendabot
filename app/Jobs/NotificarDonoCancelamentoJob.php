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

class NotificarDonoCancelamentoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private Agendamento $agendamento) {}

    public function handle(EvolutionApiService $evolution): void
    {
        $tenant = $this->agendamento->tenant;

        if (! $tenant->whatsapp_conectado) {
            return;
        }

        $data  = Carbon::parse($this->agendamento->inicio)->locale('pt_BR');
        $donos = $tenant->users()->wherePivot('papel', 'admin')->get();

        foreach ($donos as $dono) {
            if (empty($dono->telefone)) {
                continue;
            }

            try {
                $evolution->enviarMensagem(
                    $tenant->evolution_instance,
                    $dono->telefone,
                    "⚠️ *Cancelamento recebido*\n\n"
                    . "Cliente: {$this->agendamento->cliente_nome}\n"
                    . "Horário: {$data->translatedFormat('l, d \d\e F')} às {$data->format('H:i')}\n"
                    . "Serviço: {$this->agendamento->recurso->nome}\n\n"
                    . "O slot ficou disponível automaticamente.",
                );
            } catch (\Throwable $e) {
                Log::error("Falha ao notificar dono do cancelamento #{$this->agendamento->id}: {$e->getMessage()}");
            }
        }
    }
}
