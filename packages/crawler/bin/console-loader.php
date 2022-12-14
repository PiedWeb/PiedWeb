<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\ErrorHandler\Debug;


if (false === in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
    echo 'Warning: The console should be invoked via the CLI version of PHP, not the '.\PHP_SAPI.' SAPI'.\PHP_EOL;
}

set_time_limit(0);

require dirname(__DIR__).'/../../vendor/autoload.php';

$input = new ArgvInput();

$application = new Application();

$application->add(new \PiedWeb\Crawler\Command\CrawlerCommand());
$application->add(new \PiedWeb\Crawler\Command\ShowExternalLinksCommand());
$application->add(new \PiedWeb\Crawler\Command\PageRankCommand());
