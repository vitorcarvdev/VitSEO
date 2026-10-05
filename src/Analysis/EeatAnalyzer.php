<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Http\FetchResult;
use VitSeo\Support\HtmlParser;
use VitSeo\Support\JsonLd;
use VitSeo\Support\Text;
use VitSeo\Support\UrlHelper;

final class EeatAnalyzer
{
    /** @var array<string, array{label: string, slugs: list<string>, anchors: list<string>}> */
    private const PAGES = [
        'about' => [
            'label' => 'Página Sobre',
            'slugs' => ['sobre', 'quem-somos', 'quemsomos', 'about', 'about-us', 'a-empresa', 'institucional', 'nossa-historia', 'sobre-nos'],
            'anchors' => ['sobre', 'sobre nos', 'quem somos', 'about', 'about us', 'a empresa', 'nossa historia', 'institucional'],
        ],
        'contact' => [
            'label' => 'Página de contato',
            'slugs' => ['contato', 'contact', 'fale-conosco', 'faleconosco', 'contacts'],
            'anchors' => ['contato', 'fale conosco', 'contact', 'contact us'],
        ],
        'privacy' => [
            'label' => 'Política de privacidade',
            'slugs' => ['privacidade', 'politica-de-privacidade', 'privacy', 'privacy-policy', 'politica-privacidade'],
            'anchors' => ['politica de privacidade', 'privacy policy', 'privacidade'],
        ],
        'terms' => [
            'label' => 'Termos',
            'slugs' => ['termos', 'termos-de-uso', 'terms', 'terms-of-use', 'termos-e-condicoes'],
            'anchors' => ['termos', 'termos de uso', 'terms', 'terms of use'],
        ],
    ];

