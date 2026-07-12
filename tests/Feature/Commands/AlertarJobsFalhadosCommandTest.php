<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class AlertarJobsFalhadosCommandTest extends TestCase
{
    use RefreshDatabase;

    private function inserirFailedJob(string $jobClass = 'App\\Jobs\\ExemploJob', string $erro = "Exception: erro simulado\nstack trace..."): int
    {
        return DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => $jobClass]),
            'exception' => $erro,
            'failed_at' => now(),
        ]);
    }

    public function test_alerta_apenas_jobs_novos_e_atualiza_cache(): void
    {
        Log::shouldReceive('channel')->with('daily')->andReturnSelf();
        Log::shouldReceive('critical')->once();

        $this->inserirFailedJob();

        Artisan::call('jobs:alertar-falhas');

        $this->assertNotNull(Cache::get('jobs_falhados_ultimo_id_verificado'));
    }

    public function test_nao_reenvia_alerta_para_jobs_ja_verificados(): void
    {
        Log::shouldReceive('channel')->with('daily')->andReturnSelf();
        Log::shouldReceive('critical')->once();

        $this->inserirFailedJob();

        Artisan::call('jobs:alertar-falhas');
        // Segunda execução, sem jobs novos — não deve alertar de novo
        Artisan::call('jobs:alertar-falhas');
    }

    public function test_alerta_de_novo_quando_surge_um_job_novo(): void
    {
        Log::shouldReceive('channel')->with('daily')->andReturnSelf();
        Log::shouldReceive('critical')->twice();

        $this->inserirFailedJob();
        Artisan::call('jobs:alertar-falhas');

        $this->inserirFailedJob();
        Artisan::call('jobs:alertar-falhas');
    }

    public function test_nao_faz_nada_quando_nao_ha_jobs_falhados(): void
    {
        Log::shouldReceive('channel')->never();

        Artisan::call('jobs:alertar-falhas');

        $this->assertSame(0, Cache::get('jobs_falhados_ultimo_id_verificado', 0));
    }
}
