<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\OperationalEvent;
use App\Support\FailedJobsFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JobsController extends Controller
{
    public function index(): Response
    {
        $fila = $this->listarFila(100);

        return Inertia::render('SuperAdmin/Jobs', [
            'failed' => $this->listarFailed(50),
            'queue' => $this->statsQueue($fila),
            'queuedJobs' => $fila,
            'falhasRecentes' => $this->listarFalhasRecentes(30),
        ]);
    }

    public function retry(int $id): RedirectResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => [$id]]);
            return back()->with('success', "Job #{$id} reenfileirado.");
        } catch (\Throwable $e) {
            return back()->with('erro', "Falha ao retentar: {$e->getMessage()}");
        }
    }

    public function retryAll(): RedirectResponse
    {
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
            return back()->with('success', 'Todos os jobs reenfileirados.');
        } catch (\Throwable $e) {
            return back()->with('erro', "Falha: {$e->getMessage()}");
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            DB::table('failed_jobs')->where('id', $id)->delete();
            return back()->with('success', "Job #{$id} removido.");
        } catch (\Throwable $e) {
            return back()->with('erro', "Falha ao remover: {$e->getMessage()}");
        }
    }

    public function destroyAll(): RedirectResponse
    {
        try {
            Artisan::call('queue:flush');
            return back()->with('success', 'Fila de falhas limpa.');
        } catch (\Throwable $e) {
            return back()->with('erro', "Falha: {$e->getMessage()}");
        }
    }

    private function listarFila(int $limit): array
    {
        try {
            $agora = now()->timestamp;

            return DB::table('jobs')->orderByDesc('id')->limit($limit)->get()->map(function ($job) use ($agora) {
                $payload = json_decode($job->payload, true) ?: [];
                $status = $job->reserved_at
                    ? 'processing'
                    : ($job->available_at > $agora ? 'delayed' : 'waiting');

                return [
                    'id' => $job->id,
                    'job' => class_basename((string) ($payload['displayName'] ?? 'Job desconhecido')),
                    'queue' => $job->queue,
                    'status' => $status,
                    'attempts' => $job->attempts,
                    'created_at' => date(DATE_ATOM, $job->created_at),
                    'available_at' => date(DATE_ATOM, $job->available_at),
                    'reserved_at' => $job->reserved_at ? date(DATE_ATOM, $job->reserved_at) : null,
                    'waiting_seconds' => max(0, $agora - $job->created_at),
                ];
            })->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function listarFailed(int $limit): array
    {
        try {
            return DB::table('failed_jobs')->orderByDesc('failed_at')->limit($limit)->get()
                ->map(fn ($job) => FailedJobsFormatter::formatar($job))->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    private function statsQueue(array $fila): array
    {
        try {
            $workerLastSeen = Cache::get('queue_worker_last_seen_at');
            $workerHealthy = $workerLastSeen && now()->diffInSeconds($workerLastSeen) <= 120;

            return [
                'failed' => DB::table('failed_jobs')->count(),
                'pending' => count(array_filter($fila, fn ($job) => $job['status'] === 'waiting')),
                'processing' => count(array_filter($fila, fn ($job) => $job['status'] === 'processing')),
                'delayed' => count(array_filter($fila, fn ($job) => $job['status'] === 'delayed')),
                'total' => DB::table('jobs')->count(),
                'oldest_wait_seconds' => collect($fila)->where('status', 'waiting')->max('waiting_seconds') ?? 0,
                'worker_status' => $workerHealthy ? 'online' : 'offline',
                'worker_last_seen_at' => $workerLastSeen,
            ];
        } catch (\Throwable) {
            return ['failed' => 0, 'pending' => 0, 'processing' => 0, 'delayed' => 0, 'total' => 0, 'oldest_wait_seconds' => 0, 'worker_status' => 'unknown', 'worker_last_seen_at' => null];
        }
    }

    private function listarFalhasRecentes(int $limit): array
    {
        try {
            return OperationalEvent::with('tenant:id,nome')
                ->whereIn('type', ['job_failure', 'integration_failure'])
                ->latest()->limit($limit)->get()
                ->map(fn (OperationalEvent $evento) => [
                    'id' => $evento->id,
                    'tipo' => $evento->type,
                    'provider' => $evento->provider,
                    'tenant' => $evento->tenant?->nome,
                    'mensagem' => data_get($evento->metadata, 'message') ?? data_get($evento->metadata, 'evento'),
                    'metadata' => $evento->metadata,
                    'ocorrido_em' => $evento->created_at,
                ])->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
