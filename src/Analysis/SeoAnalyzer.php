<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Http\FetchResult;
use VitSeo\Support\HtmlParser;
use VitSeo\Support\JsonLd;
use VitSeo\Support\Text;
use VitSeo\Support\UrlHelper;

final class SeoAnalyzer
{
    /**
     * @return array{findings: list<Finding>, data: array<string, mixed>}
     */
    public function analyze(HtmlParser $page, FetchResult $fetch): array
    {
        $findings = [];
        $title = $page->title();
        $description = $page->metaByName('description') ?? '';
        $headings = $page->headings();
        $h1 = array_values(array_filter($headings, static fn (array $heading): bool => $heading['level'] === 1));
        $canonical = $page->canonical();
        $robotsMeta = $this->robotsMeta($page);
        $robotsHeader = $fetch->header('x-robots-tag');
        $noindex = $this->containsDirective($robotsMeta . ' ' . $robotsHeader, 'noindex');
        $lang = $page->htmlLang();
        $schema = JsonLd::parse($page->jsonLdRaw());
        $links = $this->links($page, $fetch->finalUrl);

        if ($title === '') {
            $findings[] = new Finding(
                'Title ausente',
                'importante',
                'comprovado',
                'Não há elemento <title> com texto.',
                'O título da página não foi encontrado no HTML coletado.',
                'Defina um title que descreva esta página.',
                'seo'
            );
        } elseif (mb_strlen($title) < 15 || mb_strlen($title) > 70) {
            $findings[] = new Finding(
                'Comprimento do title fora da faixa usual',
                'melhoria',
                'recomendacao',
                'Title com ' . mb_strlen($title) . ' caracteres: ' . Text::excerpt($title, 180),
                'Faixas comuns de referência ficam perto de 50–60 caracteres, mas isso não é uma regra absoluta.',
                'Revise se o title descreve a página com clareza no tamanho em que costuma aparecer.',
                'seo'
            );
        }

        if ($description === '') {
            $findings[] = new Finding(
                'Meta description ausente',
                'melhoria',
                'recomendacao',
                'Não há meta name="description".',
                'A ausência foi observada no HTML. Isoladamente, isso não é um erro técnico de SEO. O mecanismo de busca pode gerar o snippet automaticamente. Uma descrição específica pode melhorar a apresentação do resultado.',
                'Escreva uma descrição específica desta página, se quiser sugerir o texto do resultado.',
                'seo'
            );
        } else {
            $generic = $this->genericDescription($description, $title);
            if ($generic !== null) {
                $findings[] = new Finding(
                    'Meta description possivelmente genérica',
                    'melhoria',
                    'recomendacao',
                    $generic,
                    'O texto encontrado é curto, igual ao title ou muito genérico. Isso é um indício, não uma infração.',
                    'Revise se a description descreve de fato esta página.',
                    'seo'
                );
            } elseif (mb_strlen($description) < 40 || mb_strlen($description) > 200) {
                $findings[] = new Finding(
                    'Comprimento da meta description fora da faixa usual',
                    'melhoria',
                    'recomendacao',
                    'Description com ' . mb_strlen($description) . ' caracteres.',
                    'Uma referência comum fica entre 120 e 160 caracteres, sem ser um limite oficial.',
                    null,
                    'seo'
                );
            }
        }

        if (count($h1) === 0) {
            $findings[] = new Finding(
                'H1 não encontrado',
                'melhoria',
                'recomendacao',
                'Nenhum elemento H1 foi encontrado no HTML.',
                'A ausência foi observada no HTML. Isoladamente, isso não comprova um problema de SEO. Um H1 claro pode melhorar a estrutura semântica e a compreensão da página.',
                'Use um H1 que identifique o assunto principal da página.',
                'seo'
            );
        } elseif (count($h1) > 1) {
            $texts = array_map(static fn (array $item): string => $item['text'] !== '' ? $item['text'] : '(vazio)', $h1);
            $findings[] = new Finding(
                'Mais de um H1',
                'melhoria',
                'comprovado',
                count($h1) . ' elementos H1: ' . Text::excerpt(implode(' | ', $texts), 300),
                'A página possui mais de um H1. A detecção registra o fato; não indica, por si só, punição ou perda de visibilidade.',
                'Revise se a página precisa de mais de um H1.',
                'seo'
            );
        }

        foreach ($h1 as $item) {
            if ($item['text'] === '') {
                $findings[] = new Finding(
                    'H1 sem texto',
                    'melhoria',
                    'comprovado',
                    'Há um H1 sem texto visível.',
                    'O elemento existe, mas não tem texto coletável.',
                    null,
                    'seo'
                );
                break;
            }
        }

        $skip = $this->headingSkip($headings);
        if ($skip !== null) {
            $findings[] = new Finding(
                'Salto na hierarquia de headings',
                'melhoria',
                'recomendacao',
                $skip,
                'A ordem dos headings pula um nível. Isso pode dificultar a leitura da estrutura, sem ser um erro absoluto.',
                null,
                'seo'
            );
        }

        $canonicalData = [
            'present' => $canonical !== null,
            'url' => $canonical,
            'self' => null,
            'note' => '',
        ];
        if ($canonical === null) {
            $findings[] = new Finding(
                'Canonical ausente',
                'melhoria',
                'recomendacao',
                'Nenhum link rel="canonical" foi encontrado.',
                'A ausência foi observada no HTML. Isoladamente, isso não é um erro. Uma página única, em URL estável e sem sinais de duplicação, pode funcionar sem canonical explícito.',
                'Declare um canonical se esta página tiver uma URL preferida.',
                'seo'
            );
        } else {
            $self = UrlHelper::sameDocument($fetch->finalUrl, $canonical);
            $canonicalData['self'] = $self;
            if ($self && UrlHelper::withoutFragment($fetch->finalUrl) !== UrlHelper::withoutFragment($canonical)) {
                $canonicalData['note'] = 'Tratado como a mesma URL; a diferença encontrada é a barra final ou a normalização do caminho.';
            }
            if (!$self) {
                $findings[] = new Finding(
                    'Canonical aponta para outra URL',
                    'informativo',
                    'comprovado',
                    'URL analisada: ' . $fetch->finalUrl . ' · Canonical: ' . $canonical,
                    'O canonical declarado é diferente da URL analisada. Isso pode ser intencional.',
                    'Confira se a URL preferida está correta.',
                    'seo'
                );
            }
        }

        if ($noindex) {
            $evidence = trim('Meta robots: ' . ($robotsMeta !== '' ? $robotsMeta : 'não declarada') . '. X-Robots-Tag: ' . ($robotsHeader !== '' ? $robotsHeader : 'não declarado') . '.');
            $findings[] = new Finding(
                'A página declara noindex',
                'importante',
                'comprovado',
                $evidence,
                'A página pede para não ser indexada. Mecanismos que respeitam essa diretiva tendem a não indexá-la. A diretiva pode ser intencional.',
                'Remova o noindex apenas se a página deva ser indexada.',
                'seo'
            );
        }

        if ($lang === null) {
            $findings[] = new Finding(
                'Atributo lang ausente',
                'melhoria',
                'recomendacao',
                'O elemento html não possui lang.',
                'O idioma da página não está declarado no atributo lang.',
                'Declare o idioma principal, por exemplo lang="pt-BR".',
                'seo'
            );
        }

        if ($schema['invalid'] !== []) {
            $findings[] = new Finding(
                'JSON-LD inválido',
                'importante',
                'comprovado',
                implode(' ', $schema['invalid']),
                'Há script application/ld+json que não pôde ser lido como JSON.',
                'Corrija a sintaxe do bloco JSON-LD.',
                'seo'
            );
        } elseif ($schema['valid_blocks'] === 0) {
            $findings[] = new Finding(
                'Nenhum JSON-LD detectado',
                'informativo',
                'informativo',
                'Nenhum script application/ld+json foi encontrado.',
                'A ausência de JSON-LD não comprova perda de ranking ou de visibilidade.',
                null,
                'seo'
            );
        }

        $openGraph = [
            'og:title' => $page->metaProperty('og:title'),
            'og:description' => $page->metaProperty('og:description'),
            'og:image' => $page->metaProperty('og:image'),
            'og:url' => $page->metaProperty('og:url'),
            'og:type' => $page->metaProperty('og:type'),
        ];
        $missingOg = array_keys(array_filter($openGraph, static fn (?string $value): bool => $value === null));
        if (count($missingOg) >= 3) {
            $findings[] = new Finding(
                'Open Graph incompleto',
                'melhoria',
                'recomendacao',
                'Não declarados: ' . implode(', ', $missingOg) . '.',
                'Faltam propriedades Open Graph usuais. Isso afeta a pré-visualização em algumas redes, não o ranking por si só.',
                null,
                'seo'
            );
        } elseif ($missingOg !== []) {
            $findings[] = new Finding(
                'Open Graph com propriedades ausentes',
                'informativo',
                'informativo',
                'Não declarados: ' . implode(', ', $missingOg) . '.',
                'Parte das propriedades Open Graph não foi encontrada.',
                null,
                'seo'
            );
        }

        $twitter = [
            'twitter:card' => $page->metaByName('twitter:card'),
            'twitter:title' => $page->metaByName('twitter:title'),
            'twitter:description' => $page->metaByName('twitter:description'),
            'twitter:image' => $page->metaByName('twitter:image'),
        ];
        foreach (['twitter:site' => $page->metaByName('twitter:site'), 'twitter:creator' => $page->metaByName('twitter:creator')] as $name => $value) {
            if ($value !== null && $value !== '') {
                $twitter[$name] = $value;
            }
        }
        if (count(array_filter($twitter)) === 0) {
            $findings[] = new Finding(
                'Twitter Cards não declarados',
                'informativo',
                'informativo',
                'Nenhuma meta twitter:card, twitter:title, twitter:description ou twitter:image.',
                'A página não declara Twitter Cards. Isso não é um erro de indexação.',
                null,
                'seo'
            );
        }

        if ($links['empty'] > 0) {
            $findings[] = new Finding(
                'Links com âncora vazia',
                'melhoria',
                'comprovado',
                $links['empty'] . ' link(s) sem texto, aria-label, title ou alt de imagem. Exemplos: ' . implode(' · ', $links['empty_examples']),
                'A âncora não tem um nome identificável no HTML coletado.',
                'Inclua um texto ou um nome acessível no link.',
                'seo'
            );
        }

        if ($links['mixed_assets'] > 0) {
            $findings[] = new Finding(
                'Recursos HTTP em página HTTPS',
                'importante',
                'comprovado',
                $links['mixed_assets'] . ' recurso(s) http em script, CSS ou imagem. Exemplos: ' . implode(' · ', $links['mixed_asset_examples']),
                'A página HTTPS referencia recursos HTTP. Navegadores podem bloquear ou alertar esse tipo de recurso. O VitSEO não executou esses arquivos.',
                'Sirva esses recursos por HTTPS.',
                'seo'
            );
        }

        if ($links['mixed_links'] > 0) {
            $findings[] = new Finding(
                'Links HTTP em página HTTPS',
                'melhoria',
                'comprovado',
                $links['mixed_links'] . ' link(s) http. Exemplos: ' . implode(' · ', $links['mixed_link_examples']),
                'Há links que apontam para HTTP a partir de uma página HTTPS.',
                'Atualize os links para HTTPS quando o destino suportar.',
                'seo'
            );
        }

        $data = [
            'title' => ['text' => $title, 'length' => mb_strlen($title), 'present' => $title !== ''],
            'description' => ['text' => $description, 'length' => mb_strlen($description), 'present' => $description !== ''],
            'h1' => ['count' => count($h1), 'items' => array_map(static fn (array $item): string => $item['text'], $h1)],
            'headings' => $headings,
            'canonical' => $canonicalData,
            'robots' => ['meta' => $robotsMeta, 'header' => $robotsHeader, 'noindex' => $noindex],
            'lang' => $lang,
            'open_graph' => $this->openGraph($page, $openGraph),
            'twitter' => $twitter,
            'links' => $links,
            'schema' => $schema,
            'checks' => $this->checks($title, $description, $h1, $canonicalData, $noindex, $lang, $fetch->status, $schema, $headings),
        ];

        return ['findings' => $findings, 'data' => $data];
    }

