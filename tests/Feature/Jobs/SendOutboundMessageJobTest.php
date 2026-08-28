<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendOutboundMessageJob;
use App\Models\Agendamento;
use App\Models\OutboundMessage;
use App\Models\Tenant;
use App\Services\EvolutionApiService;
use App\Services\OutboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SendOutboundMessageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_falha_na_evolution_mantem_mensagem_pendente_e_nao_marca_lembrete(): void
    {
        Queue::fake();
        [$agendamento, $outbound] = $this->queueReminder();

        $evolution = $this->mock(EvolutionApiService::class);
        $evolution->shouldReceive('enviarMensagem')->once()->andReturnFalse();

        try {
            (new SendOutboundMessageJob($outbound->id))->handle($evolution);
            $this->fail('Era esperada uma falha de envio.');
        } catch (RuntimeException) {
            // O worker fará a próxima tentativa usando o backoff do job.
        }

        $outbound->refresh();
        $this->assertSame(OutboundMessage::STATUS_PENDING, $outbound->status);
        $this->assertSame(1, $outbound->attempts);
        $this->assertNotNull($outbound->last_error);
        $this->assertFalse($agendamento->fresh()->lembrete_enviado);
    }

    public function test_sucesso_confirma_saida_e_so_entao_marca_lembrete(): void
    {
        Queue::fake();
        [$agendamento, $outbound] = $this->queueReminder();

        $evolution = $this->mock(EvolutionApiService::class);
        $evolution->shouldReceive('enviarMensagem')
            ->once()
            ->with('instancia-outbox', '5551999999999', 'Lembrete de teste')
            ->andReturnTrue();

        (new SendOutboundMessageJob($outbound->id))->handle($evolution);

        $outbound->refresh();
        $this->assertSame(OutboundMessage::STATUS_SENT, $outbound->status);
        $this->assertNotNull($outbound->sent_at);
        $this->assertTrue($agendamento->fresh()->lembrete_enviado);
    }

    private function queueReminder(): array
    {
        $tenant = Tenant::create([
            'nome' => 'Tenant Outbox',
            'slug' => 'tenant-outbox',
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'whatsapp_conectado' => true,
            'evolution_instance' => 'instancia-outbox',
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $agendamento = Agendamento::create([
            'tenant_id' => $tenant->id,
            'cliente_nome' => 'Cliente Outbox',
            'cliente_telefone' => '5551999999999',
            'inicio' => now()->addDay(),
            'fim' => now()->addDay()->addMinutes(30),
            'data_hora' => now()->addDay(),
            'duracao_minutos' => 30,
            'status' => 'agendado',
            'origem' => 'manual',
            'lembrete_enviado' => false,
        ]);

        $outbound = app(OutboundMessageService::class)->queue(
            tenant: $tenant,
            telefone: $agendamento->cliente_telefone,
            conteudo: 'Lembrete de teste',
            purpose: 'appointment_reminder',
            idempotencyKey: "appointment-reminder:{$agendamento->id}",
            agendamento: $agendamento,
        );

        return [$agendamento, $outbound];
    }
}
