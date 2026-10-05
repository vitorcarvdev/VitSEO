<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Http\FetchResult;
use VitSeo\Http\PageFetcher;
use VitSeo\Support\HtmlParser;
use VitSeo\Support\JsonLd;
use VitSeo\Support\RobotsParser;
use VitSeo\Support\Text;
use VitSeo\Support\UrlHelper;

final class GeoAnalyzer
{
    /** @var list<string> */
    private const KNOWN_AGENTS = [
        'GPTBot',
        'ChatGPT-User',
        'Google-Extended',
        'Googlebot',
        'Bingbot',
        'ClaudeBot',
        'anthropic-ai',
        'PerplexityBot',
        'CCBot',
        'Bytespider',
        'Amazonbot',
        'Applebot-Extended',
        'cohere-ai',
    ];

    /** @param array<string, mixed> $config */
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
        $origin = UrlHelper::origin($fetch->finalUrl) ?? '';
        $robots = $this->robots($origin);
        $llms = [
            'llms.txt' => $this->probe($origin . '/llms.txt', 'llms'),
            'llms-full.txt' => $this->probe($origin . '/llms-full.txt', 'llms'),
        ];
        $sitemaps = $this->sitemaps($origin, $robots['sitemaps']);
        $schema = JsonLd::parse($page->jsonLdRaw());
        $semantic = [
            'main' => $page->countTag('main') > 0,
            'article' => $page->countTag('article') > 0,
            'nav' => $page->countTag('nav') > 0,
            'header' => $page->countTag('header') > 0,
            'footer' => $page->countTag('footer') > 0,
            'lists' => $page->countTag('ul') + $page->countTag('ol'),
            'tables' => $page->countTag('table'),
            'headings' => count($page->headings()),
            'text_length' => mb_strlen($page->contentText()),
        ];

        $findings = [];
        $absent = [];
        foreach ($llms as $name => $item) {
            if ($item['state'] === 'nao_encontrado') {
                $absent[] = $name;
            }
        }
        if ($absent !== []) {
            $findings[] = new Finding(
                implode(' e ', $absent) . (count($absent) > 1 ? ' não encontrados' : ' não encontrado'),
                'informativo',
                'informativo',
                implode('; ', array_map(static fn (string $name): string => $name . ' em ' . $llms[$name]['url'] . ' (' . $llms[$name]['label'] . ')', $absent)),
                'A ausência desse arquivo, isoladamente, não comprova perda de visibilidade em mecanismos de busca ou sistemas de IA.',
                null,
                'geo'
            );
        }

        foreach ($robots['known'] as $rule) {
            $findings[] = new Finding(
                'Regra explícita para ' . $rule['agent'],
                'informativo',
                'informativo',
                'User-agent: ' . $rule['agent'] . '. Disallow: ' . ($rule['disallow'] !== [] ? implode(', ', $rule['disallow']) : '(nenhum)') . '.',
                'O robots.txt declara uma regra para esse agente. Isso não comprova, sozinho, como um sistema de IA trata o site.',
                null,
                'geo'
            );
        }

        if (!$robots['found']) {
            $findings[] = new Finding(
                'robots.txt não encontrado',
                'informativo',
                'informativo',
                $robots['url'] . ' · ' . $robots['label'],
                'A ausência de robots.txt não é, por si só, um erro. Sem o arquivo, não há regras explícitas para mostrar.',
                null,
                'geo'
            );
        }

        if ($sitemaps === [] || !array_filter($sitemaps, static fn (array $item): bool => $item['found'])) {
            $findings[] = new Finding(
                'Sitemap não localizado nos caminhos consultados',
                'informativo',
                'informativo',
                'Caminhos consultados: ' . implode(', ', array_map(static fn (array $item): string => $item['url'], $sitemaps)),
                'A ausência nesta consulta não comprova que o site não tenha sitemap em outro endereço.',
                null,
                'geo'
            );
        }

        $summary = 'robots.txt ' . ($robots['found'] ? 'encontrado' : 'não encontrado')
            . ' · JSON-LD ' . count($schema['types'])
            . ' · llms.txt ' . $llms['llms.txt']['label'];

