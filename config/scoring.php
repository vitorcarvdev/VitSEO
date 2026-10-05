<?php

declare(strict_types=1);

/**
 * A nota parte de 10.
 * Só problemas comprovados reduzem de forma relevante.
 * Recomendações e riscos potenciais entram num teto baixo.
 * Informativo e não verificável não reduzem a nota.
 */
return [
    'start' => 10.0,
    'recommendation_cap' => 1.0,
    'weights' => [
        'comprovado' => [
            'critico' => 1.5,
            'importante' => 0.7,
            'melhoria' => 0.25,
            'informativo' => 0.0,
        ],
        'recomendacao' => [
            'critico' => 0.15,
            'importante' => 0.12,
            'melhoria' => 0.06,
            'informativo' => 0.0,
        ],
        'risco_potencial' => [
            'critico' => 0.05,
            'importante' => 0.05,
            'melhoria' => 0.05,
            'informativo' => 0.0,
        ],
        'informativo' => [
            'critico' => 0.0,
            'importante' => 0.0,
            'melhoria' => 0.0,
            'informativo' => 0.0,
        ],
        'nao_verificavel' => [
            'critico' => 0.0,
            'importante' => 0.0,
            'melhoria' => 0.0,
            'informativo' => 0.0,
        ],
    ],
];
