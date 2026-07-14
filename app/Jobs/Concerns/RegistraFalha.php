<?php

namespace App\Jobs\Concerns;

use App\Models\OperationalEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

trait RegistraFalha
{
    /**
     * Registra, de forma estruturada, a falha definitiva de um job (depois de
     * esgotadas as tentativas) no canal de log 'jobs' e em operational_events,
     * para aparecer nas telas de erro do superadmin e nas análises por tenant.
     */
    protected function registrarFalha(Throwable $e, ?int $tenantId = null, array $contexto = []): void
    {
        Log::channel('jobs')->error(class_basename(static::class).'_FALHOU', array_merge([
            'tenant_id' => $tenantId,
            'exception' => get_class($e).': '.$e->getMessage(),
            'local' => $e->getFile().':'.$e->getLine(),
        ], $contexto));

        OperationalEvent::record($tenantId, 'job_failure', [
            'provider' => class_basename(static::class),
            'metadata' => array_merge(['message' => $e->getMessage()], $contexto),
        ]);
    }
}
