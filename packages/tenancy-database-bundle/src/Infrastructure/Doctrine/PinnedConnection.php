<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\TenancyBundle\Domain\Placement\Placement;

/**
 * A connection of its own, opened with the parameters and the configuration of the managed connection, pinned to one
 * database whatever the active tenant: the source and the target of a move, and the shared database the stored
 * placements and suspensions are read from. A primary and its replicas give the primary's parameters: what is read
 * there is read where it was written.
 *
 * The connection opens at its first query, never when it is built.
 *
 * @phpstan-import-type Params from DriverManager
 */
final class PinnedConnection
{
    private function __construct()
    {
    }

    public static function open(Connection $managed, TenantDatabaseRouter $router, Placement $placement): Connection
    {
        $parameters = $managed->getParams();

        $primary = $parameters['primary'] ?? null;
        unset($parameters['primary'], $parameters['replica']);

        /** @var Params $parameters */
        $parameters = \is_array($primary) ? array_replace($parameters, $primary) : $parameters;

        return DriverManager::getConnection($router->pinned($placement, $parameters), $managed->getConfiguration());
    }

    /**
     * The shared database, for the stores that every tenant reads: the factory of the bundle's store connection.
     */
    public static function shared(Connection $managed, TenantDatabaseRouter $router): Connection
    {
        return self::open($managed, $router, Placement::shared());
    }
}
