<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Http\FetchResult;
use VitSeo\Http\PageFetcher;
use VitSeo\Support\HtmlParser;
use VitSeo\Support\UrlHelper;

final class SubpageAnalyzer
{
    /**
     * A V1 só lista links internos da página atual e consulta um limite curto.
     * Não há crawler recursivo.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly array $config,
    ) {
    }

    /**
     * @return array{findings: list<Finding>, data: array<string, mixed>}
     */
    public function analyze(HtmlParser $page, FetchResult $fetch): array
    {
        $grouped = [];
        foreach ($page->anchors() as $anchor) {
            $absolute = (string) ($anchor['absolute'] ?? '');
            $href = strtolower((string) $anchor['href']);
            if ($absolute === '' || !preg_match('#^https?://#i', $absolute)) {
                continue;
            }
            if (str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'javascript:')) {
                continue;
            }
            if (!UrlHelper::isSameSite($fetch->finalUrl, $absolute)) {
                continue;
            }
            $key = UrlHelper::normalizeKey($absolute);
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['url' => $key, 'anchor' => '', 'count' => 0];
            }
            $grouped[$key]['count']++;
            if ($grouped[$key]['anchor'] === '' && trim((string) $anchor['text']) !== '') {
                $grouped[$key]['anchor'] = trim((string) $anchor['text']);
            }
        }

        uasort($grouped, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $limit = (int) ($this->config['subpage_limit'] ?? 40);
        $checkLimit = (int) ($this->config['subpage_checks'] ?? 12);
        $items = array_slice(array_values($grouped), 0, $limit);
        $current = UrlHelper::normalizeKey($fetch->finalUrl);
        $toCheck = [];
        foreach ($items as $index => $item) {
            if ($item['url'] === $current || count($toCheck) >= $checkLimit) {
                continue;
            }
            $toCheck[$index] = $item['url'];
        }

        $checked = [];
        if ($toCheck !== []) {
            $results = $this->fetcher->fetchMany(array_values($toCheck), [
                'method' => 'HEAD',
                'timeout' => (int) ($this->config['subpage_timeout'] ?? 5),
                'max_bytes' => 2048,
            ]);
            $positions = array_keys($toCheck);
            foreach ($results as $resultIndex => $result) {
                $checked[$positions[$resultIndex]] = $result;
            }
        }

        foreach ($items as $index => &$item) {
            $item['checked'] = isset($checked[$index]);
            if (!$item['checked']) {
                $item['status'] = $item['url'] === $current ? 'página atual' : 'não verificado';
                continue;
            }
            $result = $checked[$index];
            $item['status'] = $result->ok ? (string) $result->status : $result->message;
        }
        unset($item);

        return [
            'findings' => [],
            'data' => [
                'items' => $items,
                'discovered' => count($grouped),
                'checked' => count($checked),
                'limit_note' => 'Lista limitada a ' . $limit . ' URLs e verificação leve de até ' . $checkLimit . '. Não há varredura das páginas seguintes.',
            ],
        ];
    }
}
