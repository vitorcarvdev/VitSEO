<?php

declare(strict_types=1);

namespace VitSeo\Http;

interface HttpTransport
{
    /**
     * @param array<string, mixed> $options
     */
    public function request(string $url, array $options): RawHttpResponse;

    /**
     * @param array<int, array<string, mixed>> $requests
     * @return array<int, RawHttpResponse>
     */
    public function requestMany(array $requests): array;
}
