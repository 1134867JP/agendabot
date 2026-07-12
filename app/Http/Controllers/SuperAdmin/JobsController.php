<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Support\FailedJobsFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JobsController extends Controller
{
    public function index(): Response
    {
        $failed = $this->listarFailed(50);
        $queue = $this->statsQueue();

        return Inertia::render('SuperAdmin/Jobs', [
            'failed' => $failed,
            'queue' => $queue,
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

    // ── helpers ───────────────────────────────────────────────────────────────

    private function listarFailed(int $limit): array
    {
        try {
            return DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit($limit)
                ->get()
                ->map(fn ($job) => FailedJobsFormatter::formatar($job))
                ->toArray();
        } catch (\Throwable) {
            return []; // tabela pode não existir
        }
    }

    private function statsQueue(): array
    {
        try {
            return [
                'failed' => DB::table('failed_jobs')->count(),
                'pending' => DB::table('jobs')->count(),
            ];
        } catch (\Throwable) {
            return ['failed' => 0, 'pending' => 0];
        }
    }
}
