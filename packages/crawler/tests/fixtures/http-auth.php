<?php

declare(strict_types=1);

if (($_SERVER['PHP_AUTH_USER'] ?? '') !== 'test' || ($_SERVER['PHP_AUTH_PW'] ?? '') !== 'test') {
    header('WWW-Authenticate: Basic realm="Crawler fixture"');
    http_response_code(401);

    return;
}

header('Content-Type: text/html; charset=UTF-8');
echo '<html><head><title>Authenticated page</title></head><body><h1>Hello Test</h1></body></html>';
