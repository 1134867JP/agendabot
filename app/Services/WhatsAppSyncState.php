<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WhatsAppSyncState
{
    public function iniciar(Tenant $tenant): string
    {
        $executionId = (string) Str::uuid();

        Cache::lock($this->chaveLock($tenant), 5)->block(3, function () use ($tenant, $executionId) {
            Cache::put($this->chaveStatus($tenant), [
                'execution_id' => $executionId,
                'status' => 'queued',
                'processed' => 0,
                'total' => 0,
                'imported' => 0,
                'ignored' => 0,
                'errors' => 0,
                'message' => 'Sincronização aguardando processamento.',
                'updated_at' => now()->toIso8601String(),
            ], now()->addMinutes(15));
        });

        return $executionId;
    }

    public function cancelar(Tenant $tenant, string $mensagem = 'Sincronização interrompida pelo usuário.'): array
    {
        $status = Cache::lock($this->chaveLock($tenant), 5)->block(3, function () use ($tenant, $mensagem) {
            $status = $this->status($tenant);

            if (! in_array($status['status'] ?? null, ['queued', 'running'], true)) {
                return $status;
            }

            $status = array_merge($status, [
                'status' => 'cancelled',
                'message' => $mensagem,
                'finished_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ]);

            Cache::put($this->chaveStatus($tenant), $status, now()->addMinutes(15));

            return $status;
        });
        Cache::forget($this->chaveNomes($tenant));

        return $status;
    }

    public function deveInterromper(Tenant $tenant, string $executionId): bool
    {
        $tenant->refresh();
        $status = $this->status($tenant);

        if (($status['execution_id'] ?? null) !== $executionId
            || ($status['status'] ?? null) === 'cancelled') {
            return true;
        }

        if (! $tenant->evolution_instance || ! $tenant->whatsapp_conectado) {
            $this->cancelar(
                $tenant,
                'Sincronização interrompida porque a conexão com o WhatsApp foi perdida.',
            );

            return true;
        }

        return false;
    }

    public function atualizar(Tenant $tenant, string $executionId, array $dados, int $ttlMinutos = 15): bool
    {
        return Cache::lock($this->chaveLock($tenant), 5)->block(3, function () use ($tenant, $executionId, $dados, $ttlMinutos) {
            $status = $this->status($tenant);

            if (($status['execution_id'] ?? null) !== $executionId
                || ($status['status'] ?? null) === 'cancelled') {
                return false;
            }

            Cache::put(
                $this->chaveStatus($tenant),
                array_merge($status, $dados, [
                    'execution_id' => $executionId,
                    'updated_at' => now()->toIso8601String(),
                ]),
                now()->addMinutes($ttlMinutos),
            );

            return true;
        });
    }

    public function status(Tenant $tenant): array
    {
        $status = Cache::get($this->chaveStatus($tenant), []);

        return is_array($status) ? $status : [];
    }

    public function chaveStatus(Tenant $tenant): string
    {
        return "sync_whatsapp_tenant_{$tenant->id}";
    }

    public function chaveNomes(Tenant $tenant): string
    {
        return "sync_whatsapp_nomes_tenant_{$tenant->id}";
    }

    private function chaveLock(Tenant $tenant): string
    {
        return "sync_whatsapp_lock_tenant_{$tenant->id}";
    }
}
