<?php

use Symfony\Component\Console\Application;

require dirname(__DIR__).'/../../vendor/autoload.php';

$application = new Application();

$application->addCommand(new PiedWeb\Crawler\Command\CrawlerCommand());
$application->addCommand(new PiedWeb\Crawler\Command\ShowExternalLinksCommand());
$application->addCommand(new PiedWeb\Crawler\Command\PageRankCommand());

return $application;
