<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Http\FetchResult;
use VitSeo\Support\HtmlParser;
use VitSeo\Support\UrlHelper;

final class ImageAnalyzer
{
    /**
     * @return array{findings: list<Finding>, data: array<string, mixed>}
     */
    public function analyze(HtmlParser $page, FetchResult $fetch): array
    {
        $items = [];
        $counts = [
            'total' => 0,
            'com_alt' => 0,
            'decorativas' => 0,
            'revisar' => 0,
            'sem_alt' => 0,
            'pixels' => 0,
            'sem_dimensoes' => 0,
            'lazy' => 0,
        ];
        $formats = [];
        $missingExamples = [];
        $reviewExamples = [];

        foreach ($page->images() as $image) {
            $kind = $this->classify($image);
            $counts['total']++;
            $counts[$kind === 'com_alt' ? 'com_alt' : ($kind === 'decorativa' ? 'decorativas' : ($kind === 'revisar' ? 'revisar' : ($kind === 'pixel' ? 'pixels' : 'sem_alt')))]++;
            if ($image['width'] === '' && $image['height'] === '') {
                $counts['sem_dimensoes']++;
            }
            if ($image['loading'] === 'lazy') {
                $counts['lazy']++;
            }
            $format = $this->format((string) ($image['absolute'] ?: $image['src']));
            $formats[$format] = ($formats[$format] ?? 0) + 1;
            $host = UrlHelper::host((string) ($image['absolute'] ?? '')) ?? 'não identificado';
            if (count($items) < 80) {
                $items[] = [
                    'src' => (string) ($image['src'] !== '' ? $image['src'] : '(sem src)'),
                    'alt' => $image['alt'],
                    'alt_label' => $this->label($kind),
                    'width' => (string) $image['width'],
                    'height' => (string) $image['height'],
                    'loading' => (string) $image['loading'],
                    'decoding' => (string) $image['decoding'],
                    'format' => $format,
                    'host' => $host,
                ];
            }
            if ($kind === 'sem_alt' && count($missingExamples) < 5) {
                $missingExamples[] = (string) ($image['src'] !== '' ? $image['src'] : '(sem src)');
            }
            if ($kind === 'revisar' && count($reviewExamples) < 5) {
                $reviewExamples[] = (string) ($image['src'] !== '' ? $image['src'] : '(sem src)');
            }
        }

        $findings = [];
        if ($counts['sem_alt'] > 0) {
            $findings[] = new Finding(
                $counts['sem_alt'] === 1 ? 'Imagem informativa sem atributo alt' : 'Imagens informativas sem atributo alt',
                'importante',
                'comprovado',
                $counts['sem_alt'] . ' imagem(ns) sem o atributo alt. Exemplos: ' . implode(' · ', $missingExamples),
                'O atributo alt não existe. Imagens com alt vazio e marcadas como decorativas não entram neste grupo.',
                'Inclua alt quando a imagem transmitir conteúdo. Use alt vazio apenas se ela for decorativa.',
                'imagens'
            );
        }
        if ($counts['revisar'] > 0) {
            $findings[] = new Finding(
                'Imagens com alt vazio para revisão manual',
                'melhoria',
                'recomendacao',
                $counts['revisar'] . ' imagem(ns) com alt="" sem aria-hidden nem role de apresentação. Exemplos: ' . implode(' · ', $reviewExamples),
                'Alt vazio pode ser correto em imagem decorativa. Sem um sinal claro disso, a classificação fica como recomendação, não como erro comprovado.',
                'Confira se cada uma é decorativa ou se precisa de um texto alternativo.',
                'imagens'
            );
        }
        if ($counts['total'] >= 3 && $counts['sem_dimensoes'] >= 3) {
            $findings[] = new Finding(
                'Imagens sem width e height',
                'melhoria',
                'recomendacao',
                $counts['sem_dimensoes'] . ' de ' . $counts['total'] . ' imagens não declaram width nem height.',
                'A falta de dimensões pode contribuir para instabilidade visual. O VitSEO não mediu o impacto no navegador.',
                null,
                'imagens'
            );
        }

        $summary = $counts['total'] === 0
            ? 'Nenhuma imagem <img> encontrada.'
            : $counts['total'] . ' imagens · ' . $counts['com_alt'] . ' com alt · ' . $counts['decorativas'] . ' decorativas';

        return [
            'findings' => $findings,
            'data' => [
                'total' => $counts['total'],
                'com_alt' => $counts['com_alt'],
                'decorativas' => $counts['decorativas'],
                'revisar' => $counts['revisar'],
                'sem_alt' => $counts['sem_alt'],
                'pixels' => $counts['pixels'],
                'sem_dimensoes' => $counts['sem_dimensoes'],
                'lazy' => $counts['lazy'],
                'formats' => $formats,
                'items' => $items,
                'truncated' => $counts['total'] > count($items),
                'summary' => $summary,
                'size_note' => 'Tamanho em bytes não consultado nesta versão, para evitar uma requisição extra por imagem.',
            ],
        ];
    }

    /** @param array<string, mixed> $image */
    private function classify(array $image): string
    {
        $hidden = (bool) $image['aria_hidden'] || in_array($image['role'], ['presentation', 'none'], true);
        $width = is_numeric($image['width']) ? (int) $image['width'] : 0;
        $height = is_numeric($image['height']) ? (int) $image['height'] : 0;
        if ($width === 1 && $height === 1) {
            return 'pixel';
        }
        if (!$image['has_alt']) {
            return $hidden ? 'decorativa' : 'sem_alt';
        }
        if (trim((string) $image['alt']) === '') {
            return $hidden ? 'decorativa' : 'revisar';
        }

        return 'com_alt';
    }

    private function label(string $kind): string
    {
        return match ($kind) {
            'com_alt' => 'Com alt',
            'decorativa' => 'Decorativa — OK',
            'revisar' => 'Revisar',
            'pixel' => 'Pixel 1×1',
            default => 'Sem alt',
        };
    }

    private function format(string $src): string
    {
        $path = (string) (parse_url($src, PHP_URL_PATH) ?? $src);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $known = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico'];
        if ($ext === 'jpeg') {
            return 'jpg';
        }

        return in_array($ext, $known, true) ? $ext : 'outro';
    }
}
