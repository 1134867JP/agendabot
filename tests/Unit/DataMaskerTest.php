<?php

namespace Tests\Unit;

use App\Support\DataMasker;
use PHPUnit\Framework\TestCase;

class DataMaskerTest extends TestCase
{
    public function test_mascara_dados_pessoais_inclusive_em_contextos_aninhados(): void
    {
        $masked = DataMasker::context([
            'tenant' => 10,
            'telefone' => '5554999991234',
            'payload' => ['email' => 'cliente@example.com', 'status' => 500],
        ]);

        $this->assertSame(10, $masked['tenant']);
        $this->assertStringNotContainsString('555499999', $masked['telefone']);
        $this->assertStringNotContainsString('cliente@', $masked['payload']['email']);
        $this->assertSame(500, $masked['payload']['status']);
    }

    public function test_remove_segredos_sem_preservar_sufixo(): void
    {
        $masked = DataMasker::context([
            'authorization' => 'Bearer segredo-total',
            'payload' => [
                'webhook_token' => 'token-muito-secreto',
                'api_key' => 'chave-muito-secreta',
            ],
        ]);

        $this->assertSame('[REDACTED]', $masked['authorization']);
        $this->assertSame('[REDACTED]', $masked['payload']['webhook_token']);
        $this->assertSame('[REDACTED]', $masked['payload']['api_key']);
    }
}
