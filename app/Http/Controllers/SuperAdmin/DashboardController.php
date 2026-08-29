<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\AiStatus;
use App\Support\ErroLogScanner;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => $this->stats(),
            'ia' => AiStatus::resumo(),
            'tenants' => Tenant::withCount('recursos')
                ->latest()
                ->paginate(25),
        ]);
    }

    private function stats(): array
    {
        $failedJobs = $this->contarFailedJobs();
        $erros24h = $this->contarErros24h();

        return [
            'total_tenants' => Tenant::count(),
            'tenants_ativos' => Tenant::where('ativo', true)->count(),
            'tenants_conectados' => Tenant::where('whatsapp_conectado', true)->count(),
            'failed_jobs' => $failedJobs,
            'erros_24h' => $erros24h,
            'tenants_sem_config' => Tenant::where('ativo', true)
                ->where('whatsapp_conectado', false)
                ->count(),
        ];
    }

    private function contarFailedJobs(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function contarErros24h(): int
    {
        return ErroLogScanner::contarUltimas24h();
    }
}
