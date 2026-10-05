<?php

declare(strict_types=1);

namespace VitSeo\Support;

final class RobotsParser
{
    /**
     * Leitura simples para exibição. Não avalia permissões de rastreamento.
     *
     * @return array{sitemaps: list<string>, agents: list<string>, groups: list<array{agents: list<string>, disallow: list<string>, allow: list<string>}>}
     */
    public static function parse(string $body): array
    {
        $sitemaps = [];
        $groups = [];
        $current = ['agents' => [], 'disallow' => [], 'allow' => []];
        $lines = preg_split('/\r\n|\n|\r/', $body) ?: [];

        $flush = static function () use (&$groups, &$current): void {
            if ($current['agents'] === [] && $current['disallow'] === [] && $current['allow'] === []) {
                return;
            }
            $groups[] = $current;
            $current = ['agents' => [], 'disallow' => [], 'allow' => []];
        };

        foreach ($lines as $line) {
            $line = preg_replace('/\s*#.*$/', '', $line) ?? $line;
            if (!str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $key = strtolower(trim($key));
            $value = trim($value);
            if ($key === 'sitemap') {
                if ($value !== '') {
                    $sitemaps[] = $value;
                }
                continue;
            }
            if ($key === 'user-agent') {
                if ($current['disallow'] !== [] || $current['allow'] !== []) {
                    $flush();
                }
                if ($value !== '') {
                    $current['agents'][] = $value;
                }
                continue;
            }
            if ($key === 'disallow') {
                $current['disallow'][] = $value;
            } elseif ($key === 'allow') {
                $current['allow'][] = $value;
            }
        }
        $flush();

        $agents = [];
        foreach ($groups as $group) {
            foreach ($group['agents'] as $agent) {
                $agents[] = $agent;
            }
        }

        return [
            'sitemaps' => array_values(array_unique($sitemaps)),
            'agents' => array_values(array_unique($agents)),
            'groups' => $groups,
        ];
    }
}
