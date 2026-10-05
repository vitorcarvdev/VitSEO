<?php

declare(strict_types=1);

use VitSeo\Http\DnsResolver;
use VitSeo\Http\FetchResult;
use VitSeo\Http\HttpTransport;
use VitSeo\Http\PageFetcher;
use VitSeo\Http\RawHttpResponse;

final class FakeDns implements DnsResolver
{
    /** @param array<string, list<string>> $map */
    public function __construct(private array $map = [])
    {
    }

    public function resolve(string $host): array
    {
        return $this->map[strtolower($host)] ?? [];
    }
}

final class ExplodingDns implements DnsResolver
{
    public function resolve(string $host): array
    {
        throw new RuntimeException('DNS não deveria ser consultado para ' . $host);
    }
}

final class FakeTransport implements HttpTransport
{
    /** @var list<string> */
    public array $calls = [];

    /** @param array<string, RawHttpResponse> $responses */
    public function __construct(private array $responses)
    {
    }

    public function request(string $url, array $options): RawHttpResponse
    {
        $this->calls[] = $url;

        return $this->responses[$url] ?? new RawHttpResponse(0, [], '', 7, 'connect', [], false);
    }

    public function requestMany(array $requests): array
    {
        $results = [];
        foreach ($requests as $index => $request) {
            $results[$index] = $this->request((string) $request['url'], $request);
        }

        return $results;
    }
}

final class MapFetcher implements PageFetcher
{
    /** @var list<string> */
    public array $calls = [];

    /** @param array<string, FetchResult> $map */
    public function __construct(private array $map)
    {
    }

    public function fetch(string $url, array $options = []): FetchResult
    {
        $this->calls[] = $url;

        return $this->map[$url] ?? FetchResult::failure('network', 'não mapeado', $url);
    }

    public function fetchMany(array $urls, array $options = []): array
    {
        $results = [];
        foreach ($urls as $index => $url) {
            $results[$index] = $this->fetch($url, $options);
        }

        return $results;
    }
}

function http_response(int $status, array $headers = [], string $body = ''): RawHttpResponse
{
    return new RawHttpResponse($status, $headers, $body, 0, '', [
        'total_time' => 0.05,
        'namelookup_time' => 0.0,
        'connect_time' => 0.01,
        'appconnect_time' => 0.02,
        'starttransfer_time' => 0.03,
        'size_download' => strlen($body),
    ], false);
}

function fetch_of(string $url, string $html, array $headers = [], int $status = 200): FetchResult
{
    return new FetchResult(true, '', '', $status, $url, $url, $html, $headers, [
        'namelookup' => 0.0,
        'connect' => 0.01,
        'appconnect' => 0.02,
        'starttransfer' => 0.04,
        'total' => 0.05,
    ], strlen($html), [], 0.01, false);
}

function http_document(string $url, int $status = 404): FetchResult
{
    return new FetchResult(true, '', '', $status, $url, $url, '', ['content-type' => 'text/plain'], [], 0, [], 0.0, false);
}
