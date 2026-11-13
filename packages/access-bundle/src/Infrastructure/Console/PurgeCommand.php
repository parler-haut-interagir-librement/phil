<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Console;

use Phil\ResourcePermissions\Contract\TupleStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the tuples whose expiry has passed: shares, memberships and accesses that already ended on their own.
 */
#[AsCommand(name: 'phil:access:purge', description: 'Deletes the access tuples that have expired.')]
final readonly class PurgeCommand
{
    public function __construct(
        private TupleStore $store,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('%d expired tuples deleted.', $this->store->purgeExpired()));

        return Command::SUCCESS;
    }
}
