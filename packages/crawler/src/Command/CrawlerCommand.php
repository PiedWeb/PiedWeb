<?php

namespace PiedWeb\Crawler\Command;

use PiedWeb\Crawler\Crawler;
use PiedWeb\Crawler\CrawlerConfig;
use PiedWeb\Curl\StaticClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CrawlerCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'crawler:go';

    protected ?string $id = null;

    protected function configure(): void
    {
        $this->setDescription('Crawl a website.');

        $this
            ->addArgument(
                'start',
                InputArgument::REQUIRED,
                'Define where the crawl start. Eg: https://piedweb.com'
                .\PHP_EOL.'You can specify an id from a previous crawl. Other options will not be listen.'
                .\PHP_EOL.'You can use `last` to continue the last crawl (just stopped).'
            )
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Define where a depth limit', 5)
            ->addOption(
                'ignore',
                'i',
                InputOption::VALUE_REQUIRED,
                'Virtual Robots.txt to respect (could be a string or an URL).'
            )
            ->addOption(
                'user-agent',
                'u',
                InputOption::VALUE_REQUIRED,
                'Define the user-agent used during the crawl.',
                'SEO Pocket Crawler - PiedWeb.com/seo/crawler'
            )
            ->addOption(
                'wait',
                'w',
                InputOption::VALUE_REQUIRED,
                'In Microseconds, the time to wait between 2 requests. Default 0,1s.',
                100000
            )
            ->addOption(
                'cache-method',
                'c',
                InputOption::VALUE_REQUIRED,
                'In Microseconds, the time to wait between two request. Default : 100000 (0,1s).',
                \PiedWeb\Crawler\Recorder::CACHE_ID
            )
            ->addOption(
                'restart',
                'r',
                InputOption::VALUE_REQUIRED,
                'Permit to restart a previous crawl. Values 1 = fresh restart, 2 = restart from cache'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->checkArguments($input);

        $start = microtime(true);

        $crawler = $this->initCrawler($input);

        $output->writeln(['', '', 'Crawl starting !', '============', '', 'ID: '.$crawler->config->getId()]);
        $output->writeln([
            null !== $this->id ? ($input->getOption('restart') ? 'Restart' : 'Continue') : '',
            '',
            'Details : ',
            '- Crawl starting at '.$crawler->config->getStartUrl(),
            '- User-Agent used `'.$crawler->config->userAgent,
            '- `'.$crawler->config->sleepBetweenReqInMs.' ms between two requests',
        ]);

        $crawler->crawl();

        $end = microtime(true);

        $output->writeln(['', '---------------', 'Crawl succeed', 'You can find your data in ']);

        echo realpath($crawler->config->getDataFolder()).'/data.csv'.\PHP_EOL;

        $output->writeln(['', '', '----Chrono----', round($end - $start, 2).'s', '', '']);

        return 0;
    }

    public function checkArguments(InputInterface $input): void
    {
        $start = $input->getArgument('start');
        if (! filter_var($start, \FILTER_VALIDATE_URL)) {
            if (! \is_string($start)) {
                throw new \LogicException();
            }

            $this->id = $start;
        }
    }

    public function initCrawler(InputInterface $input): Crawler
    {
        if (null === $this->id) {
            return new Crawler(
                (new CrawlerConfig(
                    \intval($input->getOption('limit')),
                    \strval($input->getOption('user-agent')),
                    \intval($input->getOption('cache-method')),
                    \intval($input->getOption('wait')),
                    $this->loadVirtualRobotsTxt($input)
                ))->setStartUrl(\strval($input->getArgument('start'))),
                ! $input->getOption('quiet')
            );
        }

        if ($input->getOption('restart')) {
            return Crawler::restart(
                $this->id,
                2 == $input->getOption('restart') ? true : false, // $fromCache
                ! $input->getOption('quiet')
            );
        }

        return Crawler::continue($this->id, ! $input->getOption('quiet'));
    }

    public function loadVirtualRobotsTxt(InputInterface $input): string
    {
        if (null === $input->getOption('ignore')) {
            return '';
        }

        $ignore = \strval($input->getOption('ignore'));

        if (filter_var($ignore, \FILTER_VALIDATE_URL)) {
            return StaticClient::request($ignore);
        }

        if (file_exists($ignore)) {
            return \Safe\file_get_contents($ignore);
        }

        throw new \Exception('An error occured with your --ignore option');
    }
}
