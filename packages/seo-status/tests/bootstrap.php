<?php

use PiedWeb\SeoStatus\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;

if (file_exists(dirname(__DIR__).'/vendor/autoload.php'))
    require dirname(__DIR__).'/vendor/autoload.php';
else
    require dirname(__DIR__).'./../../vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Refresh database for tests
$kernel = new Kernel('test', true);
$kernel->boot();
$application = new Application($kernel);
$application->setAutoExit(false);

$dirToCreate = dirname(__DIR__).'/var/data/test';
exec('mkdir -p "'.$dirToCreate.'"');

$application->run(new ArrayInput(['command' => 'doctrine:database:drop', '--no-interaction' => true, '--force' => true]), new ConsoleOutput());
$application->run(new ArrayInput(['command' => 'doctrine:database:create', '--no-interaction' => true]), new ConsoleOutput());
$application->run(new ArrayInput(['command' => 'doctrine:schema:create', '--quiet' => true]), new ConsoleOutput());

// Import One kw
$fs = new Filesystem();
$fs->remove(__DIR__.'/../var/data/test/search/');
$fs->mirror(__DIR__.'/searchData/pieds_vert', __DIR__.'/../var/data/test/search/fr-fr/pieds_vert');
