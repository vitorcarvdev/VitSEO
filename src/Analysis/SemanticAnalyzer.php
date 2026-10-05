<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

use VitSeo\Support\HtmlParser;
use VitSeo\Support\Stopwords;
use VitSeo\Support\Text;

final class SemanticAnalyzer
{
    /**
     * @return array{findings: list<Finding>, data: array<string, mixed>}
     */
    public function analyze(HtmlParser $page): array
    {
        $paragraphs = $page->paragraphs();
        $text = trim(implode(' ', $paragraphs));
        if ($text === '') {
            $text = $page->contentText();
        }
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordCount = count($words);
        $chars = array_sum(array_map(static fn (string $paragraph): int => mb_strlen($paragraph), $paragraphs));
        $paragraphCount = count($paragraphs);

        $headings = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        foreach ($page->headings() as $heading) {
            $headings[$heading['level']] = ($headings[$heading['level']] ?? 0) + 1;
        }

        $sequence = [];
        foreach (Text::tokens($text) as $token) {
            if (Stopwords::isStopword($token) || preg_match('/^\d+$/', $token)) {
                continue;
            }
            $sequence[] = Text::stripAccents($token);
        }
        $bigrams = $this->ngrams($sequence, 2, 12);
        $trigrams = $this->ngrams($sequence, 3, 8);
        $unique = count(array_unique($sequence));
        $ttr = $sequence !== [] ? $unique / count($sequence) : 0.0;

        $freq = array_count_values($sequence);
        arsort($freq);
        $terms = [];
        foreach (array_slice($freq, 0, 10, true) as $term => $count) {
            $terms[] = ['term' => $term, 'count' => $count];
        }

        return [
            'findings' => [],
            'data' => [
                'words' => $wordCount,
                'paragraphs' => $paragraphCount,
                'avg_words' => $paragraphCount > 0 ? $wordCount / $paragraphCount : 0,
                'avg_chars' => $paragraphCount > 0 ? $chars / $paragraphCount : 0,
                'headings' => $headings,
                'lists' => $page->countTag('ul') + $page->countTag('ol'),
                'tables' => $page->countTag('table'),
                'terms' => $terms,
                'bigrams' => $bigrams,
                'trigrams' => $trigrams,
                'ttr' => $ttr,
                'readability' => $this->readability($text, $words),
                'note' => 'Esta análise descreve padrões encontrados no conteúdo. Não representa diretamente como o Google classifica a página.',
            ],
        ];
    }

    /**
     * @param list<string> $tokens
     * @return list<array{text: string, count: int}>
     */
    private function ngrams(array $tokens, int $size, int $limit): array
    {
        $counts = [];
        $last = count($tokens) - $size;
        for ($i = 0; $i <= $last; $i++) {
            $gram = implode(' ', array_slice($tokens, $i, $size));
            $counts[$gram] = ($counts[$gram] ?? 0) + 1;
        }
        arsort($counts);
        $items = [];
        foreach ($counts as $text => $count) {
            if ($count < 2 || count($items) >= $limit) {
                break;
            }
            $items[] = ['text' => $text, 'count' => $count];
        }

        return $items;
    }

    /**
     * Flesch adaptado para o português, só como aproximação:
     * IL = 248,835 − (1,015 × palavras por frase) − (84,6 × sílabas por palavra).
     * Sílabas são grupos de vogais, não uma contagem linguística.
     *
     * @param list<string> $words
     * @return array<string, mixed>
     */
    private function readability(string $text, array $words): array
    {
        $formula = 'IL = 248,835 − (1,015 × palavras por frase) − (84,6 × sílabas por palavra). Adaptação aproximada de Flesch para o português; a sílaba é estimada por grupos de vogais.';
        $sentences = preg_split('/[.!?]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sentences = array_values(array_filter($sentences, static fn (string $sentence): bool => str_word_count($sentence) > 0 || preg_match('/\p{L}/u', $sentence)));
        if (count($words) < 80 || count($sentences) < 3) {
            return [
                'available' => false,
                'score' => null,
                'band' => null,
                'formula' => $formula,
                'words_per_sentence' => null,
                'syllables_per_word' => null,
                'reason' => 'Texto curto demais para uma estimativa útil de legibilidade.',
            ];
        }

        $syllables = 0;
        foreach ($words as $word) {
            preg_match_all('/[aeiouáéíóúâêôãõàü]+/iu', $word, $groups);
            $syllables += max(1, count($groups[0]));
        }
        $wordsPerSentence = count($words) / count($sentences);
        $syllablesPerWord = $syllables / count($words);
        $score = 248.835 - (1.015 * $wordsPerSentence) - (84.6 * $syllablesPerWord);
        $band = 'leitura muito difícil';
        if ($score >= 75) {
            $band = 'leitura mais fácil';
        } elseif ($score >= 50) {
            $band = 'leitura média';
        } elseif ($score >= 25) {
            $band = 'leitura mais difícil';
        }

        return [
            'available' => true,
            'score' => round($score, 1),
            'band' => $band,
            'formula' => $formula,
            'words_per_sentence' => round($wordsPerSentence, 1),
            'syllables_per_word' => round($syllablesPerWord, 2),
            'reason' => '',
        ];
    }
}
