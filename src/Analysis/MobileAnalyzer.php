<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Http\FetchResult;
use VitSeo\Http\PageFetcher;
use VitSeo\Support\HtmlParser;
use VitSeo\Support\Text;
use VitSeo\Support\UrlHelper;

final class MobileAnalyzer
{
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
        $viewport = $page->metaByName('viewport');
        $cssNotes = [];
        $css = $page->inlineCss() . "\n" . implode("\n", $page->styleAttributes());
        $sources = [];
        if (str_contains($css, '@media')) {
            $sources[] = 'CSS inline ou atributo style';
        }

        $fetched = 0;
        foreach ($page->stylesheetHrefs() as $href) {
            if ($fetched >= (int) ($this->config['css_files'] ?? 2)) {
                break;
            }
            if (!UrlHelper::isSameSite($fetch->finalUrl, $href)) {
                $cssNotes[] = 'CSS de outro domínio não analisado: ' . $href;
                continue;
            }
            $result = $this->fetcher->fetch($href, [
                'timeout' => (int) ($this->config['aux_timeout'] ?? 8),
                'max_bytes' => (int) ($this->config['css_max_bytes'] ?? 200000),
            ]);
            $fetched++;
            if (!$result->ok || $result->status !== 200) {
                $cssNotes[] = 'CSS não lido: ' . $href;
                continue;
            }
            $css .= "\n" . $result->body;
            if (str_contains($result->body, '@media')) {
                $sources[] = $href;
            }
        }

        $fixed = $this->fixedWidths($page, $css);
        $menu = $this->menu($page);
        $findings = [];
        if ($viewport === null || trim($viewport) === '') {
            $findings[] = new Finding(
                'Viewport ausente',
                'importante',
                'comprovado',
                'Não há meta name="viewport".',
                'A página não declara viewport. Isso não comprova, sozinho, como ela aparece em um aparelho.',
                'Inclua uma meta viewport se a página deve se adaptar a telas menores.',
                'mobile'
            );
        }
        if ($fixed !== []) {
            $findings[] = new Finding(
                'Larguras fixas grandes no código',
                'melhoria',
                'risco_potencial',
                implode(' · ', array_slice($fixed, 0, 5)),
                'Larguras fixas grandes podem causar overflow em telas menores. Isto é um indício no código, não uma comprovação de falha visual.',
                'A validação visual em um dispositivo ou navegador continua necessária.',
                'mobile'
            );
        }

        $hasStructural = $viewport === null || $viewport === '';
        if (!$hasStructural && $fixed === []) {
            $summary = 'Nenhum problema estrutural evidente detectado. Validação visual em dispositivo/browser continua recomendada.';
            $status = 'Sem evidência de falha';
        } elseif ($hasStructural) {
            $summary = 'A meta viewport não foi encontrada.';
            $status = 'Viewport ausente';
        } else {
            $summary = 'Há larguras fixas grandes no código. Isso é risco potencial, não prova de que o site não seja responsivo.';
            $status = 'Risco potencial';
        }

        $maxWidth = preg_match_all('/img[^{}]*\{[^}]*max-width\s*:/i', $css) ?: 0;
        $imgMax = 0;
        foreach ($page->styleAttributes() as $style) {
            if (preg_match('/max-width\s*:/i', $style)) {
                $imgMax++;
            }
        }

        return [
            'findings' => $findings,
            'data' => [
                'viewport' => $viewport,
                'viewport_present' => $viewport !== null && trim($viewport) !== '',
                'media_queries' => ['found' => $sources !== [], 'sources' => $sources],
                'fixed_widths' => $fixed,
                'tables' => $page->countTag('table'),
                'iframes' => $page->countTag('iframe'),
                'overflow' => preg_match_all('/overflow-x\s*:/i', $css) ?: 0,
                'max_width_hints' => $maxWidth + $imgMax,
                'mobile_menu' => $menu,
                'css_notes' => array_slice(array_values(array_unique($cssNotes)), 0, 6),
                'summary' => $summary,
                'status' => $status,
                'note' => 'Não há navegador nem aparelho real nesta versão. Nenhum achado daqui afirma que o site seja ou não responsivo.',
            ],
        ];
    }

    /** @return list<string> */
    private function fixedWidths(HtmlParser $page, string $css): array
    {
        $examples = [];
        if (preg_match_all('/(?:^|[^-])(?:min-width|width)\s*:\s*(\d{3,4})\s*px/i', $css, $matches)) {
            foreach ($matches[1] as $value) {
                if ((int) $value >= 700 && count($examples) < 8) {
                    $examples[] = 'width/min-width: ' . $value . 'px';
                }
            }
        }
        foreach (['table', 'iframe'] as $tag) {
            foreach ($page->dom->getElementsByTagName($tag) as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                $width = $node->getAttribute('width');
                if (is_numeric($width) && (int) $width >= 700 && count($examples) < 8) {
                    $examples[] = '<' . $tag . ' width="' . $width . '">';
                }
            }
        }

        return array_values(array_unique($examples));
    }

    /** @return array{found: bool, evidence: string} */
    private function menu(HtmlParser $page): array
    {
        $nodes = $page->xpath->query('//*[contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "hamburger") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "menu-toggle") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "nav-toggle") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "navbar-toggler") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "menu-mobile") or contains(translate(@class, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "mobile-menu")]');
        if ($nodes && $nodes->length > 0) {
            $node = $nodes->item(0);

            return ['found' => true, 'evidence' => 'Classe identificada: ' . ($node instanceof \DOMElement ? $node->getAttribute('class') : 'elemento')];
        }
        foreach ($page->dom->getElementsByTagName('button') as $button) {
            if (!$button instanceof \DOMElement) {
                continue;
            }
            $label = Text::stripAccents($button->getAttribute('aria-label') . ' ' . $button->textContent);
            if (preg_match('/\b(menu|navegacao)\b/', $label)) {
                return ['found' => true, 'evidence' => 'Botão com rótulo relacionado a menu.'];
            }
        }

        return ['found' => false, 'evidence' => 'Nenhum controle de menu móvel foi identificado. A ausência desse sinal não comprova que ele não exista.'];
    }
}
