<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Console;

use Override;
use Phil\SearchBundle\Application\Search;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:search:reindex            # every index
 *     bin/console phil:search:reindex pages      # one.
 *
 * Rebuilds indexes from their providers.
 */
#[AsCommand(name: 'phil:search:reindex', description: 'Rebuilds search indexes from their providers')]
final class ReindexCommand extends Command
{
    public function __construct(
        private readonly Search $search,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('indexes', InputArgument::IS_ARRAY, 'The indexes to rebuild; every one when none is given');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $asked an argument that is an array */
        $asked = $input->getArgument('indexes');

        foreach ([] === $asked ? $this->search->indexes() : $asked as $index) {
            $output->writeln(\sprintf('%s: %d document(s).', $index, $this->search->reindex($index)));
        }

        return Command::SUCCESS;
    }
}
