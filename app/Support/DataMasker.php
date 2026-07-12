<?php

namespace App\Support;

class DataMasker
{
    private const SENSITIVE_KEYS = [
        'telefone', 'phone', 'email', 'mensagem', 'message', 'conteudo', 'content',
        'body', 'input', 'result', 'cliente', 'nome', 'push_name', 'resumo', 'preferencia',
    ];

    public static function context(array $context): array
    {
        return self::walk($context);
    }

    private static function walk(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(mb_strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = self::mask($value);
            } elseif (is_array($value)) {
                $data[$key] = self::walk($value);
            }
        }

        return $data;
    }

    private static function mask(mixed $value): string
    {
        if (! is_scalar($value) || $value === '') {
            return '[REDACTED]';
        }

        $text = (string) $value;
        $suffix = mb_strlen($text) >= 4 ? mb_substr($text, -4) : '';

        return '[REDACTED'.($suffix !== '' ? ":***{$suffix}" : '').']';
    }
}
