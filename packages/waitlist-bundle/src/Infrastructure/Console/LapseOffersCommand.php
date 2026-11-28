<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Infrastructure\Console;

use Phil\WaitlistBundle\Application\Waitlists;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lapses the offers whose deadline has come and offers their places to the next entries; a scheduler or a cron job
 * runs it every minute or so.
 */
#[AsCommand(name: self::NAME, description: 'Lapses the waiting list offers whose deadline has come and offers their places to the next entries.')]
final readonly class LapseOffersCommand
{
    public const string NAME = 'phil:waitlist:lapse';

    public function __construct(
        private Waitlists $waitlists,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $lapsed = $this->waitlists->lapseExpired();
        $io->success(\sprintf('%d offers lapsed.', $lapsed));

        return Command::SUCCESS;
    }
}
