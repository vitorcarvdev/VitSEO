<?php

declare(strict_types=1);

namespace VitSeo\Analysis;

final class AuditReport
{
    /**
     * @param list<string> $redirects
     * @param array<string, mixed> $scoreDetails
     * @param list<Finding> $topProblems
     * @param list<Finding> $findings
     * @param array<string, list<Finding>> $groups
     * @param list<array<string, mixed>> $categories
     * @param array<string, mixed> $seo
     * @param array<string, mixed> $performance
     * @param array<string, mixed> $geo
     * @param array<string, mixed> $eeat
     * @param array<string, mixed> $images
     * @param array<string, mixed> $mobile
     * @param array<string, mixed> $analytics
     * @param array<string, mixed> $subpages
     * @param array<string, mixed> $keywords
     * @param array<string, mixed> $semantic
     * @param array<string, int> $counts
     */
    public function __construct(
        public readonly string $inputUrl,
        public readonly string $finalUrl,
        public readonly string $domain,
        public readonly int $httpStatus,
        public readonly float $durationMs,
        public readonly string $analyzedAt,
        public readonly array $redirects,
        public readonly float $score,
        public readonly string $diagnosis,
        public readonly array $scoreDetails,
        public readonly array $topProblems,
        public readonly array $findings,
        public readonly array $groups,
        public readonly int $urgentCount,
        public readonly array $categories,
        public readonly array $seo,
        public readonly array $performance,
        public readonly array $geo,
        public readonly array $eeat,
        public readonly array $images,
        public readonly array $mobile,
        public readonly array $analytics,
        public readonly array $subpages,
        public readonly array $keywords,
        public readonly array $semantic,
        public readonly array $counts,
    ) {
    }
}
