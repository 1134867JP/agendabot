<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EvolutionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CorrigirNomesClientesCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->tenant = Tenant::create([
            'nome'                => 'Barbearia Correcao',
            'slug'                => 'barbearia-correcao',
            'tipo_servico'        => 'barbeiro',
            'ativo'               => true,
            'evolution_instance'  => 'instancia-teste',
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(14),
        ]);
        $this->tenant->users()->attach($user->id, ['papel' => 'admin']);
    }

    public function test_corrige_cliente_com_nome_voce_via_findcontacts(): void
    {
        $telefone = '5551977777777';
        $cliente  = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => 'Você',
        ]);

        $this->mock(EvolutionApiService::class, function ($mock) use ($telefone) {
            $mock->shouldReceive('fetchContacts')->andReturn([
                ['remoteJid' => "{$telefone}@s.whatsapp.net", 'pushName' => 'Cliente Real'],
            ]);
            $mock->shouldReceive('fetchMessages')->andReturn([]);
        });

        Artisan::call('clientes:corrigir-nomes', ['--tenant' => $this->tenant->slug]);

        $this->assertSame('Cliente Real', $cliente->fresh()->nome);
    }

    public function test_corrige_cliente_com_nome_igual_ao_telefone_via_mensagens(): void
    {
        $telefone = '5551966666666';
        $cliente  = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => $telefone,
        ]);

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('fetchContacts')->andReturn([]);
            $mock->shouldReceive('fetchMessages')->andReturn([
                ['key' => ['fromMe' => true], 'pushName' => 'Você'],
                ['key' => ['fromMe' => false], 'pushName' => 'Cliente Via Mensagem'],
            ]);
        });

        Artisan::call('clientes:corrigir-nomes', ['--tenant' => $this->tenant->slug]);

        $this->assertSame('Cliente Via Mensagem', $cliente->fresh()->nome);
    }

    public function test_dry_run_nao_persiste_alteracoes(): void
    {
        $telefone = '5551955555555';
        $cliente  = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => 'Você',
        ]);

        $this->mock(EvolutionApiService::class, function ($mock) use ($telefone) {
            $mock->shouldReceive('fetchContacts')->andReturn([
                ['remoteJid' => "{$telefone}@s.whatsapp.net", 'pushName' => 'Cliente Real'],
            ]);
            $mock->shouldReceive('fetchMessages')->andReturn([]);
        });

        Artisan::call('clientes:corrigir-nomes', ['--tenant' => $this->tenant->slug, '--dry-run' => true]);

        $this->assertSame('Você', $cliente->fresh()->nome);
    }

    public function test_mantem_telefone_quando_nao_encontra_nome_valido(): void
    {
        $telefone = '5551944444444';
        $cliente  = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'telefone'  => $telefone,
            'nome'      => $telefone,
        ]);

        $this->mock(EvolutionApiService::class, function ($mock) {
            $mock->shouldReceive('fetchContacts')->andReturn([]);
            $mock->shouldReceive('fetchMessages')->andReturn([]);
        });

        Artisan::call('clientes:corrigir-nomes', ['--tenant' => $this->tenant->slug]);

        $this->assertSame($telefone, $cliente->fresh()->nome);
    }
}
