<?php

namespace PiedWeb\SeoStatus\Command;

use PiedWeb\SeoStatus\Service\SearchImportBatch;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'search-results:import')]
class SearchResultsImportCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'search-results:import';

    private string $from = 'local';

    private string $code = 'fr-fr';

    private int $limit = 3;

    private bool $importSearch = false;

    private OutputInterface $output;

    private ProgressBar $progressBar;

    public int  $maxSearchesToImportBeforeFlush = 1;

    public function __construct(
        private SearchImportBatch $searchImportBatch,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, '', $this->from)
            ->addOption('code', 'c', InputOption::VALUE_REQUIRED, '', $this->code)
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, '-1 no limit', $this->limit)
            ->addOption('import-search', null, InputOption::VALUE_NONE, '')
        ;
    }

    private function initBatchImporter(InputInterface $input): void
    {
        $this->output->writeln('Starting...');
        $this->limit = $this->searchImportBatch->limit = \intval($input->getOption('limit'));
        $this->output->writeln('Limit : '.$this->limit);
        $this->from = $this->searchImportBatch->from = \strval($input->getOption('from'));
        $this->output->writeln('From : '.$this->from);
        $this->code = $this->searchImportBatch->code = \strval($input->getOption('code'));
        $this->output->writeln('code : '.$this->code);
        $this->importSearch = \boolval($input->getOption('import-search'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // @memprof_enable();
        $this->output = $output;
        $this->initBatchImporter($input);

        $this->progressBar = new ProgressBar(
            $output,
            \count($this->searchImportBatch->getFilesToImport())
        );
        $this->progressBar->start();
        $this->searchImportBatch->freezeCallback = [$this, 'updateProgressBar'];

        if ($this->importSearch) {
            $this->searchImportBatch->importSearches();
        }

        $this->searchImportBatch->importSearchResultsBatch();

        $this->progressBar->finish();

        return Command::SUCCESS;
    }

    public function updateProgressBar(int $count): void
    {
        $this->progressBar->setProgress($count);
    }
}
