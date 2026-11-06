<?php

declare(strict_types=1);

$list = static fn (string $key): array => array_values(array_filter(array_map('trim', explode(',', is_string($value = env($key)) ? $value : ''))));

return [
    // 64 Zeichen, im takuma manager erzeugt. Leer = Endpunkt antwortet immer 404.
    'token' => env('TAKUMA_REPORTER_TOKEN'),

    // Erlaubte Absender: IPv4/IPv6, auch CIDR, kommagetrennt.
    'ips' => $list('TAKUMA_REPORTER_IPS'),

    // Proxys (z.B. Load Balancer), deren X-Forwarded-For übernommen wird. Leer = nur REMOTE_ADDR zählt.
    'trusted_proxies' => $list('TAKUMA_REPORTER_TRUSTED_PROXIES'),
];
