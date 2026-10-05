<?php

declare(strict_types=1);

return [
    'name' => 'VitSEO',
    'timezone' => 'America/Sao_Paulo',
    'user_agent' => 'VitSEO/1.0 (auditoria interna de SEO)',
    'accept_language' => 'pt-BR,pt;q=0.9,en;q=0.8',
    'connect_timeout' => 8,
    'timeout' => 15,
    'max_redirects' => 5,
    'max_bytes' => 2000000,
    'aux_timeout' => 8,
    'aux_max_bytes' => 400000,
    'sitemap_max_bytes' => 500000,
    'subpage_limit' => 40,
    'subpage_checks' => 12,
    'subpage_timeout' => 5,
    'css_files' => 2,
    'css_max_bytes' => 200000,
    'ca_bundle' => __DIR__ . '/cacert.pem',
];
