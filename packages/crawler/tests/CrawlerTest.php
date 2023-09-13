<?php

declare(strict_types=1);

namespace PiedWeb\Crawler\Test;

use PiedWeb\Crawler\Crawler;
use PiedWeb\Crawler\CrawlerConfig;
use PiedWeb\Crawler\Recorder;
use PiedWeb\Crawler\SimplePageRankCalculator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CrawlerTest extends \PHPUnit\Framework\TestCase
{
    public function testIt(): void
    {
        $crawler = new Crawler(
            (new CrawlerConfig())->setStartUrl('https://dev.piedweb.com/')
        );
        $crawler->config->recordConfig();
        $crawler->crawl();

        $this->assertFileExists($crawler->config->getDataFolder().'/index.csv');

        $id = $crawler->config->getId();

        $crawlerRestart = Crawler::restart($id, true, false);
        $crawlerRestart->crawl();
        // todo test
        $crawlerRestart = Crawler::continue($id, false);
        $crawlerRestart->crawl();
        // todo test
        $prCalculator = new SimplePageRankCalculator($id);
        $prCalculator->record();
        // todo test
    }

    public function testCommand(): void
    {
        $application = new Application();

        $application->add(new \PiedWeb\Crawler\Command\CrawlerCommand());
        $application->add(new \PiedWeb\Crawler\Command\ShowExternalLinksCommand());
        $application->add(new \PiedWeb\Crawler\Command\PageRankCommand());

        $command = $application->find('crawler:go');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'start' => 'https://dev.piedweb.com',
            '--quiet',
            // prefix the key with two dashes when passing options,
            // e.g: '--some-option' => 'option_value',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('piedweb.com', $output);
    }

    public function testWitchCachId(): void
    {
        $crawler = new Crawler(
            (new CrawlerConfig(
                0,
                'HelloMe',
                Recorder::CACHE_ID
            ))->setStartUrl(
                'https://dev.piedweb.com/'
            )
        );
        $crawler->config->recordConfig();
        $crawler->crawl();

        $this->assertFileExists($crawler->config->getDataFolder().'/index.csv');

        $restart = Crawler::restart($crawler->config->getId());
        $restart->crawl();

        $continue = Crawler::continue($crawler->config->getId());
        $continue->crawl();

        $this->assertFileExists($crawler->config->getDataFolder().'/index.csv');
    }
}