    /** @param list<array{level: int, text: string}> $headings */
    private function headingSkip(array $headings): ?string
    {
        $previous = 0;
        foreach ($headings as $heading) {
            if ($previous > 0 && $heading['level'] > $previous + 1) {
                return 'Depois de H' . $previous . ' aparece H' . $heading['level'] . '.';
            }
            $previous = $heading['level'];
        }

        return null;
    }

    private function genericDescription(string $description, string $title): ?string
    {
        $plain = Text::stripAccents($description);
        $generic = ['bem vindo', 'bem-vindo', 'welcome', 'home', 'inicio', 'pagina inicial', 'descricao', 'description', 'saiba mais', 'clique aqui', 'lorem ipsum'];
        if (in_array($plain, $generic, true) || mb_strlen($description) < 25) {
            return 'Texto: ' . Text::excerpt($description, 180);
        }
        if ($title !== '' && Text::lower($description) === Text::lower($title)) {
            return 'A description repete o title: ' . Text::excerpt($description, 180);
        }

        return null;
    }

    private function robotsMeta(HtmlParser $page): string
    {
        $chunks = [];
        foreach (['robots', 'googlebot', 'bingbot'] as $name) {
            foreach ($page->metasByName($name) as $content) {
                if ($content !== '') {
                    $chunks[] = $name . '=' . $content;
                }
            }
        }

        return implode('; ', $chunks);
    }

