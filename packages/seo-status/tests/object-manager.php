<?php

use Doctrine\Bundle\DoctrineBundle\Registry as Doctrine;
use PiedWeb\SeoStatus\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../../../vendor/autoload.php';

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

/** @var Doctrine */
$doctrine = $kernel->getContainer()->get('doctrine');

return $doctrine->getManager();
