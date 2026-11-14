<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\DriverManager;
use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantCredentials;
use Phil\TenancyDatabaseBundle\Domain\ValueObject\DatabaseCredentials;
use SensitiveParameter;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The database the managed connection opens on: the shared one, as configured, until a tenant with a database of its
 * own is entered. The parameters are rewritten as the connection opens, never when the container compiles: one
 * connection service serves every tenant, and a new tenant needs no deployment.
 *
 * A dedicated database keeps the server and the account of the shared connection, with its own name, unless the
 * {@see TenantCredentials} give another account, host or port. A primary and its replicas reach the driver one node at
 * a time, each with its own parameters: each opens on the tenant's database.
 *
 * A connection opened for one database whatever the active tenant — the source and the target of a move — carries
 * {@see self::PINNED} among its driver options, and is left as it is.
 *
 * @phpstan-import-type Params from DriverManager
 */
final class TenantDatabaseRouter implements ResetInterface
{
    /**
     * The driver option that pins a connection to the database of its parameters; removed before the driver reads
     * its options.
     */
    public const string PINNED = 'phil_tenancy_database.pinned';

    private Placement $current;

    public function __construct(
        private readonly ?TenantCredentials $credentials = null,
    ) {
        $this->current = Placement::shared();
    }

    public function current(): Placement
    {
        return $this->current;
    }

    /**
     * The database the next connection opens on; the caller closes the open one first.
     */
    public function route(Placement $placement): void
    {
        $this->current = $placement;
    }

    /**
     * The parameters a connection opens with, from those of the shared connection.
     *
     * @param Params $parameters
     *
     * @return Params
     */
    public function parameters(#[SensitiveParameter] array $parameters): array
    {
        if (true === ($parameters['driverOptions'][self::PINNED] ?? false)) {
            unset($parameters['driverOptions'][self::PINNED]);

            return $parameters;
        }

        return $this->parametersFor($this->current, $parameters);
    }

    /**
     * The parameters of a connection pinned to one database, whatever the active tenant.
     *
     * @param Params $parameters the parameters of the shared connection
     *
     * @return Params
     */
    public function pinned(Placement $placement, #[SensitiveParameter] array $parameters): array
    {
        $parameters = $this->parametersFor($placement, $parameters);
        $parameters['driverOptions'][self::PINNED] = true;

        return $parameters;
    }

    #[Override]
    public function reset(): void
    {
        $this->current = Placement::shared();
    }

    /**
     * @param Params $parameters
     *
     * @return Params
     */
    private function parametersFor(Placement $placement, #[SensitiveParameter] array $parameters): array
    {
        $database = $placement->database;

        if (null === $database) {
            return $parameters;
        }

        $parameters['dbname'] = $database;
        $credentials = $this->credentials?->credentialsFor($database);

        if (!$credentials instanceof DatabaseCredentials) {
            return $parameters;
        }

        $parameters['user'] = $credentials->user;
        $parameters['password'] = $credentials->password;

        if (null !== $credentials->host) {
            $parameters['host'] = $credentials->host;
        }

        if (null !== $credentials->port) {
            $parameters['port'] = $credentials->port;
        }

        return $parameters;
    }
}
