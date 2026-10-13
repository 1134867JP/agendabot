<?php

namespace Tests\Feature;

use App\Models\Agendamento;
use App\Models\Tenant;
use Database\Seeders\BarbeariaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarbeariaDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_monta_a_barbearia_de_demonstracao_e_pode_rodar_de_novo(): void
    {
        $this->comVariaveis(['DEMO_BARBEARIA_EMAIL' => 'demo@barbearia.test', 'DEMO_BARBEARIA_PASSWORD' => 'senha-de-demonstracao']);

        $this->seed(BarbeariaDemoSeeder::class);
        $this->seed(BarbeariaDemoSeeder::class);

        $tenant = Tenant::where('slug', 'barbearia-do-joao-demo')->firstOrFail();
        $this->assertSame(3, $tenant->profissionais()->count());
        $this->assertSame(6, $tenant->servicos()->count());
        $this->assertSame(14, Agendamento::where('tenant_id', $tenant->id)->count());
        $this->assertTrue(Agendamento::where('tenant_id', $tenant->id)->get()->contains(
            fn (Agendamento $a) => $a->descricaoServicos() === 'Corte + Sobrancelha' && (float) $a->valor_total === 65.0
        ));
    }

    private function comVariaveis(array $variaveis): void
    {
        foreach ($variaveis as $chave => $valor) {
            putenv("{$chave}={$valor}");
            $_ENV[$chave] = $_SERVER[$chave] = $valor;
        }
        $this->beforeApplicationDestroyed(function () use ($variaveis): void {
            foreach (array_keys($variaveis) as $chave) {
                putenv($chave);
                unset($_ENV[$chave], $_SERVER[$chave]);
            }
        });
    }
}
