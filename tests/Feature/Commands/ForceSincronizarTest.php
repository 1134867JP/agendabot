<?php

namespace Tests\Feature\Commands;

use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ForceSincronizarTest extends TestCase
{
    use RefreshDatabase;

    public function test_comando_inicia_estado_e_dispara_job_com_execution_id(): void
    {
        Queue::fake();

        $tenant = Tenant::create([
            'nome' => 'Barbearia Sync',
            'slug' => 'barbearia-sync',
            'tipo_servico' => 'barbeiro',
            'evolution_instance' => 'barbearia-sync',
            'whatsapp_conectado' => true,
            'ativo' => true,
        ]);

        $this->artisan('whatsapp:sync', ['tenant_slug' => $tenant->slug])
            ->assertSuccessful();

        Queue::assertPushed(SincronizarConversasWhatsappJob::class);
    }
}
