<?php

declare(strict_types=1);

namespace PiedWeb\Crawler\Test;

use PiedWeb\Crawler\Crawler;
use PiedWeb\Crawler\CrawlerConfig;
use PiedWeb\Crawler\CrawlerUrl;
use PiedWeb\Crawler\Recorder;
use PiedWeb\Crawler\SimplePageRankCalculator;
use PiedWeb\Crawler\Url;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CrawlerTest extends \PHPUnit\Framework\TestCase
{
    public function testCrawlerUrl(): void
    {
        $url = new Url('https://dev.piedweb.com');
        new CrawlerUrl($url, (new CrawlerConfig())->setStartUrl('https://dev.piedweb.com/'));

        $this->assertGreaterThan(0, $url->getResponseTime());
    }

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

        $application->addCommand(new \PiedWeb\Crawler\Command\CrawlerCommand());
        $application->addCommand(new \PiedWeb\Crawler\Command\ShowExternalLinksCommand());
        $application->addCommand(new \PiedWeb\Crawler\Command\PageRankCommand());

        $command = $application->find('crawler:go');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'start' => 'https://dev.piedweb.com',
            '--quiet' => true,
            // prefix the key with two dashes when passing options,
            // e.g: '--some-option' => 'option_value',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('piedweb.com', $output);
    }

    public function testWithCachId(): void
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

        $restart = Crawler::restart($crawler->config->getId(), debug: false);
        $restart->crawl();

        $continue = Crawler::continue($crawler->config->getId(), false);
        $continue->crawl();

        $this->assertFileExists($crawler->config->getDataFolder().'/index.csv');
    }

    public function testHttpAuth(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->assertIsString($address);
        $server = proc_open(
            [\PHP_BINARY, '-S', $address, __DIR__.'/fixtures/http-auth.php'],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
        );
        $this->assertIsResource($server);

        try {
            for ($attempt = 0; $attempt < 100; ++$attempt) {
                $connection = @stream_socket_client('tcp://'.$address, timeout: 0.1);
                if (false !== $connection) {
                    fclose($connection);

                    break;
                }
                usleep(10000);
            }
            $this->assertLessThan(100, $attempt, 'HTTP fixture server did not start.');
            foreach (['', 'wrong:test', 'test:wrong'] as $credentials) {
                $request = new \PiedWeb\Curl\ExtendedClient('http://'.$address.'/');
                if ('' !== $credentials) {
                    $request->setOpt(\CURLOPT_USERPWD, $credentials);
                }
                $request->request();
                $this->assertSame(401, $request->getResponse()->getStatusCode());
            }

            $crawler = new Crawler((new CrawlerConfig(userPassword: 'test:test'))->setStartUrl('http://'.$address.'/'));
            $crawler->config->recordConfig();
            $crawler->crawl();
            $this->assertSame('Hello Test', $crawler->firstUrl()->getH1());
        } finally {
            fclose($pipes[0]);
            proc_terminate($server);
            proc_close($server);
        }
    }
}
