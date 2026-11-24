<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Support;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Override;
use SensitiveParameter;

/**
 * A driver middleware that keeps every statement a connection prepares and, when asked, tells the connection it talks
 * to PostgreSQL: SQLite runs the `INSERT … ON CONFLICT (…) DO NOTHING` of PostgreSQL as written, so the statements of
 * that platform run in memory.
 */
final class SqlRecorder implements Middleware
{
    /** @var list<string> */
    public array $prepared = [];

    public function __construct(
        private readonly bool $postgreSql = false,
    ) {
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this, $this->postgreSql) extends AbstractDriverMiddleware {
            public function __construct(
                Driver $driver,
                private readonly SqlRecorder $recorder,
                private readonly bool $postgreSql,
            ) {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(#[SensitiveParameter] array $params): DriverConnection
            {
                return new class(parent::connect($params), $this->recorder) extends AbstractConnectionMiddleware {
                    public function __construct(
                        DriverConnection $connection,
                        private readonly SqlRecorder $recorder,
                    ) {
                        parent::__construct($connection);
                    }

                    #[Override]
                    public function prepare(string $sql): Statement
                    {
                        $this->recorder->prepared[] = $sql;

                        return parent::prepare($sql);
                    }
                };
            }

            #[Override]
            public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
            {
                return $this->postgreSql ? new PostgreSQLPlatform() : parent::getDatabasePlatform($versionProvider);
            }
        };
    }

    /**
     * The statements prepared that start with the given word.
     *
     * @return list<string>
     */
    public function startingWith(string $word): array
    {
        return array_values(array_filter($this->prepared, static fn (string $sql): bool => str_starts_with($sql, $word)));
    }
}