    private function containsDirective(string $value, string $directive): bool
    {
        return (bool) preg_match('/(?:^|[\s,;=])' . preg_quote($directive, '/') . '(?:$|[\s,;=])/i', $value);
    }

    /** @return array<string, mixed> */
    private function links(HtmlParser $page, string $finalUrl): array
    {
        $internal = 0;
        $external = 0;
        $nofollow = 0;
        $empty = 0;
        $emptyExamples = [];
        $mixedLinks = 0;
        $mixedLinkExamples = [];
        $https = str_starts_with(strtolower($finalUrl), 'https://');

        foreach ($page->anchors() as $anchor) {
            $absolute = $anchor['absolute'];
            $href = (string) $anchor['href'];
            if ($absolute === null || !preg_match('#^https?://#i', (string) $absolute)) {
                if ($this->anchorIsEmpty($anchor) && !str_starts_with($href, '#')) {
                    $empty++;
                    if (count($emptyExamples) < 5) {
                        $emptyExamples[] = $href !== '' ? $href : '(href vazio)';
                    }
                }
                continue;
            }
            if (UrlHelper::isSameSite($finalUrl, (string) $absolute)) {
                $internal++;
            } else {
                $external++;
            }
            if (str_contains($anchor['rel'], 'nofollow')) {
                $nofollow++;
            }
            if ($this->anchorIsEmpty($anchor)) {
                $empty++;
                if (count($emptyExamples) < 5) {
                    $emptyExamples[] = (string) $absolute;
                }
            }
            if ($https && str_starts_with(strtolower((string) $absolute), 'http://')) {
                $mixedLinks++;
                if (count($mixedLinkExamples) < 5) {
                    $mixedLinkExamples[] = (string) $absolute;
                }
            }
        }

        $mixedAssets = 0;
        $mixedAssetExamples = [];
        if ($https) {
            foreach (array_merge($page->scriptSrcs(), $page->stylesheetHrefs()) as $url) {
                if (str_starts_with(strtolower($url), 'http://')) {
                    $mixedAssets++;
                    if (count($mixedAssetExamples) < 5) {
                        $mixedAssetExamples[] = $url;
                    }
                }
            }
            foreach ($page->images() as $image) {
                $src = (string) ($image['absolute'] ?? '');
                if (str_starts_with(strtolower($src), 'http://')) {
                    $mixedAssets++;
                    if (count($mixedAssetExamples) < 5) {
                        $mixedAssetExamples[] = $src;
                    }
                }
            }
        }

        return [
            'internal' => $internal,
            'external' => $external,
            'nofollow' => $nofollow,
            'empty' => $empty,
            'empty_examples' => $emptyExamples,
            'mixed_links' => $mixedLinks,
            'mixed_link_examples' => $mixedLinkExamples,
            'mixed_assets' => $mixedAssets,
            'mixed_asset_examples' => $mixedAssetExamples,
        ];
    }

