<?php

declare(strict_types=1);

namespace VitSeo\Http;

final class IpGuard
{
    public static function isBlocked(string $ip): bool
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        if (str_starts_with(strtolower($ip), '::ffff:')) {
            $mapped = substr($ip, 7);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return self::isBlocked($mapped);
            }
        }

        $public = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($public === false) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long !== false && ($long & 0xFFC00000) === 0x64400000) {
                return true;
            }
        }

        return false;
    }
}
