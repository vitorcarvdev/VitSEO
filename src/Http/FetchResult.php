<?php

declare(strict_types=1);

namespace VitSeo\Http;

final class FetchResult
{
    /**
     * @param array<string, string> $headers
     * @param array<string, float> $timings
     * @param list<string> $redirects
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $errorCode,
        public readonly string $message,
        public readonly int $status,
        public readonly string $requestedUrl,
        public readonly string $finalUrl,
        public readonly string $body,
        public readonly array $headers,
        public readonly array $timings,
        public readonly int $bytesDownloaded,
        public readonly array $redirects,
        public readonly float $dnsSeconds,
        public readonly bool $truncated,
    ) {
    }

    public static function failure(string $code, string $message, string $url = ''): self
    {
        return new self(false, $code, $message, 0, $url, $url, '', [], [], 0, [], 0.0, false);
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }
}
