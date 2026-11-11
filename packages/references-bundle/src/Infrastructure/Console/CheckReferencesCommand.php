<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Infrastructure\Console;

use Override;
use Phil\ReferencesBundle\Application\References;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:references:check.
 *
 * Lists the links that point to resources that are gone, and fails while there is one.
 */
#[AsCommand(name: 'phil:references:check', description: 'Lists the links to resources that are gone')]
final class CheckReferencesCommand extends Command
{
    public function __construct(
        private readonly References $references,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $broken = $this->references->broken();

        foreach ($broken as $usage) {
            $output->writeln(\sprintf('%s (%s) → %s, gone', $usage->source->toString(), $usage->field, $usage->target->toString()));
        }

        if ([] === $broken) {
            $output->writeln('No broken link.');

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }
}
