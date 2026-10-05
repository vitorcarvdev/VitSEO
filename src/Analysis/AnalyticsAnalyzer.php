<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Http\FetchResult;
use VitSeo\Support\HtmlParser;

final class AnalyticsAnalyzer
{
    /**
     * @return array{findings: list<Finding>, data: array<string, mixed>}
     */
    public function analyze(HtmlParser $page, FetchResult $fetch): array
    {
        $html = $fetch->body . "\n" . $page->rawScriptsText();
        $tags = [];

        if (preg_match_all('/GTM-[A-Z0-9]{4,}/', $html, $matches) && $this->hasAny($html, ['googletagmanager.com', 'gtm.js', 'ns.html?id=GTM-'])) {
            foreach (array_unique($matches[0]) as $id) {
                $tags[] = $this->tag('Google Tag Manager', $id);
            }
        }

        if ($this->hasAny($html, ['gtag/js', 'googletagmanager.com/gtag', 'google-analytics.com', 'gtag('])) {
            if (preg_match_all('/\bG-[A-Z0-9]{6,}\b/', $html, $matches)) {
                foreach (array_unique($matches[0]) as $id) {
                    $tags[] = $this->tag('Google Analytics 4', $id);
                }
            }
            if (preg_match_all('/\bUA-\d{4,}-\d+\b/', $html, $matches)) {
                foreach (array_unique($matches[0]) as $id) {
                    $tags[] = $this->tag('Universal Analytics', $id);
                }
            }
            if (preg_match_all('/\bAW-\d{6,}\b/', $html, $matches)) {
                foreach (array_unique($matches[0]) as $id) {
                    $tags[] = $this->tag('Google Ads', $id);
                }
            }
        }

        if ($this->hasAny($html, ['connect.facebook.net', 'fbevents.js', 'fbq('])) {
            $id = null;
            if (preg_match('/fbq\(\s*[\'"]init[\'"]\s*,\s*[\'"](\d{5,})[\'"]/', $html, $matches)) {
                $id = $matches[1];
            }
            $tags[] = $this->tag('Meta Pixel', $id);
        }

        if ($this->hasAny($html, ['clarity.ms'])) {
            $id = null;
            if (preg_match('#clarity\.ms/tag/([a-z0-9]+)#i', $html, $matches)) {
                $id = $matches[1];
            }
            $tags[] = $this->tag('Microsoft Clarity', $id);
        }

        if ($this->hasAny($html, ['static.hotjar.com', 'hotjar.com'])) {
            $tags[] = $this->tag('Hotjar', null);
        }
        if ($this->hasAny($html, ['js.hs-scripts.com', 'hs-scripts.com'])) {
            $tags[] = $this->tag('HubSpot', null);
        }
        if ($this->hasAny($html, ['d335luupugsy2.cloudfront.net', 'rdstation.com.br'])) {
            $tags[] = $this->tag('RD Station', null);
        }

        $summary = $tags === [] ? 'Nenhuma tag detectada' : count($tags) . ' tag(s) detectada(s)';

        return [
            'findings' => [],
            'data' => [
                'tags' => $tags,
                'summary' => $summary,
                'note' => 'Tag detectada significa que o identificador ou o script apareceu no HTML. Isso não comprova que a ferramenta esteja recebendo dados.',
            ],
        ];
    }

    /** @param list<string> $needles */
    private function hasAny(string $html, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($html, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{name: string, id: ?string, source: string} */
    private function tag(string $name, ?string $id): array
    {
        return [
            'name' => $name,
            'id' => $id,
            'source' => 'Detectado no HTML ou em script da página.',
        ];
    }
}
