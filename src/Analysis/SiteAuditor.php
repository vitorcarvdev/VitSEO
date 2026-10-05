<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Http\FetchResult;
use VitSeo\Http\PageFetcher;
use VitSeo\Support\HtmlParser;

final class SiteAuditor
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly ScoreCalculator $scores,
        private readonly array $config,
    ) {
    }

    public function audit(string $input): AuditReport
    {
        $started = microtime(true);
        $fetch = $this->fetcher->fetch(trim($input));
        if (!$fetch->ok) {
            throw new AuditException($fetch->errorCode !== '' ? $fetch->errorCode : 'network', $fetch->message);
        }
        if (trim($fetch->body) === '') {
            throw new AuditException('empty', 'A resposta chegou sem HTML.');
        }
        if (!$this->looksLikeHtml($fetch)) {
            throw new AuditException('unparseable', 'A resposta não contém HTML interpretável.');
        }

        $parser = new HtmlParser($fetch->body, $fetch->finalUrl);
        if (!$parser->valid) {
            throw new AuditException('unparseable', 'A resposta não contém HTML interpretável.');
        }

        $seo = (new SeoAnalyzer())->analyze($parser, $fetch);
        $performance = (new PerformanceAnalyzer())->analyze($parser, $fetch);
        $images = (new ImageAnalyzer())->analyze($parser, $fetch);
        $mobile = (new MobileAnalyzer($this->fetcher, $this->config))->analyze($parser, $fetch);
        $geo = (new GeoAnalyzer($this->fetcher, $this->config))->analyze($parser, $fetch);
        $eeat = (new EeatAnalyzer())->analyze($parser, $fetch);
        $analytics = (new AnalyticsAnalyzer())->analyze($parser, $fetch);
        $subpages = (new SubpageAnalyzer($this->fetcher, $this->config))->analyze($parser, $fetch);
        $keywords = (new KeywordAnalyzer())->analyze($parser);
        $semantic = (new SemanticAnalyzer())->analyze($parser);

        /** @var list<Finding> $findings */
        $findings = array_merge(
            $seo['findings'],
            $performance['findings'],
            $images['findings'],
            $mobile['findings'],
            $geo['findings'],
            $eeat['findings'],
            $analytics['findings'],
            $subpages['findings'],
            $keywords['findings'],
            $semantic['findings'],
        );

        $problems = new ProblemAnalyzer();
        $score = $this->scores->calculate($findings);
        $urgent = 0;
        foreach ($findings as $finding) {
            if ($finding->classification === 'comprovado' && in_array($finding->severity, ['critico', 'importante'], true)) {
                $urgent++;
            }
        }

        $timezone = new \DateTimeZone((string) ($this->config['timezone'] ?? 'America/Sao_Paulo'));
        $domain = parse_url($fetch->finalUrl, PHP_URL_HOST);

        return new AuditReport(
            $input,
            $fetch->finalUrl,
            is_string($domain) && $domain !== '' ? $domain : $fetch->finalUrl,
            $fetch->status,
            (microtime(true) - $started) * 1000,
            (new \DateTimeImmutable('now', $timezone))->format('d/m/Y H:i'),
            $fetch->redirects,
            $score['score'],
            $this->diagnosis($findings),
            $score,
            $problems->top($findings),
            $findings,
            $problems->group($findings),
            $urgent,
            $this->categories($findings, $performance['data'], $images['data'], $mobile['data'], $geo['data'], $eeat['data'], $analytics['data']),
            $seo['data'],
            $performance['data'],
            $geo['data'],
            $eeat['data'],
            $images['data'],
            $mobile['data'],
            $analytics['data'],
            $subpages['data'],
            $keywords['data'],
            $semantic['data'],
            [
                'images' => (int) $images['data']['total'],
                'internal_links' => (int) $subpages['data']['discovered'],
                'words' => (int) $semantic['data']['words'],
            ],
        );
    }

    private function looksLikeHtml(FetchResult $fetch): bool
    {
        $type = strtolower($fetch->header('content-type'));
        $sample = ltrim(substr($fetch->body, 0, 5000));
        $sniff = preg_match('/<\s*(!doctype|html|head|body|title|meta|div|p|h1|section|main)\b/i', $sample) === 1;
        if ($type !== '' && !str_contains($type, 'html') && !str_contains($type, 'xml') && !str_contains($type, 'text/plain')) {
            return false;
        }
        if (str_contains($type, 'html') || str_contains($type, 'xhtml')) {
            return true;
        }

        return $sniff;
    }

    /** @param list<Finding> $findings */
    private function diagnosis(array $findings): string
    {
        $critical = 0;
        $important = 0;
        foreach ($findings as $finding) {
            if ($finding->classification !== 'comprovado') {
                continue;
            }
            if ($finding->severity === 'critico') {
                $critical++;
            } elseif ($finding->severity === 'importante') {
                $important++;
            }
        }
        if ($critical === 0 && $important === 0) {
            return 'Nenhum problema crítico ou importante comprovado nesta página.';
        }
        if ($critical > 0 && $important > 0) {
            return 'Há ' . $this->countLabel($critical, 'problema crítico comprovado', 'problemas críticos comprovados')
                . ' e ' . $this->countLabel($important, 'importante', 'importantes')
                . '. A nota resume esses achados e não é uma medida absoluta.';
        }
        if ($critical > 0) {
            return 'Há ' . $this->countLabel($critical, 'problema crítico comprovado', 'problemas críticos comprovados')
                . '. A nota resume esses achados e não é uma medida absoluta.';
        }

        return 'Há ' . $this->countLabel($important, 'ponto importante comprovado', 'pontos importantes comprovados')
            . '. A nota é um resumo visual, não uma medida absoluta.';
    }

    /**
     * @param list<Finding> $findings
     * @param array<string, mixed> $performance
     * @param array<string, mixed> $images
     * @param array<string, mixed> $mobile
     * @param array<string, mixed> $geo
     * @param array<string, mixed> $eeat
     * @param array<string, mixed> $analytics
     * @return list<array<string, mixed>>
     */
    private function categories(array $findings, array $performance, array $images, array $mobile, array $geo, array $eeat, array $analytics): array
    {
        $seoScore = $this->categoryScore($findings, 'seo');
        $imageScore = ((int) $images['total']) > 0 ? $this->categoryScore($findings, 'imagens') : null;

        return [
            [
                'label' => 'Velocidade',
                'score' => null,
                'figure' => $performance['figure'],
                'status' => 'Medido',
                'summary' => $performance['summary'],
            ],
            [
                'label' => 'Imagens',
                'score' => $imageScore,
                'figure' => null,
                'status' => $imageScore === null ? 'Não avaliado' : ($imageScore >= 9 ? 'Verificado' : 'Revisar'),
                'summary' => $images['summary'],
            ],
            [
                'label' => 'Mobile',
                'score' => null,
                'figure' => null,
                'status' => $mobile['status'],
                'summary' => $mobile['summary'],
            ],
            [
                'label' => 'SEO',
                'score' => $seoScore,
                'figure' => null,
                'status' => $seoScore >= 9 ? 'Estável' : ($seoScore >= 7 ? 'Revisar' : 'Problemas comprovados'),
                'summary' => 'Title, headings, canonical, robots e dados estruturados.',
            ],
            [
                'label' => 'GEO',
                'score' => null,
                'figure' => null,
                'status' => 'Informativo',
                'summary' => $geo['summary'],
            ],
            [
                'label' => 'E-E-A-T',
                'score' => null,
                'figure' => null,
                'status' => 'Sinais',
                'summary' => $eeat['summary'],
            ],
            [
                'label' => 'Analytics',
                'score' => null,
                'figure' => null,
                'status' => 'Informativo',
                'summary' => $analytics['summary'],
            ],
        ];
    }

    /** @param list<Finding> $findings */
    private function categoryScore(array $findings, string $category): float
    {
        $subset = array_values(array_filter(
            $findings,
            static fn (Finding $finding): bool => $finding->category === $category
        ));

        return $this->scores->calculate($subset)['score'];
    }

    private function countLabel(int $count, string $one, string $many): string
    {
        return $count . ' ' . ($count === 1 ? $one : $many);
    }
}