        return [
            'findings' => $findings,
            'data' => [
                'robots' => $robots,
                'sitemaps' => $sitemaps,
                'llms' => $llms,
                'semantic' => $semantic,
                'schema_types' => $schema['types'],
                'summary' => $summary,
                'note' => 'GEO, aqui, é a leitura de sinais objetivos no HTML e em arquivos públicos. Não mede visibilidade em ChatGPT, Gemini ou outro sistema.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function robots(string $origin): array
    {
        $url = $origin . '/robots.txt';
        $result = $this->fetcher->fetch($url, [
            'timeout' => (int) ($this->config['aux_timeout'] ?? 8),
            'max_bytes' => (int) ($this->config['aux_max_bytes'] ?? 400000),
        ]);
        $base = [
            'url' => $url,
            'status' => $result->status,
            'found' => false,
            'excerpt' => '',
            'sitemaps' => [],
            'agents' => [],
            'known' => [],
            'disallow' => [],
            'label' => 'Não encontrado',
            'note' => '',
        ];
        if (!$result->ok) {
            $base['label'] = 'Inconclusivo';
            $base['note'] = $result->message;

            return $base;
        }
        if ($result->status !== 200 || $this->looksLikeHtml($result)) {
            $base['label'] = 'Não encontrado';
            $base['note'] = $result->status === 200 ? 'A URL respondeu HTML, não um robots.txt típico.' : 'HTTP ' . $result->status . '.';

            return $base;
        }

        $parsed = RobotsParser::parse($result->body);
        $disallow = [];
        foreach ($parsed['groups'] as $group) {
            foreach ($group['disallow'] as $rule) {
                if ($rule !== '' && count($disallow) < 30) {
                    $disallow[] = $rule;
                }
            }
        }
        $known = [];
        foreach ($parsed['groups'] as $group) {
            foreach ($group['agents'] as $agent) {
                foreach (self::KNOWN_AGENTS as $candidate) {
                    if (strcasecmp($agent, $candidate) === 0) {
                        $known[] = ['agent' => $agent, 'disallow' => $group['disallow'], 'allow' => $group['allow']];
                    }
                }
            }
        }

        $base['found'] = true;
        $base['label'] = 'Encontrado';
        $base['excerpt'] = Text::excerpt($result->body, 2500);
        $base['sitemaps'] = $parsed['sitemaps'];
        $base['agents'] = $parsed['agents'];
        $base['known'] = $known;
        $base['disallow'] = array_values(array_unique($disallow));
        $base['note'] = 'Disallow não foi interpretado como erro.';

        return $base;
    }

    /**
     * @param list<string> $declared
     * @return list<array<string, mixed>>
     */
    private function sitemaps(string $origin, array $declared): array
    {
        $urls = $declared !== [] ? array_slice($declared, 0, 2) : [
            $origin . '/sitemap.xml',
            $origin . '/sitemap_index.xml',
        ];
        $items = [];
        foreach ($urls as $url) {
            $resolved = UrlHelper::resolve($origin . '/', $url) ?? $url;
            $result = $this->fetcher->fetch($resolved, [
                'timeout' => (int) ($this->config['aux_timeout'] ?? 8),
                'max_bytes' => (int) ($this->config['sitemap_max_bytes'] ?? 500000),
            ]);
            $kind = 'não identificado';
            $found = false;
            $count = 0;
            $sample = [];
            $note = '';
            if (!$result->ok) {
                $note = $result->message;
            } elseif ($result->status === 200 && !$this->looksLikeHtml($result)) {
                $body = $result->body;
                if (stripos($body, '<sitemapindex') !== false) {
                    $kind = 'índice de sitemap';
                    $found = true;
                } elseif (stripos($body, '<urlset') !== false) {
                    $kind = 'sitemap';
                    $found = true;
                }
                if ($found && preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $body, $matches)) {
                    $count = count($matches[1]);
                    $sample = array_slice(array_map('trim', $matches[1]), 0, 5);
                }
                if (!$found) {
                    $note = 'A resposta não parece um sitemap XML.';
                }
                if ($result->truncated) {
                    $note = trim($note . ' Arquivo truncado pelo limite desta versão.');
                }
            } else {
                $note = 'HTTP ' . $result->status . '.';
            }
            $items[] = [
                'url' => $resolved,
                'status' => $result->status,
                'kind' => $kind,
                'found' => $found,
                'loc_count' => $count,
                'sample' => $sample,
                'note' => $note,
                'declared' => $declared !== [],
            ];
        }

        return $items;
    }

    /** @return array{url: string, status: int, state: string, label: string, note: string} */
    private function probe(string $url, string $kind): array
    {
        $result = $this->fetcher->fetch($url, [
            'timeout' => (int) ($this->config['aux_timeout'] ?? 8),
            'max_bytes' => 100000,
        ]);
        if (!$result->ok) {
            return ['url' => $url, 'status' => 0, 'state' => 'inconclusivo', 'label' => 'Inconclusivo', 'note' => $result->message];
        }
        if ($result->status === 200 && trim($result->body) !== '' && !$this->looksLikeHtml($result)) {
            return ['url' => $url, 'status' => 200, 'state' => 'encontrado', 'label' => 'Encontrado', 'note' => $kind === 'llms' ? 'Arquivo público encontrado. A presença não comprova tratamento por sistemas de IA.' : ''];
        }

        $note = $result->status === 200 ? 'A URL respondeu HTML ou corpo vazio.' : 'HTTP ' . $result->status . '.';

        return ['url' => $url, 'status' => $result->status, 'state' => 'nao_encontrado', 'label' => 'Não encontrado', 'note' => $note];
    }

    private function looksLikeHtml(FetchResult $result): bool
    {
        $type = strtolower($result->header('content-type'));
        if (str_contains($type, 'html')) {
            return true;
        }
        $start = ltrim(substr($result->body, 0, 400));

        return (bool) preg_match('/^<!doctype|^<html/i', $start);
    }
}