    /** @param array<string, mixed> $anchor */
    private function anchorIsEmpty(array $anchor): bool
    {
        if (trim((string) $anchor['text']) !== '' || trim((string) $anchor['aria']) !== '' || trim((string) $anchor['title']) !== '') {
            return false;
        }
        foreach ($anchor['images'] as $alt) {
            if (trim((string) $alt) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{level: int, text: string}> $h1
     * @param array<string, mixed> $canonical
     * @param array<string, mixed> $schema
     * @param list<array{level: int, text: string}> $headings
     * @return list<array{label: string, tone: string, detail: string}>
     */
    private function checks(string $title, string $description, array $h1, array $canonical, bool $noindex, ?string $lang, int $status, array $schema, array $headings): array
    {
        $checks = [];
        $checks[] = [
            'label' => 'Status HTTP',
            'tone' => $status === 200 ? 'ok' : 'problem',
            'detail' => (string) $status,
        ];
        $checks[] = [
            'label' => 'Title',
            'tone' => $title === '' ? 'problem' : 'ok',
            'detail' => $title === '' ? 'Ausente' : $title . ' (' . mb_strlen($title) . ' caracteres)',
        ];
        $checks[] = [
            'label' => 'Meta description',
            'tone' => $description === '' ? 'attention' : 'ok',
            'detail' => $description === '' ? 'Ausente' : Text::excerpt($description, 220) . ' (' . mb_strlen($description) . ' caracteres)',
        ];
        $checks[] = [
            'label' => 'H1',
            'tone' => count($h1) === 1 ? 'ok' : 'attention',
            'detail' => count($h1) === 0 ? 'Nenhum H1' : count($h1) . ' — ' . Text::excerpt(implode(' | ', array_map(static fn (array $item): string => $item['text'], $h1)), 220),
        ];
        $checks[] = [
            'label' => 'Headings',
            'tone' => 'info',
            'detail' => count($headings) . ' heading(s) de H1 a H6',
        ];
        $checks[] = [
            'label' => 'Canonical',
            'tone' => $canonical['present'] ? (($canonical['self'] ?? false) ? 'ok' : 'info') : 'attention',
            'detail' => !$canonical['present'] ? 'Ausente' : (string) $canonical['url'] . (($canonical['self'] ?? false) ? ' · auto-referente' : ' · diferente da URL analisada'),
        ];
        $checks[] = [
            'label' => 'Robots',
            'tone' => $noindex ? 'problem' : 'ok',
            'detail' => $noindex ? 'noindex declarado' : 'noindex não declarado',
        ];
        $checks[] = [
            'label' => 'Lang',
            'tone' => $lang ? 'ok' : 'attention',
            'detail' => $lang ?? 'Ausente',
        ];
        $checks[] = [
            'label' => 'JSON-LD',
            'tone' => $schema['invalid'] !== [] ? 'problem' : ($schema['types'] !== [] ? 'ok' : 'info'),
            'detail' => $schema['invalid'] !== [] ? implode(' ', $schema['invalid']) : ($schema['types'] !== [] ? implode(', ', $schema['types']) : 'Nenhum bloco válido'),
        ];

        return $checks;
    }

    /**
     * Extras só entram na exibição quando existem. A classificação continua nos cinco campos usuais.
     *
     * @param array<string, ?string> $core
     * @return array<string, ?string>
     */
    private function openGraph(HtmlParser $page, array $core): array
    {
        foreach (['og:site_name', 'og:locale'] as $name) {
            $value = $page->metaProperty($name);
            if ($value !== null && $value !== '') {
                $core[$name] = $value;
            }
        }

        return $core;
    }
}
