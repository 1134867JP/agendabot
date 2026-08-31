<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\EvolutionApiException;
use App\Http\Controllers\Controller;
use App\Jobs\SincronizarConversasWhatsappJob;
use App\Models\Tenant;
use App\Services\EvolutionApiService;
use App\Services\WhatsAppConversationBackupService;
use App\Services\WhatsAppSyncState;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppController extends Controller
{
    public function __construct(
        private EvolutionApiService $evolution,
        private WhatsAppConversationBackupService $backups,
        private WhatsAppSyncState $syncState,
    ) {}

    private function webhookUrl(Tenant $tenant): string
    {
        if (! $tenant->webhook_token) {
            $tenant->update([
                'webhook_token' => Str::random(64),
                'webhook_token_rotated_at' => now(),
            ]);
            $tenant->refresh();
        }

        return route('webhook', $tenant->slug);
    }

    public function index(): Response
    {
        $tenant = app('tenant');

        return Inertia::render('Tenant/WhatsApp', [
            'tenant' => $tenant,
            'ultimo_backup' => $this->backups->ultimoBackup($tenant),
        ]);
    }

    public function qrcode(): JsonResponse
    {
        try {
            $tenant = app('tenant');
            $instance = $tenant->evolution_instance;

            if (! $this->evolution->configurado()) {
                throw EvolutionApiException::configuracaoAusente();
            }

            if (! $instance) {
                // Mesmo nome usado no onboarding e no CreateEvolutionInstanceJob (slug puro),
                // evitando criar uma instância divergente.
                $instance = $tenant->slug;
                $tenant->update(['evolution_instance' => $instance]);
            }

            $webhookUrl = $this->webhookUrl($tenant);
            $status = $this->evolution->statusInstancia($instance);

            // Já conectado — garantir webhook atualizado e avisar o frontend.
            if ($status === 'open') {
                $eraConectado = $tenant->whatsapp_conectado;
                $tenant->update(['whatsapp_conectado' => true]);
                $this->evolution->configurarWebhook($instance, $webhookUrl, $tenant->webhook_token);
                if (! $eraConectado) {
                    $this->iniciarSincronizacaoInicial($tenant);
                }

                return response()->json(['connected' => true]);
            }

            // Instância não existe — criar e configurar webhook.
            if ($status === 'desconhecido') {
                $result = $this->evolution->criarInstancia($instance);
                $this->evolution->configurarWebhook($instance, $webhookUrl, $tenant->webhook_token);

                $qrcode = data_get($result, 'qrcode.base64') ?? data_get($result, 'base64');
                if ($qrcode) {
                    return response()->json(['qrcode' => $qrcode]);
                }
            }

            // Instância existe mas não conectada — garantir webhook atualizado e buscar QR.
            $this->evolution->configurarWebhook($instance, $webhookUrl, $tenant->webhook_token);
            $qrcode = $this->evolution->obterQrCode($instance);

            if (! $qrcode) {
                Log::warning('WHATSAPP_QRCODE_AUSENTE', [
                    'tenant' => $tenant->id,
                    'instance' => $instance,
                    'status' => $status,
                ]);

                return response()->json([
                    'erro' => 'O WhatsApp ainda está preparando o QR Code. Aguarde alguns segundos e tente novamente.',
                ], 503);
            }

            return response()->json(['qrcode' => $qrcode]);
        } catch (EvolutionApiException|ConnectionException $e) {
            Log::warning('WHATSAPP_QRCODE_INDISPONIVEL', [
                'tenant' => app('tenant')->id,
                'erro' => $e->getMessage(),
            ]);

            return response()->json([
                'erro' => 'Não foi possível conectar ao WhatsApp agora. Tente novamente em alguns minutos.',
            ], 503);
        }
    }

    public function desconectar(): JsonResponse
    {
        $tenant = app('tenant');

        if (! $tenant->evolution_instance) {
            return response()->json(['ok' => false, 'erro' => 'Instância não configurada.'], 400);
        }

        if (! $this->evolution->desconectar($tenant->evolution_instance)) {
            return response()->json([
                'ok' => false,
                'erro' => 'O WhatsApp não confirmou a desconexão. Nada foi removido.',
            ], 502);
        }

        $tenant->update(['whatsapp_conectado' => false]);
        $this->syncState->cancelar(
            $tenant,
            'Sincronização interrompida porque o WhatsApp foi desconectado.',
        );

        try {
            $backup = $this->backups->criarBackup($tenant);
            $limpeza = $this->backups->limparConversas($tenant);
        } catch (\Throwable $e) {
            Log::error('WHATSAPP_BACKUP_FALHOU', [
                'tenant' => $tenant->id,
                'erro' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'desconectado' => true,
                'erro' => 'WhatsApp desconectado, mas o backup falhou. As conversas foram preservadas.',
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'backup' => $backup,
            'limpeza' => $limpeza,
        ]);
    }

    public function baixarBackup(string $arquivo): StreamedResponse
    {
        $tenant = app('tenant');
        $nomeDownload = preg_replace('/\\.enc$/', '', $arquivo);

        return response()->streamDownload(
            fn () => print $this->backups->conteudo($tenant, $arquivo),
            $nomeDownload,
            [
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store, private',
            ],
        );
    }

    public function status(): JsonResponse
    {
        $tenant = app('tenant');
        $status = $this->evolution->statusInstancia($tenant->evolution_instance);

        $conectado = $status === 'open';
        if ($conectado !== $tenant->whatsapp_conectado) {
            $tenant->update(['whatsapp_conectado' => $conectado]);
            if ($conectado) {
                $this->iniciarSincronizacaoInicial($tenant);
            }
        }

        if (! $conectado) {
            $this->syncState->cancelar(
                $tenant,
                'Sincronização interrompida porque a conexão com o WhatsApp foi perdida.',
            );
        }

        return response()->json(['status' => $status]);
    }

    private function iniciarSincronizacaoInicial(Tenant $tenant): void
    {
        $executionId = $this->syncState->iniciar($tenant);
        SincronizarConversasWhatsappJob::dispatch($tenant, $executionId)->onQueue('sync');
    }
}
