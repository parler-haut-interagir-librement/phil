<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Console;

use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Exception\UnscopedTuples;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Deletes the tuples whose expiry has passed: shares, memberships and accesses that already ended on their own. When
 * the tuples are partitioned by tenant, the purge deletes those of the command's tenant, `--tenant`, and fails without
 * one.
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
        try {
            $deleted = $this->store->purgeExpired();
        } catch (UnscopedTuples $unscopedTuples) {
            $io->error($unscopedTuples->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d expired tuples deleted.', $deleted));

        return Command::SUCCESS;
    }
}
