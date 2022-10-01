<?php

namespace PiedWeb\SeoStatus\Command;

use Doctrine\ORM\EntityManagerInterface;
use PiedWeb\SeoStatus\Entity\Search\Search;
use PiedWeb\SeoStatus\Repository\SearchRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'search:import')]
class SearcbImportCommand extends Command
{
    /**
     * @var string
     */
    protected static $defaultName = 'search:import';

    private string $code = 'fr-fr';

    private OutputInterface $output;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('keywords', InputArgument::REQUIRED, '')
            ->addOption('code', 'c', InputOption::VALUE_REQUIRED, '', $this->code)
        ;
    }

    private function initOptions(InputInterface $input): void
    {
        $this->output->writeln('Starting...');
        $this->code = \strval($input->getOption('code'));
        $this->output->writeln('code : '.$this->code);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->initOptions($input);

        /** @var SearchRepository */
        $searchRepo = $this->entityManager->getRepository(Search::class);

        $keywords = explode(\chr(10), \strval($input->getArgument('keywords')));
        foreach ($keywords as $keyword) {
            $create = false;
            $search = $searchRepo->findOrCreate($keyword, $create);
            $output->writeln('`'.$search->getKeyword().'` '.(true === $create ? 'will be import' : 'exists'));
        }

        $this->entityManager->flush();
        $output->writeln('Import done...');

        return Command::SUCCESS;
    }
}
