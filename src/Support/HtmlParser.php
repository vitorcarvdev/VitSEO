<?php

declare(strict_types=1);

namespace VitSeo\Support;

final class HtmlParser
{
    public readonly \DOMDocument $dom;

    public readonly \DOMXPath $xpath;

    public readonly string $baseUrl;

    public readonly bool $valid;

    /** @var list<string>|null */
    private ?array $jsonLd = null;

    public function __construct(string $html, string $baseUrl)
    {
        $html = $this->toUtf8($html);
        $this->dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $this->dom->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET | LIBXML_COMPACT
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->xpath = new \DOMXPath($this->dom);
        $this->valid = $loaded && $this->dom->documentElement !== null;
        $this->baseUrl = $this->resolveBase($baseUrl);
    }

    public function title(): string
    {
        $nodes = $this->dom->getElementsByTagName('title');
        if ($nodes->length === 0) {
            return '';
        }

        return Text::normalizeSpace($nodes->item(0)?->textContent ?? '');
    }

    public function htmlLang(): ?string
    {
        $lang = strtolower(trim($this->dom->documentElement?->getAttribute('lang') ?? ''));

        return $lang !== '' ? $lang : null;
    }

    public function metaByName(string $name): ?string
    {
        $values = $this->metasByName($name);

        return $values[0] ?? null;
    }

    /** @return list<string> */
    public function metasByName(string $name): array
    {
        $wanted = strtolower($name);
        $values = [];
        foreach ($this->dom->getElementsByTagName('meta') as $meta) {
            if (!$meta instanceof \DOMElement) {
                continue;
            }
            $metaName = strtolower(trim($meta->getAttribute('name')));
            if ($metaName === $wanted) {
                $values[] = trim($meta->getAttribute('content'));
            }
        }

        return $values;
    }

    public function metaProperty(string $property): ?string
    {
        $wanted = strtolower($property);
        foreach ($this->dom->getElementsByTagName('meta') as $meta) {
            if (!$meta instanceof \DOMElement) {
                continue;
            }
            $metaProperty = strtolower(trim($meta->getAttribute('property')));
            if ($metaProperty === $wanted) {
                $content = trim($meta->getAttribute('content'));

                return $content !== '' ? $content : null;
            }
        }

        return null;
    }

    public function canonical(): ?string
    {
        foreach ($this->dom->getElementsByTagName('link') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }
            $rel = strtolower($link->getAttribute('rel'));
            if (!preg_match('/(?:^|\s)canonical(?:\s|$)/', $rel)) {
                continue;
            }
            $href = trim($link->getAttribute('href'));
            if ($href === '') {
                return null;
            }

            return UrlHelper::resolve($this->baseUrl, $href);
        }

