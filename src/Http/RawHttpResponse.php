<?php

declare(strict_types=1);

namespace VitSeo\Http;

final class RawHttpResponse
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $info
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly int $errno,
        public readonly string $error,
        public readonly array $info,
        public readonly bool $truncated,
    ) {
    }
}
