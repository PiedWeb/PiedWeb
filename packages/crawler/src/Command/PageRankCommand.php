<?php

namespace PiedWeb\Crawler\Command;

use PiedWeb\Crawler\LinksVisualizer;
use PiedWeb\Crawler\SimplePageRankCalculator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PageRankCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'crawler:pagerank';

    protected ?string $id = null;

    protected function configure(): void
    {
        $this->setDescription('Add internal page rank to index.csv');

        $this
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'id from a previous crawl'
                .\PHP_EOL.'You can use `last` to calcul page rank from the last crawl.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pr = new SimplePageRankCalculator((string) $input->getArgument('id'));

        echo $pr->record().\PHP_EOL;

        new LinksVisualizer((string) $input->getArgument('id'));

        return 0;
    }
}
