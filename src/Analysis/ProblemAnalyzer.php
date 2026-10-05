<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

final class ProblemAnalyzer
{
    /**
     * @param list<Finding> $findings
     * @return array{critico: list<Finding>, importante: list<Finding>, melhoria: list<Finding>, informativo: list<Finding>}
     */
    public function group(array $findings): array
    {
        $groups = [
            'critico' => [],
            'importante' => [],
            'melhoria' => [],
            'informativo' => [],
        ];

        foreach ($findings as $finding) {
            if ($finding->classification === 'nao_verificavel') {
                continue;
            }
            $key = $finding->severity;
            if (!isset($groups[$key])) {
                $key = 'informativo';
            }
            $groups[$key][] = $finding;
        }

        return $groups;
    }

    /**
     * @param list<Finding> $findings
     * @return list<Finding>
     */
    public function top(array $findings): array
    {
        $selected = [];
        foreach (['critico', 'importante'] as $severity) {
            foreach ($findings as $finding) {
                if ($finding->classification !== 'comprovado' || $finding->severity !== $severity) {
                    continue;
                }
                $selected[] = $finding;
                if (count($selected) >= 5) {
                    return $selected;
                }
            }
        }

        return $selected;
    }
}
