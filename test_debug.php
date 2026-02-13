<?php

require 'vendor/autoload.php';

use PiedWeb\Google\Extractor\SERPExtractor;

$html = file_get_contents('debug.html');
$extractor = new SERPExtractor($html);

echo "=== Organic results ===\n";
$results = $extractor->getResults(true);
foreach ($results as $i => $r) {
    $flag = $r->ads ? ' [ADS]' : '';
    echo "#{$r->organicPos} {$r->url}{$flag}\n";
}
echo "\nTotal: ".count($results)." organic results\n";
