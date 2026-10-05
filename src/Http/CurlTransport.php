<?php

declare(strict_types=1);

namespace VitSeo\Http;

final class CurlTransport implements HttpTransport
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function request(string $url, array $options): RawHttpResponse
    {
        $bag = [];
        $handle = $this->createHandle($url, $options, $bag);
        curl_exec($handle);
        $response = $this->collect($handle, $bag);
        curl_close($handle);

        return $response;
    }

    public function requestMany(array $requests): array
    {
        if ($requests === []) {
            return [];
        }

        $multi = curl_multi_init();
        $handles = [];
        $bag = [];

        foreach ($requests as $index => $request) {
            $handle = $this->createHandle((string) $request['url'], $request, $bag);
            $handles[$index] = $handle;
            curl_multi_add_handle($multi, $handle);
        }

        $running = null;
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        $results = [];
        foreach ($handles as $index => $handle) {
            $results[$index] = $this->collect($handle, $bag);
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);

        return $results;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<int, array{body: string, headers: array<string, string>, truncated: bool}> $bag
     */
    private function createHandle(string $url, array $options, array &$bag): \CurlHandle
    {
        $handle = curl_init($url);
        $id = spl_object_id($handle);
        $bag[$id] = ['body' => '', 'headers' => [], 'truncated' => false];

        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        $resolved = [];
        $ipv4 = [];
        $ipv6 = [];
        foreach ($options['ips'] ?? [] as $ip) {
            if (!is_string($ip) || $ip === '') {
                continue;
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ipv4[] = $ip;
            } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ipv6[] = $ip;
            }
        }
        // IPv4 é usado quando existe. IPv6 quebrado na rede local faz o cURL
        // falhar imediato se estiver na lista, mas o endereço IPv6 já foi
        // validado contra redes internas antes da conexão.
        $chosen = $ipv4 !== [] ? $ipv4 : $ipv6;
        foreach ($chosen as $ip) {
            $address = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
            $resolved[] = $host . ':' . $port . ':' . $address;
        }

        $maxBytes = (int) ($options['max_bytes'] ?? 2000000);
        $method = strtoupper((string) ($options['method'] ?? 'GET'));
        $timeout = (int) ($options['timeout'] ?? $this->config['timeout'] ?? 15);
        $connect = (int) ($this->config['connect_timeout'] ?? 8);

        $optionsCurl = [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => (string) ($this->config['user_agent'] ?? 'VitSEO/1.0'),
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ' . (string) ($this->config['accept_language'] ?? 'pt-BR,pt;q=0.9'),
            ],
            CURLOPT_HEADERFUNCTION => function ($curl, string $line) use (&$bag, $id): int {
                $length = strlen($line);
                $trim = trim($line);
                if ($trim === '' || str_starts_with($trim, 'HTTP/')) {
                    return $length;
                }
                $pos = strpos($trim, ':');
                if ($pos === false) {
                    return $length;
                }
                $name = strtolower(trim(substr($trim, 0, $pos)));
                $value = trim(substr($trim, $pos + 1));
                if (isset($bag[$id]['headers'][$name])) {
                    $bag[$id]['headers'][$name] .= ', ' . $value;
                } else {
                    $bag[$id]['headers'][$name] = $value;
                }

                return $length;
            },
        ];

        if ($method === 'HEAD') {
            $optionsCurl[CURLOPT_NOBODY] = true;
        } else {
            $optionsCurl[CURLOPT_HTTPGET] = true;
            $optionsCurl[CURLOPT_WRITEFUNCTION] = function ($curl, string $data) use (&$bag, $id, $maxBytes): int {
                if (strlen($bag[$id]['body']) + strlen($data) > $maxBytes) {
                    $bag[$id]['truncated'] = true;

                    return 0;
                }
                $bag[$id]['body'] .= $data;

                return strlen($data);
            };
        }

        if ($resolved !== []) {
            $optionsCurl[CURLOPT_RESOLVE] = $resolved;
            $optionsCurl[CURLOPT_IPRESOLVE] = $ipv4 !== [] ? CURL_IPRESOLVE_V4 : CURL_IPRESOLVE_V6;
        }

        $ca = (string) ($this->config['ca_bundle'] ?? '');
        if ($ca !== '' && is_file($ca)) {
            $optionsCurl[CURLOPT_CAINFO] = $ca;
        }

        curl_setopt_array($handle, $optionsCurl);

        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($handle, CURLOPT_PROTOCOLS_STR, 'http,https');
            curl_setopt($handle, CURLOPT_REDIR_PROTOCOLS_STR, 'http,https');
        }

        return $handle;
    }

    /**
     * @param array<int, array{body: string, headers: array<string, string>, truncated: bool}> $bag
     */
    private function collect(\CurlHandle $handle, array $bag): RawHttpResponse
    {
        $id = spl_object_id($handle);
        $slot = $bag[$id] ?? ['body' => '', 'headers' => [], 'truncated' => false];
        $info = curl_getinfo($handle);

        return new RawHttpResponse(
            (int) ($info['http_code'] ?? 0),
            $slot['headers'],
            $slot['body'],
            curl_errno($handle),
            curl_error($handle),
            is_array($info) ? $info : [],
            $slot['truncated'],
        );
    }
}
