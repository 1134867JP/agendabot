<?php

namespace App\Support;

class FailedJobsFormatter
{
    /**
     * Formata uma linha da tabela `failed_jobs` num array legível, reutilizado tanto pelo
     * painel superadmin quanto pelo comando de alerta de jobs falhados.
     */
    public static function formatar(object $job): array
    {
        $payload = json_decode($job->payload, true);
        $jobClass = data_get($payload, 'displayName', 'Desconhecido');
        $exception = $job->exception ?? '';

        // Extrair apenas a primeira linha da exception (mais legível)
        $firstLine = trim(explode("\n", $exception)[0]);

        return [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'job' => class_basename($jobClass),
            'job_full' => $jobClass,
            'queue' => $job->queue,
            'error' => $firstLine,
            'exception' => $exception,
            'failed_at' => $job->failed_at,
            'connection' => $job->connection,
        ];
    }
}
