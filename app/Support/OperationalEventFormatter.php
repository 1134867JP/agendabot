<?php

namespace App\Support;

use App\Models\OperationalEvent;

class OperationalEventFormatter
{
    /**
     * Os emissores de operational_events guardam a mensagem da falha em chaves
     * diferentes (ou nem guardam). Monta a melhor descrição disponível em vez do
     * genérico "Falha registrada" exibido no front quando não há 'message'/'evento'.
     */
    public static function mensagem(OperationalEvent $evento): ?string
    {
        $metadata = $evento->metadata ?? [];

        $mensagem = data_get($metadata, 'message') ?? data_get($metadata, 'evento') ?? data_get($metadata, 'error');
        if (filled($mensagem)) {
            return (string) $mensagem;
        }

        $detalhes = array_filter([
            data_get($metadata, 'status'),
            data_get($metadata, 'error_type'),
            data_get($metadata, 'operation'),
        ], fn ($valor) => filled($valor));

        return $detalhes !== [] ? implode(' · ', $detalhes) : null;
    }
}
