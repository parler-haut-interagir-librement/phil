<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Console;

use Phil\Outbox\Housekeeping;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes the delivered records past their retention, the oldest first; the pending and dead records stay.
 */
#[AsCommand(name: 'phil:outbox:purge', description: 'Removes the delivered records of the outbox past their retention')]
final readonly class PurgeCommand
{
    public function __construct(
        private Housekeeping $housekeeping,
        private int $retention = Housekeeping::RETENTION,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('The seconds a delivered record is kept; the configured retention when not given')]
        ?int $retention = null,
        #[Option('The most records to remove')]
        int $limit = 1000,
    ): int {
        $retention ??= $this->retention;

        if ($retention < 0) {
            $io->error('--retention is 0 seconds or more.');

            return Command::INVALID;
        }

        $io->success(\sprintf('%d delivered records removed.', $this->housekeeping->purge($retention, max(1, $limit))));

        return Command::SUCCESS;
    }
}
