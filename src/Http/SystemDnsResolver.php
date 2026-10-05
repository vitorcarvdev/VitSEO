<?php

declare(strict_types=1);

namespace VitSeo\Http;

final class SystemDnsResolver implements DnsResolver
{
    /** @var array<string, list<string>> */
    private array $cache = [];

    public function resolve(string $host): array
    {
        $host = strtolower($host);
        if (isset($this->cache[$host])) {
            return $this->cache[$host];
        }

        $ips = [];
        $recordsA = @dns_get_record($host, DNS_A);
        if (is_array($recordsA)) {
            foreach ($recordsA as $record) {
                if (!empty($record['ip'])) {
                    $ips[] = $record['ip'];
                }
            }
        }

        $recordsAAAA = @dns_get_record($host, DNS_AAAA);
        if (is_array($recordsAAAA)) {
            foreach ($recordsAAAA as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $fallback = @gethostbynamel($host);
            if (is_array($fallback)) {
                $ips = $fallback;
            }
        }

        $ips = array_values(array_unique($ips));
        $this->cache[$host] = $ips;

        return $ips;
    }
}
