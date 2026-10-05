<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

final class ScoreCalculator
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param list<Finding> $findings
     * @return array{score: float, start: float, proven_deduction: float, recommendation_deduction: float, recommendation_raw: float, recommendation_cap: float, lines: list<array{title: string, weight: float, classification: string}>}
     */
    public function calculate(array $findings): array
    {
        $start = (float) ($this->config['start'] ?? 10);
        $cap = (float) ($this->config['recommendation_cap'] ?? 1);
        $proven = 0.0;
        $recommendation = 0.0;
        $lines = [];

        foreach ($findings as $finding) {
            $weight = $this->weight($finding);
            if ($weight <= 0) {
                continue;
            }
            if ($finding->classification === 'comprovado') {
                $proven += $weight;
            } else {
                $recommendation += $weight;
            }
            $lines[] = [
                'title' => $finding->title,
                'weight' => $weight,
                'classification' => $finding->classification,
            ];
        }

        $appliedRecommendation = min($recommendation, $cap);
        $raw = max(0, min(10, $start - $proven - $appliedRecommendation));
        $score = round($raw, 1);
        if ($raw < $start && $score >= $start) {
            $score = round($start - 0.1, 1);
        }

        return [
            'score' => $score,
            'start' => $start,
            'proven_deduction' => round($proven, 2),
            'recommendation_deduction' => round($appliedRecommendation, 2),
            'recommendation_raw' => round($recommendation, 2),
            'recommendation_cap' => $cap,
            'lines' => $lines,
        ];
    }

    private function weight(Finding $finding): float
    {
        $table = $this->config['weights'][$finding->classification] ?? null;
        if (!is_array($table)) {
            return 0.0;
        }

        return (float) ($table[$finding->severity] ?? 0);
    }
}
