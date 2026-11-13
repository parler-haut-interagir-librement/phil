<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Dbal;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Override;
use Phil\Automations\Tests\Unit\Fixture\RecordingLogger;

/**
 * SQLite connections that tell what they run: the SQL and the types of each statement, and, for one, the dialect of
 * another server.
 */
final class SpiedConnection
{
    public static function logging(RecordingLogger $logger): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], new Configuration()->setMiddlewares([new LoggingMiddleware($logger)]));
    }

    /**
     * Two connections on one file: the first plain, to build the schema, the second one that says it runs on PostgreSQL.
     *
     * @return array{Connection, Connection}
     */
    public static function postgresOverFile(string $path): array
    {
        $plain = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $postgres = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path], new Configuration()->setMiddlewares([new class implements Middleware {
            #[Override]
            public function wrap(Driver $driver): Driver
            {
                return new class($driver) extends AbstractDriverMiddleware {
                    #[Override]
                    public function getDatabasePlatform(ServerVersionProvider $versionProvider): PostgreSQLPlatform
                    {
                        return new PostgreSQLPlatform();
                    }
                };
            }
        }]));

        return [$plain, $postgres];
    }
}
