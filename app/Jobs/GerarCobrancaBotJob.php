<?php

namespace App\Jobs;

use App\Models\Agendamento;
use App\Models\CobrancaBot;
use App\Models\Tenant;
use App\Services\AsaasService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GerarCobrancaBotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 600];

    public function handle(AsaasService $asaas): void
    {
        $periodoAnterior = now()->subMonth()->format('Y-m');
        $inicioMes = Carbon::parse($periodoAnterior . '-01')->startOfMonth();
        $fimMes    = $inicioMes->copy()->endOfMonth();

        Tenant::where('ativo', true)
            ->where('subscription_status', 'active')
            ->whereNotNull('asaas_customer_id')
            ->each(function (Tenant $tenant) use ($asaas, $periodoAnterior, $inicioMes, $fimMes) {
                if (CobrancaBot::where('tenant_id', $tenant->id)->where('periodo', $periodoAnterior)->exists()) {
                    return; // já gerada para este período
                }

                $quantidade = Agendamento::where('tenant_id', $tenant->id)
                    ->where('origem', 'bot')
                    ->where('status', '!=', 'cancelado')
                    ->whereBetween('created_at', [$inicioMes, $fimMes])
                    ->count();

                if ($quantidade === 0) {
                    return; // nada a cobrar
                }

                $taxa       = (float) $tenant->taxa_agendamento_bot;
                $valorTotal = round($quantidade * $taxa, 2);

                // Os planos atuais usam mensalidade fixa, sem cobrança variável.
                if ($taxa <= 0) {
                    return;
                }

                // Tenant isento: registrar uso mas não cobrar
                if ($tenant->isento_cobranca) {
                    CobrancaBot::create([
                        'tenant_id'               => $tenant->id,
                        'periodo'                 => $periodoAnterior,
                        'quantidade_agendamentos' => $quantidade,
                        'valor_total'             => $valorTotal,
                        'asaas_charge_id'         => null,
                        'status'                  => 'isento',
                    ]);

                    Log::channel('jobs')->info('GerarCobrancaBotJob: tenant isento, sem cobrança', [
                        'tenant'     => $tenant->id,
                        'periodo'    => $periodoAnterior,
                        'quantidade' => $quantidade,
                        'valor_ref'  => $valorTotal,
                    ]);
                    return;
                }

                $vencimento = now()->addDays(5);
                $descricao  = "AgendaBot — Taxa bot {$periodoAnterior}: {$quantidade} agendamento(s) × R$ " . number_format($taxa, 2, ',', '.');

                $chargeId = $asaas->criarCobrancaAvulsa(
                    $tenant->asaas_customer_id,
                    $valorTotal,
                    $descricao,
                    $vencimento,
                );

                CobrancaBot::create([
                    'tenant_id'               => $tenant->id,
                    'periodo'                 => $periodoAnterior,
                    'quantidade_agendamentos' => $quantidade,
                    'valor_total'             => $valorTotal,
                    'asaas_charge_id'         => $chargeId,
                    'status'                  => $chargeId ? 'pendente' : 'falhou',
                ]);

                Log::channel('jobs')->info('GerarCobrancaBotJob', [
                    'tenant'      => $tenant->id,
                    'periodo'     => $periodoAnterior,
                    'quantidade'  => $quantidade,
                    'valor_total' => $valorTotal,
                    'charge_id'   => $chargeId,
                ]);
            });
    }
}
