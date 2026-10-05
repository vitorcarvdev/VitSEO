<?php

declare(strict_types=1);

namespace VitSeo\Http;

final class ValidationResult
{
    /**
     * @param list<string> $ips
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $errorCode,
        public readonly string $message,
        public readonly string $url,
        public readonly string $host,
        public readonly array $ips,
        public readonly float $dnsSeconds,
    ) {
    }

    public static function fail(string $code, string $message, string $url = ''): self
    {
        return new self(false, $code, $message, $url, '', [], 0.0);
    }
}
