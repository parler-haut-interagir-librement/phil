<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Infrastructure\Doctrine;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Override;
use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Model\LeasedResource;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use Throwable;

/**
 * The leases in the table `phil_edit_lease`, by plain queries on a Doctrine connection: an insertion that meets the
 * primary key of a lease kept meanwhile fails, a replacement is an `UPDATE` on the version read, so that a race is
 * decided by the database. A claim runs in a transaction that first updates the resource's row of
 * `phil_edit_lease_resource`, inserting it the first time: the database holds the other claims on the resource until
 * it commits. A failed write leaves the entity manager open. Times are kept in UTC.
 */
final readonly class DoctrineLeaseRepository implements LeaseRepository
{
    private const string FORMAT = 'Y-m-d H:i:s';

    /** PostgreSQL's `lock_not_available`, when `lock_timeout` ends a wait. */
    private const string LOCK_NOT_AVAILABLE = '55P03';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function exclusively(string $resource, Closure $claim): mixed
    {
        $this->connection->beginTransaction();

        try {
            $this->lock($resource);
            $result = $claim();
        } catch (Throwable $throwable) {
            $this->connection->rollBack();

            return $this->gaveUp($throwable) ? null : throw $throwable;
        }

        $this->connection->commit();

        return $result;
    }

    #[Override]
    public function insert(Lease $lease): bool
    {
        try {
            $this->connection->insert(Lease::TABLE, [
                'resource' => $lease->key()->resource,
                'part' => $lease->key()->part ?? '',
                'version' => $lease->version,
                ...$this->holding($lease),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function replace(Lease $read, Lease $next): bool
    {
        return 1 === $this->connection->update(Lease::TABLE, ['version' => $next->version, ...$this->holding($next)], [
            'resource' => $next->key()->resource,
            'part' => $next->key()->part ?? '',
            'version' => $read->version,
        ]);
    }

    #[Override]
    public function delete(LeaseKey $key, string $holderId): bool
    {
        return 1 === $this->connection->delete(Lease::TABLE, ['resource' => $key->resource, 'part' => $key->part ?? '', 'holder_id' => $holderId]);
    }

    #[Override]
    public function get(LeaseKey $key): ?Lease
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM '.Lease::TABLE.' WHERE resource = ? AND part = ?',
            [$key->resource, $key->part ?? ''],
        );

        return false === $row ? null : $this->lease($row);
    }

    #[Override]
    public function ofResource(string $resource): array
    {
        return array_map($this->lease(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.Lease::TABLE.' WHERE resource = ? ORDER BY part',
            [$resource],
        ));
    }

    #[Override]
    public function deleteExpired(DateTimeImmutable $now): int
    {
        $deleted = $this->connection->executeStatement('DELETE FROM '.Lease::TABLE.' WHERE expires_at <= ?', [$this->utc($now)]);

        // DBAL counts in a string only beyond PHP's integers.
        return \is_int($deleted) ? $deleted : \PHP_INT_MAX;
    }

    /**
     * Locks the resource's row until the transaction ends, inserting it when no claim on the resource ran before; two
     * first claims racing to insert it are told apart by its primary key.
     */
    private function lock(string $resource): void
    {
        if (0 === $this->connection->executeStatement('UPDATE '.LeasedResource::TABLE.' SET claims = claims + 1 WHERE resource = ?', [$resource])) {
            $this->connection->insert(LeasedResource::TABLE, ['resource' => $resource, 'claims' => 1]);
        }
    }

    /**
     * Whether the database ended the claim for another one: a deadlock or a wait that timed out, or the resource's row
     * inserted meanwhile by a first claim.
     */
    private function gaveUp(Throwable $throwable): bool
    {
        return $throwable instanceof RetryableException
            || $throwable instanceof UniqueConstraintViolationException
            || ($throwable instanceof DriverException && self::LOCK_NOT_AVAILABLE === $throwable->getSQLState());
    }

    /**
     * @return array{holder_id: string, holder_name: string, acquired_at: string, expires_at: string}
     */
    private function holding(Lease $lease): array
    {
        return [
            'holder_id' => $lease->holder()->id,
            'holder_name' => $lease->holder()->name,
            'acquired_at' => $this->utc($lease->acquiredAt()),
            'expires_at' => $this->utc($lease->expiresAt()),
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws InvalidLease when the row does not hold a lease
     */
    private function lease(array $row): Lease
    {
        $part = $this->text($row, 'part');
        $version = filter_var($row['version'] ?? null, \FILTER_VALIDATE_INT);

        if (false === $version) {
            throw InvalidLease::because('The version of a stored lease is a number.');
        }

        return Lease::restore(
            LeaseKey::of($this->text($row, 'resource'), '' === $part ? null : $part),
            Holder::of($this->text($row, 'holder_id'), $this->text($row, 'holder_name')),
            $this->moment($row, 'acquired_at'),
            $this->moment($row, 'expires_at'),
            $version,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $column): string
    {
        $value = $row[$column] ?? null;

        if (!\is_string($value)) {
            throw InvalidLease::because(\sprintf('The column "%s" of a stored lease is a text.', $column));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function moment(array $row, string $column): DateTimeImmutable
    {
        return new DateTimeImmutable($this->text($row, $column), new DateTimeZone('UTC'));
    }

    private function utc(DateTimeImmutable $moment): string
    {
        return $moment->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }
}
