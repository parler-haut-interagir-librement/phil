<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Console;

use Override;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:privacy:deletions.
 *
 * Runs the account deletions whose delay is over, for an application without a scheduler. It names requests by their
 * identifier and never the person.
 */
#[AsCommand(name: 'phil:privacy:deletions', description: 'Runs the account deletions whose delay is over')]
final class ProcessDeletionsCommand extends Command
{
    public const int BATCH = 100;

    public function __construct(
        private readonly AccountDeletions $deletions,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = Command::SUCCESS;

        foreach ($this->deletions->dueIdentifiers(self::BATCH) as $id) {
            try {
                $place = $this->deletions->advance($id);
                $output->writeln(\sprintf('%s: %s', $id, $place->value ?? 'unknown'));
            } catch (ErasureIncomplete $erasureIncomplete) {
                $output->writeln(\sprintf('%s: failed in %s', $id, implode(', ', array_keys($erasureIncomplete->failures))));
                $status = Command::FAILURE;
            }
        }

        $this->deletions->purgeExpiredExports();

        return $status;
    }
}
