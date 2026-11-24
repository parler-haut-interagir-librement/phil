<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\Console;

use Phil\SamlSp\Store\DbalReplayGuard;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the accepted assertions that have expired from the table of `DbalReplayGuard`, for a scheduled task; it is
 * registered when the accepted assertions are kept in the database.
 */
#[AsCommand(name: self::NAME, description: 'Deletes the expired assertions from the table of the accepted assertions.')]
final readonly class PurgeAssertionsCommand
{
    public const string NAME = 'phil:saml:purge-assertions';

    public function __construct(private DbalReplayGuard $guard, private ClockInterface $clock)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('%d expired assertion(s) deleted.', $this->guard->purge($this->clock->now())));

        return Command::SUCCESS;
    }
}
