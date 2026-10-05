<?php

declare(strict_types=1);

namespace VitSeo\Support;

final class Format
{
    public static function score(float $score): string
    {
        return number_format($score, 1, ',', '');
    }

    public static function seconds(float $seconds): string
    {
        if ($seconds < 0.01) {
            return (string) max(0, (int) round($seconds * 1000)) . ' ms';
        }
        if ($seconds < 10) {
            return number_format($seconds, 2, ',', '') . ' s';
        }

        return number_format($seconds, 1, ',', '') . ' s';
    }

    public static function bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', '') . ' KB';
        }

        return number_format($bytes / (1024 * 1024), 2, ',', '') . ' MB';
    }

    public static function percent(float $value): string
    {
        return number_format($value, 1, ',', '') . '%';
    }

    public static function integer(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
