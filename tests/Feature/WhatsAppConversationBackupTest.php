<?php

namespace Tests\Feature;

use App\Jobs\BackupELimparHistoricoJob;
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

    public function test_cria_backup_criptografado_antes_de_limpar_e_preserva_clientes(): void
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
        $path = "whatsapp-backups/tenant-{$tenant->id}/{$backup['arquivo']}";

        Storage::disk('local')->assertExists($path);
        $this->assertStringNotContainsString(
            'Olá, gostaria de agendar.',
            Storage::disk('local')->get($path),
        );

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

    public function test_retencao_automatica_respeita_o_plano(): void
    {
        Storage::fake('local');
        $starter = Tenant::create([
            'nome' => 'Starter', 'slug' => 'starter-retencao', 'tipo_servico' => 'clinica',
            'plano' => 'starter', 'ativo' => true,
        ]);
        $business = Tenant::create([
            'nome' => 'Business', 'slug' => 'business-retencao', 'tipo_servico' => 'clinica',
            'plano' => 'business', 'ativo' => true,
        ]);

        foreach ([$starter, $business] as $tenant) {
            $conversa = Conversa::create([
                'tenant_id' => $tenant->id,
                'telefone_cliente' => '555199999'.str_pad((string) $tenant->id, 4, '0', STR_PAD_LEFT),
                'status_v2' => 'ativa',
                'ultima_mensagem_em' => now()->subDays(40),
            ]);
            Mensagem::create([
                'conversa_id' => $conversa->id,
                'remetente' => 'cliente',
                'tipo' => 'texto',
                'conteudo' => 'Mensagem antiga',
                'evolution_message_id' => "RETENCAO-{$tenant->id}",
                'enviada_em' => now()->subDays(40),
            ]);
        }

        (new BackupELimparHistoricoJob($starter))->handle();
        (new BackupELimparHistoricoJob($business))->handle();

        $this->assertDatabaseMissing('mensagens', ['evolution_message_id' => "RETENCAO-{$starter->id}"]);
        $this->assertDatabaseHas('mensagens', ['evolution_message_id' => "RETENCAO-{$business->id}"]);
        $this->assertNotEmpty(Storage::disk('local')->files("backups/tenant-{$starter->id}"));
    }
}
