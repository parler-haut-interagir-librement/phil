<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use WeakMap;

/**
 * Sets the active tenant in the session variable of every PostgreSQL connection the middleware hands it: on each
 * connection as it opens, then on every open one each time a tenant is entered or left. The policies of row-level
 * security compare each row to that variable.
 *
 * **Fail closed.** A connection opens with the variable unset, and leaving a tenant empties it: the policies then
 * match no row. The variable is set for the session, with `set_config(…, false)`: a connection pooler in
 * transaction mode would hand it to another client, so the connection goes straight to PostgreSQL, or through a pooler
 * in session mode.
 *
 * The connections are held weakly: one that is closed and dropped is forgotten. The active tenant is the state of the
 * unit of work, and `TenantContext::leave()`, at each kernel reset, empties it.
 */
final class TenantSession implements TenantActivation
{
    /** @var WeakMap<Connection, true> */
    private WeakMap $connections;

    private ?TenantId $tenant = null;

    public function __construct(
        private readonly RowLevelSecurityConfig $config,
    ) {
        $this->connections = new WeakMap();
    }

    /**
     * A connection that has just opened: it takes the active tenant, if there is one.
     */
    public function attach(Connection $connection): void
    {
        $this->connections[$connection] = true;

        if ($this->tenant instanceof TenantId) {
            $this->apply($connection, $this->tenant->value);
        }
    }

    #[Override]
    public function activate(TenantId $tenant): void
    {
        $this->tenant = $tenant;

        foreach ($this->connections as $connection => $attached) {
            $this->apply($connection, $tenant->value);
        }
    }

    #[Override]
    public function deactivate(): void
    {
        $this->tenant = null;

        foreach ($this->connections as $connection => $attached) {
            $this->apply($connection, '');
        }
    }

    private function apply(Connection $connection, string $value): void
    {
        // Bound, never interpolated: a tenant reference shaped like SQL is only an unknown tenant.
        $statement = $connection->prepare('SELECT set_config(?, ?, false)');
        $statement->bindValue(1, $this->config->setting, ParameterType::STRING);
        $statement->bindValue(2, $value, ParameterType::STRING);
        $statement->execute()->free();
    }
}
