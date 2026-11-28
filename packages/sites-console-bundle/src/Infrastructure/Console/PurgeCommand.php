<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Console;

use Phil\SitesConsoleBundle\Domain\Contract\SnapshotRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Removes the snapshots older than the retention, keeping the latest of each site.
 */
#[AsCommand(name: 'phil:sites-console:purge', description: 'Removes the snapshots older than the retention')]
final readonly class PurgeCommand
{
    public function __construct(
        private SnapshotRepository $snapshots,
        private ClockInterface $clock,
        private int $retentionDays,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $removed = $this->snapshots->purgeBefore($this->clock->now()->modify(\sprintf('-%d days', $this->retentionDays)));
        $io->success(\sprintf('%d snapshots removed.', $removed));

        return Command::SUCCESS;
    }
}
