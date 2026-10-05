<?php

declare(strict_types=1);

namespace VitSeo\Http;

interface DnsResolver
{
    /** @return list<string> */
    public function resolve(string $host): array;
}
