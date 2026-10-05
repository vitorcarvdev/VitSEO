<?php

declare(strict_types=1);

namespace VitSeo\Support;

final class Text
{
    public static function normalizeSpace(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    public static function stripAccents(string $value): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];

        return strtr(self::lower($value), $map);
    }

    /** @return list<string> */
    public static function tokens(string $value): array
    {
        if (!preg_match_all('/[\p{L}\p{N}]+/u', self::lower($value), $matches)) {
            return [];
        }

        return $matches[0];
    }

    public static function excerpt(string $value, int $length): string
    {
        $value = self::normalizeSpace($value);
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $length - 1)) . '…';
    }
}
