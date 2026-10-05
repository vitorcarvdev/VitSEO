<?php

declare(strict_types=1);

namespace VitSeo\Http;

use VitSeo\Support\UrlHelper;

final class UrlFetcher implements PageFetcher
{
    /** @var array<string, FetchResult> */
    private array $cache = [];

    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly UrlValidator $validator,
        private readonly HttpTransport $transport,
        private readonly array $config,
    ) {
    }

    public function fetch(string $url, array $options = []): FetchResult
    {
        $validation = $this->validator->validate($url);
        if (!$validation->ok) {
            return FetchResult::failure($validation->errorCode, $validation->message, $url);
        }

        $method = strtoupper((string) ($options['method'] ?? 'GET'));
        $key = $method . ' ' . $validation->url;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $result = $this->fetchValidated($validation, $options, 0);
        if ($result->ok) {
            $this->cache[$key] = $result;
        }

        return $result;
    }

    public function fetchMany(array $urls, array $options = []): array
    {
        $results = [];
        $pending = [];

        foreach ($urls as $index => $url) {
            $validation = $this->validator->validate($url);
            if (!$validation->ok) {
                $results[$index] = FetchResult::failure($validation->errorCode, $validation->message, $url);
                continue;
            }
            $pending[$index] = $validation;
        }

        if ($pending === []) {
            return $results;
        }

        $requests = [];
        foreach ($pending as $index => $validation) {
            $requests[$index] = [
                'url' => $validation->url,
                'ips' => $validation->ips,
                'method' => strtoupper((string) ($options['method'] ?? 'HEAD')),
                'timeout' => (int) ($options['timeout'] ?? 5),
                'max_bytes' => (int) ($options['max_bytes'] ?? 2048),
            ];
        }

        $raws = $this->transport->requestMany($requests);
        foreach ($pending as $index => $validation) {
            $raw = $raws[$index] ?? new RawHttpResponse(0, [], '', 7, 'Falha de conexão.', [], false);
            $results[$index] = $this->fromRaw($validation, $raw, []);
        }

        ksort($results);

        return $results;
    }

    /** @param array<string, mixed> $options */
    private function fetchValidated(ValidationResult $validation, array $options, int $hop): FetchResult
    {
        $maxRedirects = (int) ($options['max_redirects'] ?? $this->config['max_redirects'] ?? 5);
        $raw = $this->transport->request($validation->url, [
            'ips' => $validation->ips,
            'method' => strtoupper((string) ($options['method'] ?? 'GET')),
            'timeout' => (int) ($options['timeout'] ?? $this->config['timeout'] ?? 15),
            'max_bytes' => (int) ($options['max_bytes'] ?? $this->config['max_bytes'] ?? 2000000),
        ]);

        $redirects = [];
        $dnsSeconds = $validation->dnsSeconds;
        $current = $validation;
        $seen = [$validation->url => true];

        while ($this->isRedirect($raw)) {
            if ($hop >= $maxRedirects) {
                return FetchResult::failure('network', 'O limite de redirecionamentos foi atingido.', $validation->url);
            }

            $location = trim($raw->headers['location'] ?? '');
            if ($location === '') {
                break;
            }

            $nextUrl = UrlHelper::resolve($current->url, $location);
            if ($nextUrl === null) {
                return FetchResult::failure('invalid_url', 'O redirecionamento aponta para uma URL inválida.', $current->url);
            }

            $next = $this->validator->validate($nextUrl);
            if (!$next->ok) {
                $message = $next->errorCode === 'blocked'
                    ? 'Um redirecionamento leva a um destino bloqueado por segurança.'
                    : $next->message;

                return FetchResult::failure($next->errorCode, $message, $nextUrl);
            }

            if (isset($seen[$next->url])) {
                return FetchResult::failure('network', 'O site entrou em um ciclo de redirecionamentos.', $next->url);
            }

            $seen[$next->url] = true;
            $redirects[] = $next->url;
            $dnsSeconds += $next->dnsSeconds;
            $hop++;
            $current = $next;
            $raw = $this->transport->request($current->url, [
                'ips' => $current->ips,
                'method' => 'GET',
                'timeout' => (int) ($options['timeout'] ?? $this->config['timeout'] ?? 15),
                'max_bytes' => (int) ($options['max_bytes'] ?? $this->config['max_bytes'] ?? 2000000),
            ]);
        }

        $result = $this->fromRaw($current, $raw, $redirects);

        return new FetchResult(
            $result->ok,
            $result->errorCode,
            $result->message,
            $result->status,
            $validation->url,
            $current->url,
            $result->ok ? $result->body : '',
            $result->headers,
            $result->timings,
            $result->bytesDownloaded,
            $redirects,
            $dnsSeconds,
            $result->truncated,
        );
    }

    /** @param list<string> $redirects */
    private function fromRaw(ValidationResult $validation, RawHttpResponse $raw, array $redirects): FetchResult
    {
        if ($raw->truncated) {
            return FetchResult::failure('too_large', 'O HTML excede o limite de 2 MB desta versão.', $validation->url);
        }

        if ($raw->errno !== 0 || $raw->status === 0) {
            return FetchResult::failure(
                $this->errorCode($raw),
                $this->errorMessage($raw),
                $validation->url,
            );
        }

        $info = $raw->info;

        return new FetchResult(
            true,
            '',
            '',
            $raw->status,
            $validation->url,
            $validation->url,
            $raw->body,
            $raw->headers,
            [
                'namelookup' => (float) ($info['namelookup_time'] ?? 0),
                'connect' => (float) ($info['connect_time'] ?? 0),
                'appconnect' => (float) ($info['appconnect_time'] ?? 0),
                'starttransfer' => (float) ($info['starttransfer_time'] ?? 0),
                'total' => (float) ($info['total_time'] ?? 0),
            ],
            (int) ($info['size_download'] ?? strlen($raw->body)),
            $redirects,
            $validation->dnsSeconds,
            false,
        );
    }

    private function isRedirect(RawHttpResponse $raw): bool
    {
        return in_array($raw->status, [301, 302, 303, 307, 308], true);
    }

    private function errorCode(RawHttpResponse $raw): string
    {
        $ssl = [35, 51, 53, 54, 58, 59, 60, 66, 77, 80, 82, 83, 90, 91];
        if (in_array($raw->errno, $ssl, true) || str_contains(strtolower($raw->error), 'ssl')) {
            return 'ssl';
        }
        if ($raw->errno === 28) {
            return 'timeout';
        }
        if (in_array($raw->errno, [6, 7], true)) {
            return $raw->errno === 6 ? 'dns' : 'network';
        }

        return 'network';
    }

    private function errorMessage(RawHttpResponse $raw): string
    {
        return match ($this->errorCode($raw)) {
            'ssl' => 'O certificado SSL não pôde ser validado.',
            'timeout' => 'A requisição excedeu o tempo limite.',
            'dns' => 'O domínio não foi encontrado ou não respondeu no DNS.',
            default => 'Não foi possível conectar ao endereço informado.',
        };
    }
}
