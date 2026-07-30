<?php

namespace App\Exceptions;

use RuntimeException;

class EvolutionApiException extends RuntimeException
{
    public static function configuracaoAusente(): self
    {
        return new self('A integração com o WhatsApp não está configurada.');
    }

    public static function requisicaoFalhou(string $operacao, int $status): self
    {
        return new self("A Evolution API recusou a operação {$operacao} (HTTP {$status}).");
    }

    public static function webhookNaoConfigurado(): self
    {
        return new self('A instância foi criada, mas o webhook não pôde ser configurado.');
    }
}
