<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Availability;

use Doctrine\DBAL\Connection;
use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreTables;

/**
 * The suspended databases and tenants, in the table {@see StoreTables::SUSPENSIONS} of the shared database: one row per
 * suspension, kept until it is resumed. A suspension survives a cleared cache, a deployment and a restart: a database
 * whose migration failed stays closed to its tenants until a migration succeeds.
 *
 * The default availability. The connection must stay on the shared database whatever the active tenant: by default, a
 * connection of its own pinned there. Each question is one query on the primary key, and nothing is kept between two:
 * a suspension recorded by one process is seen by the next request or message of every other.
 *
 * `kind` is `shared`, `database` or `tenant`; `subject` the database name or the tenant reference, `NULL` for the
 * shared database, which is never a dedicated database named `shared`.
 */
final readonly class DbalTenantDatabaseAvailability implements TenantDatabaseAvailability
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function suspendDatabase(Placement $database): void
    {
        $this->suspend(null === $database->database ? 'shared' : 'database', $database->database);
    }

    #[Override]
    public function resumeDatabase(Placement $database): void
    {
        $this->resume(null === $database->database ? 'shared' : 'database', $database->database);
    }

    #[Override]
    public function isDatabaseSuspended(Placement $database): bool
    {
        return $this->isSuspended(null === $database->database ? 'shared' : 'database', $database->database);
    }

    #[Override]
    public function suspendTenant(TenantId $tenant): void
    {
        $this->suspend('tenant', $tenant->value);
    }

    #[Override]
    public function resumeTenant(TenantId $tenant): void
    {
        $this->resume('tenant', $tenant->value);
    }

    #[Override]
    public function isTenantSuspended(TenantId $tenant): bool
    {
        return $this->isSuspended('tenant', $tenant->value);
    }

    private function suspend(string $kind, ?string $subject): void
    {
        StoreTables::upsert($this->connection, StoreTables::SUSPENSIONS, ['id' => $this->id($kind, $subject), 'kind' => $kind, 'subject' => $subject]);
    }

    private function resume(string $kind, ?string $subject): void
    {
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE id = ?', StoreTables::SUSPENSIONS), [$this->id($kind, $subject)]);
    }

    private function isSuspended(string $kind, ?string $subject): bool
    {
        return false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE id = ?', StoreTables::SUSPENSIONS), [$this->id($kind, $subject)]);
    }

    private function id(string $kind, ?string $subject): string
    {
        return hash('sha256', null === $subject ? $kind : $kind.':'.$subject);
    }
}
