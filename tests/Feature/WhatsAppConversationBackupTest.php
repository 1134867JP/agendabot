<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Conversa;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Services\WhatsAppConversationBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhatsAppConversationBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_backup_antes_de_limpar_e_preserva_clientes(): void
    {
        Storage::fake('local');

        $tenant = Tenant::create([
            'nome' => 'Clínica Backup',
            'slug' => 'clinica-backup',
            'tipo_servico' => 'clinica',
            'ativo' => true,
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Cliente Teste',
            'telefone' => '5551999999999',
            'observacoes' => 'Prefere atendimento pela manhã.',
        ]);

        $conversa = Conversa::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'telefone_cliente' => $cliente->telefone,
            'status_v2' => 'ativa',
            'ultima_mensagem_em' => now(),
        ]);

        Mensagem::create([
            'conversa_id' => $conversa->id,
            'remetente' => 'cliente',
            'tipo' => 'texto',
            'conteudo' => 'Olá, gostaria de agendar.',
            'evolution_message_id' => 'BACKUP-MSG-1',
            'enviada_em' => now(),
        ]);

        $service = app(WhatsAppConversationBackupService::class);
        $backup = $service->criarBackup($tenant);

        Storage::disk('local')->assertExists(
            "whatsapp-backups/tenant-{$tenant->id}/{$backup['arquivo']}",
        );

        $conteudoCriptografado = Storage::disk('local')
            ->get("whatsapp-backups/tenant-{$tenant->id}/{$backup['arquivo']}");
        $this->assertStringNotContainsString('Olá, gostaria de agendar.', $conteudoCriptografado);

        $json = json_decode(
            $service->conteudo($tenant, $backup['arquivo']),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(1, $json['resumo']['clientes']);
        $this->assertSame(1, $json['resumo']['conversas']);
        $this->assertSame(1, $json['resumo']['mensagens']);
        $this->assertSame('Olá, gostaria de agendar.', $json['conversas'][0]['mensagens'][0]['conteudo']);

        $resultado = $service->limparConversas($tenant);

        $this->assertSame(['conversas' => 1, 'mensagens' => 1], $resultado);
        $this->assertDatabaseMissing('conversas', ['id' => $conversa->id]);
        $this->assertDatabaseMissing('mensagens', ['evolution_message_id' => 'BACKUP-MSG-1']);
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'nome' => 'Cliente Teste',
        ]);
    }
}