    /**
     * @return array{findings: list<Finding>, data: array<string, mixed>}
     */
    public function analyze(HtmlParser $page, FetchResult $fetch): array
    {
        $anchors = $page->anchors();
        $pages = [];
        foreach (self::PAGES as $key => $config) {
            $pages[$key] = $this->matchPage($anchors, $config['label'], $config['slugs'], $config['anchors']);
        }

        $schema = JsonLd::parse($page->jsonLdRaw());
        $orgTypes = array_values(array_filter($schema['types'], fn (string $type): bool => $this->isIdentityType($type)));
        $author = $this->author($page, $schema['types']);
        $phones = $this->phones($anchors, $schema['nodes']);
        $emails = $this->emails($anchors, $page->contentText(), $schema['nodes']);
        $addresses = $this->addresses($page, $schema['nodes']);
        $cnpj = $this->cnpj($page->contentText());
        $whatsapp = $this->whatsapp($anchors);
        $testimonials = $this->signal($page, $anchors, ['depoimentos', 'testemunhos', 'testimonials'], ['depoimentos', 'testemunhos', 'testimonials'], ['Review', 'AggregateRating']);
        $portfolio = $this->signal($page, $anchors, ['portfolio', 'portfolios', 'cases', 'trabalhos'], ['portfolio', 'cases', 'nossos cases', 'trabalhos realizados'], []);
        $external = $this->external($anchors, $fetch->finalUrl);

        $findings = [];
        if (!$cnpj['detected']) {
            $findings[] = new Finding(
                'CNPJ não detectado',
                'informativo',
                'informativo',
                'Nenhum CNPJ no formato 00.000.000/0000-00 foi encontrado no texto visível.',
                'A ausência de CNPJ publicado não é tratada como requisito de ranking.',
                null,
                'eeat'
            );
        }

        $detected = 0;
        foreach ([$pages['about'], $pages['contact'], $pages['privacy'], $pages['terms'], $author, $testimonials, $portfolio] as $item) {
            if (($item['status'] ?? '') === 'detectado') {
                $detected++;
            }
        }
        if ($phones !== []) {
            $detected++;
        }
        if ($emails !== []) {
            $detected++;
        }

        $aboutLabel = match ($pages['about']['status']) {
            'detectado' => 'Sobre encontrada',
            'nao_verificavel' => 'Sobre não verificável',
            default => 'Sobre não encontrada',
        };

        return [
            'findings' => $findings,
            'data' => [
                'pages' => $pages,
                'author' => $author,
                'schemas' => $orgTypes,
                'phones' => $phones,
                'emails' => $emails,
                'addresses' => $addresses,
                'cnpj' => $cnpj,
                'testimonials' => $testimonials,
                'portfolio' => $portfolio,
                'whatsapp' => $whatsapp,
                'external' => $external,
                'summary' => $aboutLabel . ' · ' . $detected . ' sinal(is)',
                'note' => 'E-E-A-T não é calculado como fórmula oficial. Abaixo estão sinais objetivos encontrados na página.',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $anchors
     * @param list<string> $slugs
     * @param list<string> $labels
     * @return array{label: string, status: string, url: ?string, evidence: string}
     */
    private function matchPage(array $anchors, string $label, array $slugs, array $labels): array
    {
        $weak = null;
        foreach ($anchors as $anchor) {
            $absolute = (string) ($anchor['absolute'] ?? '');
            $href = (string) $anchor['href'];
            if ($absolute === '' || $this->isChannel($absolute, $href)) {
                continue;
            }
            $segments = $this->segments($absolute);
            $slugHit = $this->slugHit($segments, $slugs);
            $anchorLabel = $this->norm((string) ($anchor['text'] !== '' ? $anchor['text'] : $anchor['aria']));
            $anchorHit = in_array($anchorLabel, $labels, true);
            $contradicts = $this->contradicts($anchorLabel);
            if ($slugHit && !$contradicts) {
                return [
                    'label' => $label,
                    'status' => 'detectado',
                    'url' => $absolute,
                    'evidence' => 'Link ' . $absolute . ($anchor['text'] !== '' ? ' · âncora: ' . $anchor['text'] : ''),
                ];
            }
            if ($anchorHit && !$slugHit && $weak === null && !$contradicts) {
                $weak = $anchor;
            }
        }

        if ($weak !== null) {
            return [
                'label' => $label,
                'status' => 'nao_verificavel',
                'url' => (string) ($weak['absolute'] ?? ''),
                'evidence' => 'A âncora combina, mas o endereço não confirma a página: ' . (string) ($weak['absolute'] ?? $weak['href']),
            ];
        }

        return ['label' => $label, 'status' => 'nao_detectado', 'url' => null, 'evidence' => 'Nenhum link com slug e contexto coerentes.'];
    }

    /** @param list<string> $types */
    private function author(HtmlParser $page, array $types): array
    {
        $meta = $page->metaByName('author');
        if ($meta) {
            return ['status' => 'detectado', 'evidence' => 'Meta author: ' . $meta];
        }
        foreach ($page->anchors() as $anchor) {
            if (preg_match('/(?:^|\s)author(?:\s|$)/', (string) $anchor['rel'])) {
                return ['status' => 'detectado', 'evidence' => 'Link rel="author": ' . (string) ($anchor['absolute'] ?? $anchor['href'])];
            }
        }
        if (in_array('Person', $types, true)) {
            return ['status' => 'detectado', 'evidence' => 'JSON-LD com @type Person.'];
        }

        return ['status' => 'nao_verificavel', 'evidence' => 'Autoria não identificada por meta author, rel="author" ou schema Person.'];
    }

    /**
     * @param list<array<string, mixed>> $anchors
     * @param list<mixed> $nodes
     * @return list<string>
     */
    private function phones(array $anchors, array $nodes): array
    {
        $values = [];
        foreach ($anchors as $anchor) {
            $href = (string) $anchor['href'];
            if (str_starts_with(strtolower($href), 'tel:')) {
                $values[] = rawurldecode(substr($href, 4));
            }
        }
        $this->walkSchema($nodes, static function (array $node) use (&$values): void {
            foreach (['telephone', 'phone'] as $key) {
                if (isset($node[$key]) && is_string($node[$key])) {
                    $values[] = $node[$key];
                }
            }
        });

        return array_values(array_unique(array_filter(array_map(static fn (string $v): string => Text::normalizeSpace($v), $values))));
    }

    /**
     * @param list<array<string, mixed>> $anchors
     * @param list<mixed> $nodes
     * @return list<string>
     */
    private function emails(array $anchors, string $text, array $nodes): array
    {
        $values = [];
        foreach ($anchors as $anchor) {
            $href = (string) $anchor['href'];
            if (str_starts_with(strtolower($href), 'mailto:')) {
                $values[] = rawurldecode(explode('?', substr($href, 7))[0]);
            }
        }
        if (preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text, $matches)) {
            foreach ($matches[0] as $email) {
                $values[] = $email;
            }
        }
        $this->walkSchema($nodes, static function (array $node) use (&$values): void {
            if (isset($node['email']) && is_string($node['email'])) {
                $values[] = $node['email'];
            }
        });

        $values = array_values(array_filter(array_unique(array_map(
            static fn (string $value): string => Text::lower(trim($value)),
            $values
        ))));

        return array_slice($values, 0, 8);
    }

    /**
     * @param list<mixed> $nodes
     * @return list<string>
     */
    private function addresses(HtmlParser $page, array $nodes): array
    {
        $values = [];
        foreach ($page->dom->getElementsByTagName('address') as $address) {
            $text = $page->nodeText($address);
            if (mb_strlen($text) > 8) {
                $values[] = Text::excerpt($text, 180);
            }
        }
        $this->walkSchema($nodes, static function (array $node) use (&$values): void {
            if (!isset($node['address'])) {
                return;
            }
            $address = $node['address'];
            if (is_string($address)) {
                $values[] = Text::normalizeSpace($address);
                return;
            }
            if (!is_array($address)) {
                return;
            }
            $parts = [];
            foreach (['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'] as $key) {
                if (isset($address[$key]) && is_string($address[$key])) {
                    $parts[] = $address[$key];
                }
            }
            if ($parts !== []) {
                $values[] = implode(', ', $parts);
            }
        });

        return array_values(array_unique($values));
    }

    /** @return array{detected: bool, values: list<string>} */
    private function cnpj(string $text): array
    {
        if (!preg_match_all('/\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}/', $text, $matches)) {
            return ['detected' => false, 'values' => []];
        }

        return ['detected' => true, 'values' => array_values(array_unique($matches[0]))];
    }

    /**
     * @param list<array<string, mixed>> $anchors
     * @return list<string>
     */
    private function whatsapp(array $anchors): array
    {
        $urls = [];
        foreach ($anchors as $anchor) {
            $absolute = (string) ($anchor['absolute'] ?? $anchor['href']);
            if ($this->isWhatsapp($absolute)) {
                $urls[] = $absolute;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param list<array<string, mixed>> $anchors
     * @param list<string> $slugs
     * @param list<string> $labels
     * @param list<string> $types
     * @return array{status: string, evidence: string}
     */
    private function signal(HtmlParser $page, array $anchors, array $slugs, array $labels, array $types): array
    {
        $schema = JsonLd::parse($page->jsonLdRaw());
        foreach ($types as $type) {
            if (in_array($type, $schema['types'], true)) {
                return ['status' => 'detectado', 'evidence' => 'JSON-LD com @type ' . $type . '.'];
            }
        }
        foreach ($page->headings() as $heading) {
            if (in_array($this->norm($heading['text']), $labels, true)) {
                return ['status' => 'detectado', 'evidence' => 'Heading: ' . $heading['text']];
            }
        }
        $match = $this->matchPage($anchors, 'sinal', $slugs, $labels);
        if ($match['status'] === 'detectado') {
            return ['status' => 'detectado', 'evidence' => $match['evidence']];
        }

        return ['status' => 'nao_detectado', 'evidence' => 'Nenhum heading, link coerente ou schema correspondente.'];
    }

    /**
     * @param list<array<string, mixed>> $anchors
     * @return list<string>
     */
    private function external(array $anchors, string $finalUrl): array
    {
        $urls = [];
        foreach ($anchors as $anchor) {
            $absolute = (string) ($anchor['absolute'] ?? '');
            if ($absolute === '' || !preg_match('#^https?://#i', $absolute)) {
                continue;
            }
            if (UrlHelper::isSameSite($finalUrl, $absolute) || $this->isChannel($absolute, (string) $anchor['href'])) {
                continue;
            }
            $urls[] = $absolute;
        }

        return array_slice(array_values(array_unique($urls)), 0, 8);
    }

    private function isChannel(string $url, string $href): bool
    {
        $lowerHref = strtolower($href);

        return $this->isWhatsapp($url)
            || str_starts_with($lowerHref, 'mailto:')
            || str_starts_with($lowerHref, 'tel:')
            || str_starts_with($lowerHref, 'javascript:');
    }

    private function isWhatsapp(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        $lower = strtolower($url);

        return $host === 'wa.me'
            || str_contains($host, 'whatsapp.')
            || str_contains($lower, 'api.whatsapp.com')
            || str_contains($lower, 'wa.me/');
    }

    /** @return list<string> */
    private function segments(string $url): array
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $path = strtolower(trim($path, '/'));
        if ($path === '') {
            return [];
        }
        $parts = explode('/', $path);
        $last = $parts[array_key_last($parts)];
        $parts[array_key_last($parts)] = (string) preg_replace('/\.(html|php|htm)$/', '', $last);

        return $parts;
    }

    /** @param list<string> $segments @param list<string> $slugs */
    private function slugHit(array $segments, array $slugs): bool
    {
        foreach ($segments as $segment) {
            if (in_array(Text::stripAccents($segment), array_map(fn (string $slug): string => Text::stripAccents($slug), $slugs), true)) {
                return true;
            }
        }

        return false;
    }

    private function norm(string $value): string
    {
        $value = Text::stripAccents($value);
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;

        return Text::normalizeSpace($value);
    }

    private function contradicts(string $label): bool
    {
        return (bool) preg_match('/\b(whatsapp|instagram|facebook|linkedin|youtube|blog|loja|carrinho|login)\b/', $label);
    }

    private function isIdentityType(string $type): bool
    {
        $value = strtolower($type);

        return $value === 'person'
            || str_ends_with($value, 'organization')
            || str_ends_with($value, 'business')
            || in_array($value, ['localbusiness', 'corporation', 'store', 'restaurant'], true);
    }

    /** @param list<mixed> $nodes */
    private function walkSchema(array $nodes, callable $callback, int $depth = 0): void
    {
        if ($depth > 8) {
            return;
        }
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }
            if ($this->isAssoc($node)) {
                $callback($node);
            }
            $this->walkSchema(array_values($node), $callback, $depth + 1);
        }
    }

    /** @param array<mixed> $value */
    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
