<?php

namespace App\Support;

/**
 * Ajusta o texto gerado pela IA ao que o WhatsApp exibe e detecta respostas
 * degeneradas (o modelo às vezes emite espaços invisíveis no lugar do texto).
 */
class WhatsappTexto
{
    /** Espaço não separável e caracteres de largura zero. */
    private const INVISIVEIS = '/[\x{00A0}\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u';

    /** A partir de quantos invisíveis a resposta é considerada lixo do modelo. */
    private const LIMITE_INVISIVEIS = 3;

    public static function pareceDegenerado(string $texto): bool
    {
        return preg_match_all(self::INVISIVEIS, $texto) >= self::LIMITE_INVISIVEIS;
    }

    public static function normalizar(string $texto): string
    {
        $texto = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $texto);
        $texto = preg_replace('/\x{00A0}/u', ' ', $texto);
        $texto = str_replace("\r\n", "\n", $texto);

        // Markdown → formatação do WhatsApp (*negrito*, _itálico_); títulos viram texto.
        $texto = preg_replace('/\*\*(.+?)\*\*/su', '*$1*', $texto);
        $texto = preg_replace('/__(.+?)__/su', '_$1_', $texto);
        $texto = preg_replace('/^#{1,6}\s+/mu', '', $texto);

        $texto = preg_replace('/[ \t]+$/mu', '', $texto);
        $texto = preg_replace('/\n{3,}/u', "\n\n", $texto);

        return trim($texto);
    }
}
