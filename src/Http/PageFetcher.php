<?php

declare(strict_types=1);

namespace VitSeo\Http;

interface PageFetcher
{
    /**
     * @param array<string, mixed> $options
     */
    public function fetch(string $url, array $options = []): FetchResult;

    /**
     * @param list<string> $urls
     * @param array<string, mixed> $options
     * @return array<int, FetchResult>
     */
    public function fetchMany(array $urls, array $options = []): array;
}
