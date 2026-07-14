<?php

namespace App\Support;

class ErroLogScanner
{
    /** Conta quantas linhas de nível ERROR apareceram em storage/logs/laravel.log nas últimas 24h. */
    public static function contarUltimas24h(): int
    {
        $path = storage_path('logs/laravel.log');
        if (! file_exists($path)) {
            return 0;
        }

        try {
            $since = now()->subDay()->format('Y-m-d H:i:s');
            $handle = fopen($path, 'rb');
            $count = 0;

            // Ler apenas as últimas 500KB do arquivo, para não carregar logs enormes na memória
            $size = filesize($path);
            $offset = max(0, $size - 512000);
            fseek($handle, $offset);
            $content = fread($handle, $size);
            fclose($handle);

            foreach (explode("\n", $content) as $line) {
                if (str_contains($line, '].ERROR:') &&
                    preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m) &&
                    $m[1] >= $since) {
                    $count++;
                }
            }

            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }
}
