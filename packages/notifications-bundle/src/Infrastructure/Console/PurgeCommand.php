<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Console;

use Phil\NotificationsBundle\Application\Retention;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the notifications kept longer than the retention, now: what the schedule does every day.
 */
#[AsCommand(name: 'phil:notifications:purge', description: 'Deletes the notifications kept longer than the retention.')]
final readonly class PurgeCommand
{
    public function __construct(
        private Retention $retention,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('%d notifications deleted.', $this->retention->purge()));

        return Command::SUCCESS;
    }
}
