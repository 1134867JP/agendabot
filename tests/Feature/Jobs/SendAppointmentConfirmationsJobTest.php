<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SendAppointmentConfirmationsJob;
use App\Models\Agendamento;
use App\Models\OutboundMessage;
use App\Models\Tenant;
use App\Services\OutboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendAppointmentConfirmationsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_barbearia_nao_recebe_pedido_de_confirmacao_antecipada_por_padrao(): void
    {
        $this->rodarParaTipo('barbeiro');

        $this->assertSame(0, OutboundMessage::where('purpose', 'appointment_confirmation')->count());
    }

    public function test_barbearia_pode_ligar_confirmacao_antecipada(): void
    {
        $this->rodarParaTipo('barbeiro', ['confirmacao_antecipada' => true]);

        $this->assertSame(1, OutboundMessage::where('purpose', 'appointment_confirmation')->count());
    }

    public function test_outros_ramos_mantem_confirmacao_antecipada(): void
    {
        $this->rodarParaTipo('clinica');

        $this->assertSame(1, OutboundMessage::where('purpose', 'appointment_confirmation')->count());
    }

    private function rodarParaTipo(string $tipo, array $configuracoes = []): void
    {
        Queue::fake();
        $tenant = Tenant::create([
            'nome' => "Tenant {$tipo}",
            'slug' => "tenant-confirmacao-{$tipo}",
            'tipo_servico' => $tipo,
            'ativo' => true,
            'whatsapp_conectado' => true,
            'evolution_instance' => "instancia-{$tipo}",
            'configuracoes' => $configuracoes,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $inicio = now()->addHours(40);
        Agendamento::create([
            'tenant_id' => $tenant->id,
            'cliente_nome' => 'Cliente',
            'cliente_telefone' => '5551999999999',
            'inicio' => $inicio,
            'fim' => $inicio->copy()->addMinutes(30),
            'data_hora' => $inicio,
            'duracao_minutos' => 30,
            'status' => 'agendado',
            'origem' => 'manual',
        ]);

        (new SendAppointmentConfirmationsJob)->handle(app(OutboundMessageService::class));
    }
}
