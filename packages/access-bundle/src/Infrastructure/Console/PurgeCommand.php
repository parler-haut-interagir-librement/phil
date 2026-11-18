<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Console;

use Phil\AccessBundle\Application\Contract\TuplePartitions;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\Exception\UnscopedTuples;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * Deletes the tuples whose expiry has passed: shares, memberships and accesses that already ended on their own. When
 * the tuples are partitioned by tenant, the purge deletes those of the command's tenant, `--tenant`, and fails without
 * one.
 *
 * `--all-tenants` enters each tenant whose partition holds tuples, in turn, through the tenant context of
 * `phil/tenancy-bundle`, purges that partition, and leaves the tenant, whether its purge succeeded or failed. A tenant
 * that fails is reported and the others are still purged; the command then fails. The option is refused when the
 * tuples are not partitioned by tenant, when the command already runs in a tenant, and without a list of partitions.
 */
#[AsCommand(name: 'phil:access:purge', description: 'Deletes the access tuples that have expired.')]
final readonly class PurgeCommand
{
    /**
     * @param ?TenantContext   $tenants    the tenant context, when the tuples are partitioned by tenant
     * @param ?TuplePartitions $partitions the partitions that hold tuples, for `--all-tenants`
     */
    public function __construct(
        private TupleStore $store,
        private ?TenantContext $tenants = null,
        private ?TuplePartitions $partitions = null,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Purge the partition of every tenant that holds tuples, one tenant after the other', name: 'all-tenants')]
        bool $allTenants = false,
    ): int {
        if ($allTenants) {
            return $this->purgeEveryTenant($io);
        }

        try {
            $deleted = $this->store->purgeExpired();
        } catch (UnscopedTuples $unscopedTuples) {
            $io->error($unscopedTuples->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d expired tuples deleted.', $deleted));

        return Command::SUCCESS;
    }

    private function purgeEveryTenant(SymfonyStyle $io): int
    {
        if (!$this->tenants instanceof TenantContext) {
            $io->error('--all-tenants purges the partition of each tenant: the tuples are partitioned by tenant only with phil/tenancy-bundle, and phil_access.partition "auto" or "tenant".');

            return Command::FAILURE;
        }

        if ($this->tenants->current() instanceof TenantId) {
            $io->error('Give --all-tenants or --tenant, not both.');

            return Command::FAILURE;
        }

        if (!$this->partitions instanceof TuplePartitions) {
            $io->error('--all-tenants needs the partitions that hold tuples: alias Phil\AccessBundle\Application\Contract\TuplePartitions to a service of the application, or keep the tuples in a table.');

            return Command::FAILURE;
        }

        $rows = [];
        $failed = 0;

        foreach ($this->partitions->partitions() as $partition) {
            $tenant = null;

            try {
                $tenant = TenantId::fromString($partition);
                // Entered within the try: an activation that fails on entering still leaves the tenant below.
                $this->tenants->enter($tenant);
                $rows[] = [OutputFormatter::escape($partition), $this->store->purgeExpired(), 'purged'];
            } catch (Throwable $throwable) {
                ++$failed;
                $rows[] = [OutputFormatter::escape($partition), '0', OutputFormatter::escape('failed: '.$throwable->getMessage())];
            } finally {
                if ($tenant instanceof TenantId) {
                    $this->tenants->leave();
                }
            }
        }

        if ([] === $rows) {
            $io->success('No tenant holds tuples.');

            return Command::SUCCESS;
        }

        $io->table(['Tenant', 'Expired tuples deleted', 'Outcome'], $rows);

        if (0 < $failed) {
            $io->error(\sprintf('%d of %d tenants failed; the others were purged.', $failed, \count($rows)));

            return Command::FAILURE;
        }

        $io->success(\sprintf('%d tenants purged.', \count($rows)));

        return Command::SUCCESS;
    }
}
