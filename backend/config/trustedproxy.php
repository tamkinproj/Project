<?php

// Reverse proxies allowed to set X-Forwarded-* headers (load balancer, the web app's server-side proxy).
return [
    'proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
