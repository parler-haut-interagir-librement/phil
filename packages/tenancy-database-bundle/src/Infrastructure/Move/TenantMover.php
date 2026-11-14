<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Move;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\PlacementStore;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantNotCopyable;
use Phil\TenancyDatabaseBundle\Domain\Exception\TenantNotMoved;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\PinnedConnection;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Moves a tenant from one database to another — from the shared database to one of its own, back, or between two
 * dedicated ones — and changes its placement only once its rows are copied and verified.
 *
 * 1. The tenant is suspended: its requests are answered with a `503` and its messages wait.
 * 2. Its rows are copied to the target in one transaction, table by table in the order of the {@see CopyPlan}, after
 *    any rows of the tenant left in the target are deleted; on PostgreSQL, each generated key's sequence catches up.
 * 3. Each table is compared, row by row in the order of its key, between the source and the target.
 * 4. The placement is recorded in the {@see PlacementStore}, then the source is emptied of the tenant's rows, unless
 *    they are kept.
 * 5. The tenant is resumed.
 *
 * Any failure before the source is emptied rolls the move back: the old placement is recorded again, the copied rows
 * are deleted from the target, and the tenant is served from its source, unchanged.
 *
 * The source and the target are two connections of their own, opened with the parameters of the managed connection
 * pinned to each database, and closed at the end. The move runs in the tenant, so that row-level security, if it is
 * enabled, lets its rows through.
 */
