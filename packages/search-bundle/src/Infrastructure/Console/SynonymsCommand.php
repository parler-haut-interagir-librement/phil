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
 *     bin/console phil:search:synonyms           # every index
 *     bin/console phil:search:synonyms pages     # one.
 *
 * Gives the synonyms of the configured languages to an engine that keeps them itself, once they changed, without a
 * rebuild.
 */
#[AsCommand(name: 'phil:search:synonyms', description: 'Gives the synonyms to a search engine that keeps them itself')]
final class SynonymsCommand extends Command
{
    public function __construct(
        private readonly Search $search,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('indexes', InputArgument::IS_ARRAY, 'The indexes to give the synonyms to; every one when none is given');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $asked an argument that is an array */
        $asked = $input->getArgument('indexes');

        foreach ([] === $asked ? $this->search->indexes() : $asked as $index) {
            $output->writeln($this->search->publishSynonyms($index)
                ? \sprintf('%s: synonyms given to the engine.', $index)
                : \sprintf('%s: nothing to give, the index reads the synonyms at each search or no language is configured.', $index));
        }

        return Command::SUCCESS;
    }
}
