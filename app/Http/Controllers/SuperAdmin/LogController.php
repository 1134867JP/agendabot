<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    private const MAX_LINES  = 2000; // linhas varridas do final do arquivo
    private const MAX_ENTRIES = 150; // entradas retornadas

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Logs');
    }

    public function json(Request $request): JsonResponse
    {
        $nivel = strtoupper($request->query('nivel', 'all'));
        $path  = storage_path('logs/laravel.log');

        if (! file_exists($path)) {
            return response()->json(['entries' => [], 'size' => 0]);
        }

        $size  = filesize($path);
        $lines = $this->lerUltimasLinhas($path, self::MAX_LINES);
        $entries = [];

        foreach ($lines as $line) {
            // Formato Laravel: [2026-01-01 12:00:00] production.ERROR: mensagem {"context":...}
            if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(ERROR|WARNING|INFO|DEBUG): (.+)$/s', rtrim($line), $m)) {
                continue;
            }

            $lvl = $m[2];

            if ($nivel !== 'ALL' && $lvl !== $nivel) {
                continue;
            }

            // Separar mensagem do contexto JSON
            $raw     = $m[3];
            $context = null;
            $msg     = $raw;

            if (preg_match('/^(.+?)(\s*\{.+\}|\s*\[.+\])$/s', $raw, $parts)) {
                $msg     = trim($parts[1]);
                $decoded = json_decode(trim($parts[2]), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $context = $decoded;
                }
            }

            $entries[] = [
                'at'      => $m[1],
                'level'   => $lvl,
                'message' => substr($msg, 0, 800),
                'context' => $context,
            ];

            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }
        }

        return response()->json([
            'entries' => $entries,
            'size'    => $size,
            'arquivo' => basename($path),
        ]);
    }

    /** Lê as últimas $n linhas do arquivo de trás para frente sem carregar tudo na memória. */
    private function lerUltimasLinhas(string $path, int $n): array
    {
        $fp    = fopen($path, 'rb');
        $pos   = filesize($path);
        $buf   = '';
        $count = 0;
        $chunk = 4096;

        while ($pos > 0 && $count < $n) {
            $read  = min($chunk, $pos);
            $pos  -= $read;
            fseek($fp, $pos);
            $buf   = fread($fp, $read) . $buf;
            $count = substr_count($buf, "\n");
        }

        fclose($fp);

        $lines = array_reverse(explode("\n", trim($buf)));

        return array_slice($lines, 0, $n);
    }
}
