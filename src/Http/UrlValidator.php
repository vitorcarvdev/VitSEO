<?php

declare(strict_types=1);

namespace VitSeo\Http;

final class UrlValidator
{
    /** @var list<string> */
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'metadata',
        'metadata.google.internal',
        'metadata.google.com',
        'instance-data',
        'kubernetes.default',
        'kubernetes.default.svc',
    ];

    /** @var list<string> */
    private const BLOCKED_SUFFIXES = [
        '.localhost',
        '.local',
        '.internal',
        '.localdomain',
        '.intranet',
        '.lan',
        '.home.arpa',
    ];

    public function __construct(private readonly DnsResolver $dns)
    {
    }

    public function validate(string $input): ValidationResult
    {
        $input = trim($input);
        if ($input === '' || strlen($input) > 2000) {
            return ValidationResult::fail('invalid_url', 'Informe uma URL http ou https válida.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $input) || str_contains($input, '\\')) {
            return ValidationResult::fail('invalid_url', 'Informe uma URL http ou https válida.');
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $input)) {
            $input = 'https://' . ltrim($input, '/');
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://[^/?\#]*@#i', $input)) {
            return ValidationResult::fail('blocked', 'URLs com usuário ou senha não são aceitas.');
        }

        $parts = parse_url($input);
        if (!is_array($parts) || empty($parts['scheme'])) {
            return ValidationResult::fail('invalid_url', 'Informe uma URL http ou https válida.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return ValidationResult::fail('blocked', 'Somente URLs http e https são aceitas.');
        }

        if (empty($parts['host'])) {
            return ValidationResult::fail('invalid_url', 'Informe uma URL http ou https válida.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return ValidationResult::fail('blocked', 'URLs com usuário ou senha não são aceitas.');
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === '' || preg_match('/[\s@\\\\%#]/', $host)) {
            return ValidationResult::fail('blocked', 'O endereço informado não pode ser consultado.');
        }

        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7E]/', $host)) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (!is_string($ascii) || $ascii === '') {
                return ValidationResult::fail('invalid_url', 'O domínio informado é inválido.');
            }
            $host = strtolower($ascii);
        }

        if ($this->hostIsBlockedName($host)) {
            return ValidationResult::fail('blocked', 'A URL foi bloqueada porque aponta para uma rede local ou interna.');
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $expected = $scheme === 'https' ? 443 : 80;
        if ($port !== null && $port !== $expected) {
            return ValidationResult::fail('blocked', 'Nesta versão só são aceitas as portas padrão 80 e 443.');
        }

        $literal = $this->literalIps($host);
        $dnsSeconds = 0.0;
        if ($literal !== null) {
            if ($literal === [] || $this->anyBlocked($literal)) {
                return ValidationResult::fail('blocked', 'A URL foi bloqueada porque aponta para um IP privado, local ou reservado.');
            }
            $ips = $literal;
        } else {
            $started = microtime(true);
            $ips = $this->dns->resolve($host);
            $dnsSeconds = microtime(true) - $started;
            if ($ips === []) {
                return ValidationResult::fail('dns', 'O domínio não foi encontrado ou não respondeu no DNS.');
            }
            if ($this->anyBlocked($ips)) {
                return ValidationResult::fail('blocked', 'O domínio resolve para um IP privado, local ou reservado e foi bloqueado.');
            }
        }

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        $url = $scheme . '://' . $host . $path . $query;

        return new ValidationResult(true, '', '', $url, $host, array_values($ips), $dnsSeconds);
    }

    private function hostIsBlockedName(string $host): bool
    {
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            return true;
        }

        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>|null null quando o host não é um IP
     */
    private function literalIps(string $host): ?array
    {
        $bare = trim($host, '[]');
        if (filter_var($bare, FILTER_VALIDATE_IP)) {
            return [$bare];
        }

        if (preg_match('/^(?:0x[0-9a-f]+|\d+)(?:\.(?:0x[0-9a-f]+|\d+))*$/i', $bare)) {
            return [];
        }

        return null;
    }

    /** @param list<string> $ips */
    private function anyBlocked(array $ips): bool
    {
        foreach ($ips as $ip) {
            if (IpGuard::isBlocked($ip)) {
                return true;
            }
        }

        return false;
    }
}
