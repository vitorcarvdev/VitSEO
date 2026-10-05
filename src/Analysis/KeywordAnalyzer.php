<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Support\HtmlParser;
use VitSeo\Support\Stopwords;
use VitSeo\Support\Text;

final class KeywordAnalyzer
{
    /**
     * @return array{findings: list<Finding>, data: array<string, mixed>}
     */
    public function analyze(HtmlParser $page): array
    {
        $zones = [
            'title' => $this->contentTokens($page->title()),
            'h1' => $this->contentTokens(implode(' ', array_map(
                static fn (array $heading): string => $heading['level'] === 1 ? $heading['text'] : '',
                $page->headings()
            ))),
            'headings' => $this->contentTokens(implode(' ', array_map(
                static fn (array $heading): string => $heading['text'],
                $page->headings()
            ))),
            'body' => $this->contentTokens(implode(' ', array_merge($page->paragraphs(), $page->listItems()))),
        ];

        $counts = [];
        $display = [];
        $total = 0;
        foreach ($zones as $tokens) {
            foreach ($tokens as $token) {
                $key = Text::stripAccents($token);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $display[$key][$token] = ($display[$key][$token] ?? 0) + 1;
                $total++;
            }
        }
        arsort($counts);

        $terms = [];
        foreach ($counts as $key => $count) {
            if (count($terms) >= 20) {
                break;
            }
            if ($count < 2 && $total > 40) {
                continue;
            }
            $surface = $this->surface($display[$key]);
            $terms[] = [
                'term' => $surface,
                'abs' => $count,
                'rel' => $total > 0 ? ($count / $total) * 100 : 0,
                'in_title' => $this->has($zones['title'], $key),
                'in_h1' => $this->has($zones['h1'], $key),
                'in_headings' => $this->has($zones['headings'], $key),
                'in_body' => $this->has($zones['body'], $key),
            ];
        }

        return [
            'findings' => [],
            'data' => [
                'total_tokens' => $total,
                'terms' => $terms,
                'note' => 'Termos mais frequentes no conteúdo analisado, depois da remoção de stopwords comuns em português. Isto não é a lista de palavras que um buscador associa ao site.',
            ],
        ];
    }

    /** @return list<string> */
    private function contentTokens(string $text): array
    {
        $tokens = [];
        foreach (Text::tokens($text) as $token) {
            if (Stopwords::isStopword($token) || preg_match('/^\d+$/', $token)) {
                continue;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    /** @param array<string, int> $forms */
    private function surface(array $forms): string
    {
        arsort($forms);

        return (string) array_key_first($forms);
    }

    /** @param list<string> $tokens */
    private function has(array $tokens, string $key): bool
    {
        foreach ($tokens as $token) {
            if (Text::stripAccents($token) === $key) {
                return true;
            }
        }

        return false;
    }
}
