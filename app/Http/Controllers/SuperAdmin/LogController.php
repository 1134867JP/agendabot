<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Mensagem;
use App\Models\Tenant;
use App\Support\DataMasker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    private const MAX_LINES = 2000;

    private const MAX_ENTRIES = 150;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Logs', [
            'tenants' => Tenant::where('ativo', true)->orderBy('nome')->get(['id', 'nome', 'slug']),
        ]);
    }

    public function json(Request $request): JsonResponse
    {
        $nivel = strtoupper((string) $request->query('nivel', 'all'));
        $canal = (string) $request->query('canal', 'laravel');
        $tenantId = $request->integer('tenant_id') ?: null;
        $tenant = $tenantId ? Tenant::find($tenantId) : null;
        $hoje = now()->format('Y-m-d');

        $path = match ($canal) {
            'jobs' => storage_path("logs/jobs-{$hoje}.log"),
            'db' => storage_path("logs/db-{$hoje}.log"),
            default => $this->arquivoSistema($hoje),
        };

        if (! file_exists($path)) {
            return response()->json(['entries' => [], 'size' => 0, 'arquivo' => basename($path)]);
        }

        $entries = [];
        foreach ($this->lerUltimasLinhas($path, self::MAX_LINES) as $line) {
            if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] [^.]+\.(ERROR|WARNING|INFO|DEBUG): (.+)$/s', rtrim($line), $m)) {
                continue;
            }

            if ($nivel !== 'ALL' && $m[2] !== $nivel) {
                continue;
            }

            [$message, $context] = $this->separarContexto($m[3]);

            if ($tenant && ! $this->pertenceAoTenant($line, $context, $tenant)) {
                continue;
            }

            $entries[] = [
                'at' => $m[1],
                'level' => $m[2],
                'message' => substr($message, 0, 800),
                'context' => $context ? DataMasker::context($context) : null,
            ];

            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }
        }

        return response()->json([
            'entries' => $entries,
            'size' => filesize($path),
            'arquivo' => basename($path),
        ]);
    }

    public function conversas(Request $request): Response|JsonResponse
    {
        if (! $request->wantsJson()) {
            return Inertia::render('SuperAdmin/LogsConversas');
        }

        $query = Mensagem::with('conversa.tenant')->latest('enviada_em')->limit(200);
        if ($request->filled('tenant_id')) {
            $query->whereHas('conversa', fn ($q) => $q->where('tenant_id', $request->integer('tenant_id')));
        }
        if ($request->filled('telefone')) {
            $telefone = (string) $request->query('telefone');
            $query->whereHas('conversa', fn ($q) => $q->where('telefone_cliente', 'like', "%{$telefone}%"));
        }

        return response()->json([
            'mensagens' => $query->get()->map(fn ($m) => [
                'id' => $m->id,
                'tenant' => $m->conversa?->tenant?->nome,
                'telefone' => $this->mascararTelefone($m->conversa?->telefone_cliente),
                'remetente' => $m->remetente,
                'conteudo' => '[conteúdo oculto no painel operacional]',
                'enviada_em' => $m->enviada_em?->format('d/m H:i:s'),
            ]),
            'tenants' => Tenant::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    private function arquivoSistema(string $hoje): string
    {
        $daily = storage_path("logs/laravel-{$hoje}.log");

        return file_exists($daily) ? $daily : storage_path('logs/laravel.log');
    }

    private function mascararTelefone(?string $telefone): ?string
    {
        if (! $telefone) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $telefone);

        return str_repeat('*', max(0, strlen($digitos) - 4)).substr($digitos, -4);
    }

    private function separarContexto(string $raw): array
    {
        if (preg_match('/^(.+?)(\s*\{.+\}|\s*\[.+\])$/s', $raw, $parts)) {
            $decoded = json_decode(trim($parts[2]), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return [trim($parts[1]), $decoded];
            }
        }

        return [$raw, null];
    }

    private function pertenceAoTenant(string $line, ?array $context, Tenant $tenant): bool
    {
        $contextTenant = data_get($context, 'tenant_id')
            ?? data_get($context, 'tenant.id')
            ?? data_get($context, 'context.tenant_id');

        if ($contextTenant !== null) {
            return (int) $contextTenant === (int) $tenant->id;
        }

        $line = strtolower($line);

        return str_contains($line, strtolower($tenant->slug))
            || str_contains($line, '"tenant_id":'.$tenant->id)
            || str_contains($line, 'tenant #'.$tenant->id);
    }

    private function lerUltimasLinhas(string $path, int $n): array
    {
        $fp = fopen($path, 'rb');
        $pos = filesize($path);
        $buffer = '';

        while ($pos > 0 && substr_count($buffer, "\n") < $n) {
            $read = min(4096, $pos);
            $pos -= $read;
            fseek($fp, $pos);
            $buffer = fread($fp, $read).$buffer;
        }
        fclose($fp);

        return array_slice(array_reverse(explode("\n", trim($buffer))), 0, $n);
    }
}
