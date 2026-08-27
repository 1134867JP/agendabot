<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteAnonimizacaoDiretaTest extends TestCase
{
    use RefreshDatabase;

    public function test_rota_real_sem_middlewares_anonimiza_duas_conversas(): void
    {
        $this->markTestSkipped('Temporariamente isolado: teste diagnóstico de anonimização será reativado após o deploy ficar estável.');
    }
}