final readonly class TenantMover
{
    public function __construct(
        private Connection $connection,
        private TenantDatabaseRouter $router,
        private EntityManagerInterface $entityManager,
        private TenantPlacement $placement,
        private TenantDatabaseAvailability $availability,
        private TenantContext $context,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @throws TenantNotMoved    when the move failed and was rolled back, or the placement cannot change
     * @throws TenantNotCopyable when the mapping cannot be copied table by table
     */
    public function move(TenantId $tenant, Placement $target, bool $keepSource = false): MoveReport
    {
        $store = $this->placement;

        if (!$store instanceof PlacementStore) {
            throw TenantNotMoved::placementIsReadOnly($tenant);
        }

        $source = $store->placementOf($tenant);

        if ($source->equals($target)) {
            return new MoveReport($tenant, $source, $target, [], false);
        }

        $plan = CopyPlan::of($this->entityManager);

        return $this->context->within($tenant, function () use ($store, $tenant, $source, $target, $plan, $keepSource): MoveReport {
            $this->availability->suspendTenant($tenant);

            try {
                return $this->moveSuspended($store, $tenant, $source, $target, $plan, $keepSource);
            } finally {
                $this->availability->resumeTenant($tenant);
            }
        });
    }

    /**
     * @param list<CopyTable> $plan
     */
    private function moveSuspended(PlacementStore $store, TenantId $tenant, Placement $source, Placement $target, array $plan, bool $keepSource): MoveReport
    {
        $from = $this->open($source);
        $to = $this->open($target);

        try {
            $rows = $this->copyAndPlace($store, $from, $to, $tenant, $source, $target, $plan);
            $purged = false;

            if (!$keepSource) {
                try {
                    $from->transactional(fn () => $this->delete($from, $plan, $tenant));
                    $purged = true;
                } catch (Throwable $failure) {
                    // The tenant is served from its target already: the rows left in the source are only unused.
                    $this->logger?->warning('The tenant "{tenant}" moved to "{to}", but its rows were left in "{from}": {failure}.', ['tenant' => $tenant->value, 'from' => $source->toString(), 'to' => $target->toString(), 'failure' => $failure::class]);
                }
            }

            $this->logger?->info('The tenant "{tenant}" moved from "{from}" to "{to}".', ['tenant' => $tenant->value, 'from' => $source->toString(), 'to' => $target->toString()]);

            return new MoveReport($tenant, $source, $target, $rows, $purged);
        } finally {
            $from->close();
            $to->close();
        }
    }

    /**
     * @param list<CopyTable> $plan
     *
     * @return array<string, int>
     */
    private function copyAndPlace(PlacementStore $store, Connection $from, Connection $to, TenantId $tenant, Placement $source, Placement $target, array $plan): array
    {
        try {
            $rows = $to->transactional(function () use ($from, $to, $plan, $tenant): array {
                $this->delete($to, $plan, $tenant);
                $rows = [];

                foreach ($plan as $table) {
                    $rows[$table->table] = $this->copy($from, $to, $table, $tenant);
                }

                $this->catchUpSequences($to, $plan);

                return $rows;
            });

            foreach ($plan as $table) {
                if (!hash_equals($this->digest($from, $table, $tenant), $this->digest($to, $table, $tenant))) {
                    throw TenantNotMoved::verificationFailed($tenant, $target, $table->table);
                }
            }

            // The last step that can fail: once it returns, the tenant lives in the target.
            $store->place($tenant, $target);

            return $rows;
        } catch (Throwable $throwable) {
            $this->rollBack($store, $to, $tenant, $source, $target, $plan);

            throw $throwable instanceof TenantNotMoved ? $throwable : TenantNotMoved::because($tenant, $target, $throwable);
        }
    }

    /**
     * @param list<CopyTable> $plan
     */
    private function rollBack(PlacementStore $store, Connection $to, TenantId $tenant, Placement $source, Placement $target, array $plan): void
    {
        try {
            // A store that failed half-way may hold the target: the source is recorded again before anything else.
            $store->place($tenant, $source);
            $to->transactional(fn () => $this->delete($to, $plan, $tenant));
        } catch (Throwable $throwable) {
            $this->logger?->error('The move of the tenant "{tenant}" to "{to}" could not be rolled back entirely: {failure}.', ['tenant' => $tenant->value, 'to' => $target->toString(), 'failure' => $throwable::class]);
        }
    }

    private function copy(Connection $from, Connection $to, CopyTable $table, TenantId $tenant): int
    {
        $copied = 0;
        $insert = null;

        foreach ($from->iterateAssociative($table->select(), [$tenant->value]) as $row) {
            $insert ??= \sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $table->table,
                implode(', ', array_map($to->quoteSingleIdentifier(...), array_keys($row))),
                implode(', ', array_fill(0, \count($row), '?')),
            );
            $values = array_values($row);
            $to->executeStatement($insert, array_map($this->value(...), $values), array_map($this->type(...), $values));
            ++$copied;
        }

        return $copied;
    }

    /**
     * @param list<CopyTable> $plan
     */
    private function delete(Connection $connection, array $plan, TenantId $tenant): void
    {
        foreach (array_reverse($plan) as $table) {
            $connection->executeStatement($table->delete(), [$tenant->value]);
        }
    }

    /**
     * The rows of the tenant in a table, hashed in the order of its key.
     */
    private function digest(Connection $connection, CopyTable $table, TenantId $tenant): string
    {
        $hash = hash_init('sha256');

        foreach ($connection->iterateAssociative($table->select(), [$tenant->value]) as $row) {
            foreach ($row as $column => $value) {
                hash_update($hash, $this->canonical($column, $this->value($value)));
            }

            hash_update($hash, "\n");
        }

        return hash_final($hash);
    }

    /**
     * On PostgreSQL, a generated key draws on a sequence that the copied rows did not advance: it moves up to the
     * largest key, so that the next row inserted in the target does not collide, and never back.
     *
     * @param list<CopyTable> $plan
     */
    private function catchUpSequences(Connection $connection, array $plan): void
    {
        if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        foreach ($plan as $table) {
            if (null === $table->keyTable || null === $table->key) {
                continue;
            }

            $sequence = $connection->fetchOne('SELECT pg_get_serial_sequence(?, ?)', [$table->keyTable, $table->key]);

            if (!\is_string($sequence)) {
                continue;
            }

            $largest = $connection->fetchOne(\sprintf('SELECT MAX(%s) FROM %s', $table->key, $table->table));

            if (is_numeric($largest)) {
                // Never backwards: a target that already holds other rows may have drawn further.
                $connection->executeQuery('SELECT setval(?::regclass, GREATEST(?, COALESCE(pg_sequence_last_value(?::regclass), 0)))', [$sequence, (int) $largest, $sequence], [ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING]);
            }
        }
    }

    private function open(Placement $placement): Connection
    {
        return PinnedConnection::open($this->connection, $this->router, $placement);
    }

    /**
     * A value as it compares and binds: a stream — a binary column on PostgreSQL — read into a string.
     */
    private function value(mixed $value): mixed
    {
        return \is_resource($value) ? (string) stream_get_contents($value, offset: 0) : $value;
    }

    /**
     * A column and its value, each prefixed with its length, so that no two rows hash alike by shifting bytes from one
     * column to the next.
     */
    private function canonical(string $column, mixed $value): string
    {
        $text = match (true) {
            null === $value => 'N',
            \is_bool($value) => $value ? 'B1' : 'B0',
            \is_int($value) => 'I'.$value,
            \is_float($value) => 'F'.\sprintf('%.17g', $value),
            \is_string($value) => 'S'.$value,
            default => 'U'.get_debug_type($value),
        };

        return \strlen($column).':'.$column.\strlen($text).':'.$text;
    }

    private function type(mixed $value): ParameterType
    {
        return match (true) {
            null === $value => ParameterType::NULL,
            \is_int($value) => ParameterType::INTEGER,
            \is_bool($value) => ParameterType::BOOLEAN,
            \is_resource($value) => ParameterType::LARGE_OBJECT,
            default => ParameterType::STRING,
        };
    }
}
