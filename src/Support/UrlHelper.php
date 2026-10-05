<?php

declare(strict_types=1);

namespace VitSeo\Support;

final class UrlHelper
{
    public static function resolve(string $base, string $relative): ?string
    {
        $relative = trim($relative);
        if ($relative === '' || str_starts_with(strtolower($relative), 'javascript:') || str_starts_with(strtolower($relative), 'data:')) {
            return null;
        }

        if (preg_match('#^(mailto|tel):#i', $relative)) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $relative)) {
            return $relative;
        }

        $parts = parse_url($base);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        if (str_starts_with($relative, '//')) {
            return $scheme . ':' . $relative;
        }

        $fragmentless = preg_replace('/#.*$/', '', $relative) ?? $relative;
        $query = '';
        $pathPart = $fragmentless;
        if (str_contains($fragmentless, '?')) {
            [$pathPart, $queryString] = explode('?', $fragmentless, 2);
            $query = '?' . $queryString;
        }

        if (str_starts_with($relative, '?')) {
            $path = $parts['path'] ?? '/';

            return $scheme . '://' . $host . $port . $path . $query;
        }

        if (str_starts_with($relative, '#')) {
            return self::withoutFragment($base);
        }

        $basePath = $parts['path'] ?? '/';
        if (str_starts_with($pathPart, '/')) {
            $path = $pathPart;
        } else {
            $dir = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';
            $path = $dir . $pathPart;
        }

        return $scheme . '://' . $host . $port . self::normalizePath($path) . $query;
    }

    public static function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);
    }

    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    public static function isSameSite(string $a, string $b): bool
    {
        $left = self::host($a);
        $right = self::host($b);
        if ($left === null || $right === null) {
            return false;
        }

        return preg_replace('/^www\./', '', $left) === preg_replace('/^www\./', '', $right);
    }

    public static function sameDocument(string $a, string $b): bool
    {
        $left = parse_url($a);
        $right = parse_url($b);
        if (!is_array($left) || !is_array($right)) {
            return false;
        }

        $schemeA = strtolower((string) ($left['scheme'] ?? ''));
        $schemeB = strtolower((string) ($right['scheme'] ?? ''));
        $hostA = strtolower((string) ($left['host'] ?? ''));
        $hostB = strtolower((string) ($right['host'] ?? ''));
        if ($schemeA === '' || $schemeA !== $schemeB || $hostA === '' || $hostA !== $hostB) {
            return false;
        }

        $pathA = rtrim((string) ($left['path'] ?? '/'), '/');
        $pathB = rtrim((string) ($right['path'] ?? '/'), '/');

        return $pathA === $pathB && (string) ($left['query'] ?? '') === (string) ($right['query'] ?? '');
    }

    public static function withoutFragment(string $url): string
    {
        $cut = strpos($url, '#');

        return $cut === false ? $url : substr($url, 0, $cut);
    }

    public static function normalizeKey(string $url): string
    {
        $url = self::withoutFragment($url);
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return $url;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $path = (string) ($parts['path'] ?? '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';

        return $scheme . '://' . $host . $path . $query;
    }

    private static function normalizePath(string $path): string
    {
        $segments = explode('/', $path);
        $out = [];
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $segment;
        }

        return '/' . implode('/', $out);
    }
}
