<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

final class Finding
{
    public function __construct(
        public readonly string $title,
        public readonly string $severity,
        public readonly string $classification,
        public readonly string $evidence,
        public readonly string $explanation,
        public readonly ?string $recommendation = null,
        public readonly string $category = 'geral',
    ) {
    }
}
