<?php

declare(strict_types=1);

require_once __DIR__.'/../../../vendor/autoload.php';

use PiedWeb\Crawler\CrawlerConfig;
use PiedWeb\Crawler\CrawlerUrl;
use PiedWeb\Crawler\Url;

$targetUrl = 'https://altimood.com';

echo "Testing CrawlerUrl with: {$targetUrl}\n";
echo str_repeat('-', 50)."\n";

$url = new Url($targetUrl);
new CrawlerUrl($url, (new CrawlerConfig())->setStartUrl($targetUrl.'/'));

echo 'Status Code: '.$url->getStatusCode()."\n";
echo 'Network Status: '.$url->getNetworkStatus()."\n";
echo 'Mime Type: '.$url->getMimeType()."\n";
echo 'Response Time: '.$url->getResponseTime()." ms\n";
echo 'Size: '.$url->getSize()." bytes\n";

$html = $url->getHtml();
echo 'HTML set: '.('' !== $html ? 'YES ('.strlen($html).' chars)' : 'NO')."\n";

// Check if 206 is correctly handled (should be in 200-299 range)
$statusCode = $url->getStatusCode();
$isSuccess = $statusCode >= 200 && $statusCode < 300;
echo "\nStatus code {$statusCode} is ".($isSuccess ? 'SUCCESS (2xx)' : 'NOT SUCCESS')."\n";

if (206 === $statusCode) {
    echo "\n*** 206 Partial Content detected - this should be treated as success ***\n";
}

echo str_repeat('-', 50)."\n";
echo "Test completed.\n";
