<?php

namespace App\Support;

final class Csv
{
    /**
     * Neutraliza células interpretadas como fórmulas por Excel, LibreOffice e Sheets.
     *
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    public static function row(array $values): array
    {
        return array_map([self::class, 'cell'], $values);
    }

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = str_replace("\0", '', $value);

        return preg_match('/^\\s*[=+\\-@]/u', $value) === 1
            ? "'".$value
            : $value;
    }
}