        return null;
    }

    /** @return list<array{level: int, text: string}> */
    public function headings(): array
    {
        $nodes = $this->xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
        $items = [];
        if (!$nodes) {
            return $items;
        }
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement || $this->inSkipped($node)) {
                continue;
            }
            $level = (int) substr(strtolower($node->tagName), 1);
            $items[] = ['level' => $level, 'text' => $this->nodeText($node)];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    public function images(): array
    {
        $items = [];
        foreach ($this->dom->getElementsByTagName('img') as $img) {
            if (!$img instanceof \DOMElement || $this->inSkipped($img)) {
                continue;
            }
            $src = trim($img->getAttribute('src'));
            if ($src === '') {
                $src = trim($img->getAttribute('data-src'));
            }
            $absolute = $src !== '' ? UrlHelper::resolve($this->baseUrl, $src) : null;
            $items[] = [
                'src' => $src,
                'absolute' => $absolute,
                'srcset' => trim($img->getAttribute('srcset')),
                'has_alt' => $img->hasAttribute('alt'),
                'alt' => $img->hasAttribute('alt') ? $img->getAttribute('alt') : null,
                'width' => trim($img->getAttribute('width')),
                'height' => trim($img->getAttribute('height')),
                'loading' => strtolower(trim($img->getAttribute('loading'))),
                'decoding' => strtolower(trim($img->getAttribute('decoding'))),
                'aria_hidden' => $this->isAriaHidden($img),
                'role' => strtolower(trim($img->getAttribute('role'))),
            ];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    public function anchors(): array
    {
        $items = [];
        foreach ($this->dom->getElementsByTagName('a') as $anchor) {
            if (!$anchor instanceof \DOMElement || !$anchor->hasAttribute('href')) {
                continue;
            }
            $href = trim($anchor->getAttribute('href'));
            $absolute = UrlHelper::resolve($this->baseUrl, $href);
            $items[] = [
                'href' => $href,
                'absolute' => $absolute,
                'text' => $this->nodeText($anchor),
                'aria' => trim($anchor->getAttribute('aria-label')),
                'title' => trim($anchor->getAttribute('title')),
                'rel' => strtolower(trim($anchor->getAttribute('rel'))),
                'images' => $this->nestedImageAlts($anchor),
            ];
        }

        return $items;
    }

    /** @return list<string> */
    public function jsonLdRaw(): array
    {
        if ($this->jsonLd !== null) {
            return $this->jsonLd;
        }
        $scripts = [];
        foreach ($this->dom->getElementsByTagName('script') as $script) {
            if (!$script instanceof \DOMElement) {
                continue;
            }
            if (strtolower(trim($script->getAttribute('type'))) !== 'application/ld+json') {
                continue;
            }
            $scripts[] = (string) $script->textContent;
        }
        $this->jsonLd = $scripts;

        return $scripts;
    }

    public function inlineCss(): string
    {
        $css = '';
        foreach ($this->dom->getElementsByTagName('style') as $style) {
            $css .= (string) $style->textContent . "\n";
        }

        return $css;
    }

    /** @return list<string> */
    public function styleAttributes(): array
    {
        $nodes = $this->xpath->query('//*[@style]');
        $styles = [];
        if (!$nodes) {
            return $styles;
        }
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $styles[] = $node->getAttribute('style');
            }
        }

        return $styles;
    }

    /** @return list<string> */
    public function stylesheetHrefs(): array
    {
        $hrefs = [];
        foreach ($this->dom->getElementsByTagName('link') as $link) {
            if (!$link instanceof \DOMElement) {
                continue;
            }
            $rel = strtolower($link->getAttribute('rel'));
            if (!preg_match('/(?:^|\s)stylesheet(?:\s|$)/', $rel)) {
                continue;
            }
            $href = trim($link->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $absolute = UrlHelper::resolve($this->baseUrl, $href);
            if ($absolute !== null) {
                $hrefs[] = $absolute;
            }
        }

        return $hrefs;
    }

    /** @return list<string> */
    public function scriptSrcs(): array
    {
        $srcs = [];
        foreach ($this->dom->getElementsByTagName('script') as $script) {
            if (!$script instanceof \DOMElement) {
                continue;
            }
            $src = trim($script->getAttribute('src'));
            if ($src === '') {
                continue;
            }
            $absolute = UrlHelper::resolve($this->baseUrl, $src);
            if ($absolute !== null) {
                $srcs[] = $absolute;
            }
        }

        return $srcs;
    }

    public function html(): string
    {
        return $this->dom->saveHTML() ?: '';
    }

    public function rawScriptsText(): string
    {
        $text = '';
        foreach ($this->dom->getElementsByTagName('script') as $script) {
            $text .= ' ' . $script->textContent;
            if ($script instanceof \DOMElement) {
                $text .= ' ' . $script->getAttribute('src');
            }
        }

        return $text;
    }

    /** @return list<string> */
    public function paragraphs(): array
    {
        return $this->textsFrom('//p[not(ancestor::nav) and not(ancestor::header) and not(ancestor::footer) and not(ancestor::script) and not(ancestor::style)]');
    }

    /** @return list<string> */
    public function listItems(): array
    {
        return $this->textsFrom('//li[not(ancestor::nav) and not(ancestor::header) and not(ancestor::footer)]');
    }

    public function contentText(): string
    {
        $root = $this->contentRoot();

        return $root ? $this->nodeText($root) : '';
    }

    public function countTag(string $tag): int
    {
        return $this->dom->getElementsByTagName($tag)->length;
    }

    public function contentRoot(): ?\DOMNode
    {
        $main = $this->xpath->query('//main');
        if ($main && $main->length > 0) {
            return $main->item(0);
        }
        $articles = $this->xpath->query('//article');
        if ($articles && $articles->length === 1) {
            return $articles->item(0);
        }
        $body = $this->dom->getElementsByTagName('body')->item(0);

        return $body ?: $this->dom->documentElement;
    }

    public function nodeText(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return Text::normalizeSpace($node->nodeValue ?? '');
        }
        if (!$node instanceof \DOMElement && !$node instanceof \DOMDocument) {
            return '';
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text .= ' ' . $child->nodeValue;
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $name = strtolower($child->tagName);
            if (in_array($name, ['script', 'style', 'noscript', 'svg', 'template'], true)) {
                continue;
            }
            if ($name === 'img') {
                $text .= ' ' . $child->getAttribute('alt');
                continue;
            }
            $text .= ' ' . $this->nodeText($child);
        }

        return Text::normalizeSpace($text);
    }

    /** @return list<string> */
    private function textsFrom(string $query): array
    {
        $nodes = $this->xpath->query($query);
        $items = [];
        if (!$nodes) {
            return $items;
        }
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $text = $this->nodeText($node);
            if ($text !== '') {
                $items[] = $text;
            }
        }

        return $items;
    }

    private function resolveBase(string $fallback): string
    {
        $nodes = $this->xpath->query('//base[@href]');
        if ($nodes && $nodes->length > 0) {
            $node = $nodes->item(0);
            if ($node instanceof \DOMElement) {
                $resolved = UrlHelper::resolve($fallback, trim($node->getAttribute('href')));
                if ($resolved !== null && preg_match('#^https?://#i', $resolved)) {
                    return $resolved;
                }
            }
        }

        return $fallback;
    }

    private function inSkipped(\DOMElement $node): bool
    {
        $parent = $node->parentNode;
        while ($parent instanceof \DOMElement) {
            if (in_array(strtolower($parent->tagName), ['script', 'style', 'template', 'svg'], true)) {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private function isAriaHidden(\DOMElement $node): bool
    {
        $current = $node;
        $depth = 0;
        while ($current instanceof \DOMElement && $depth < 6) {
            if (strtolower($current->getAttribute('aria-hidden')) === 'true') {
                return true;
            }
            $current = $current->parentNode instanceof \DOMElement ? $current->parentNode : null;
            $depth++;
        }

        return false;
    }

    /** @return list<string> */
    private function nestedImageAlts(\DOMElement $anchor): array
    {
        $alts = [];
        foreach ($anchor->getElementsByTagName('img') as $img) {
            if ($img instanceof \DOMElement && $img->hasAttribute('alt')) {
                $alts[] = $img->getAttribute('alt');
            }
        }

        return $alts;
    }

    private function toUtf8(string $html): string
    {
        $charset = null;
        if (preg_match('/charset\s*=\s*["\']?\s*([a-zA-Z0-9._-]+)/i', substr($html, 0, 3000), $matches)) {
            $charset = $matches[1];
        }
        if ($charset !== null && !preg_match('/utf-?8/i', $charset)) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $charset);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return $html;
    }
}
