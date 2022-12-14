<?php

namespace PiedWeb\Crawler\Command;

use PiedWeb\Crawler\ExtractExternalLinks;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ShowExternalLinksCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'crawler:external-links';

    protected ?string $id = null;

    protected function configure(): void
    {
        $this->setDescription('List external domain linked.');

        $this
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'id from a previous crawl'
                .\PHP_EOL.'You can use `last` to get show external links from the last crawl.'
            )
            ->addOption('host', 'ho', InputOption::VALUE_NONE, 'get only host')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $table = new Table($output);

        $table->setHeaders($input->getOption('host') ? ['Host'] : ['url', 'from']);

        $links = (new ExtractExternalLinks((string) $input->getArgument('id')))->get();
        arsort($links);
        $ever = [];
        foreach ($links as $link => $from) {
            if ($input->getOption('host')) {
                $host = parse_url($link, \PHP_URL_HOST);
                if ($host && ! isset($ever[$host])) {
                    $ever[$host] = 1;
                    $table->addRow([$host]);
                }
            } else {
                $first = true;
                foreach ($from as $url) {
                    $table->addRow([$first ? $link : '', $url]);
                    $first = false;
                }
            }
        }

        $table->render();

        return 0;
    }
}
