<?php

return [
    'connect_timeout' => env('RESOLVER_CONNECT_TIMEOUT', 3),
    'request_timeout' => env('RESOLVER_REQUEST_TIMEOUT', 6),
    'user_agent' => env('RESOLVER_USER_AGENT', 'web-inventory-resolver/1.0'),
    'redirect_max_hops' => env('RESOLVER_REDIRECT_MAX_HOPS', 5),
    'redirect_cross_host' => env('RESOLVER_REDIRECT_CROSS_HOST', true),
    'skip_dns_check' => env('RESOLVER_SKIP_DNS_CHECK', false),
];
