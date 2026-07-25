<?php

namespace App\Services;

use App\Models\Agendamento;
use App\Models\Tenant;

/**
 * Ponto de entrada central do domínio conversacional do Agendou.
 *
 * Providers de IA interpretam e solicitam ferramentas; todas as ferramentas e
 * regras de agendamento continuam sendo executadas pelo backend Laravel.
 */
class AgendouService
{
    public function __construct(private AgendouAgentService $agent) {}

    public function processarMensagem(
        Tenant $tenant,
        array $mensagens,
        array $clienteInfo,
        ?Agendamento $agendamentoPendente = null,
    ): array {
        return $this->agent->processar($tenant, $mensagens, $clienteInfo, $agendamentoPendente);
    }
}
