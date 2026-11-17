<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractResultMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Override;
use SensitiveParameter;

/**
 * Makes an UPDATE count no row it changes: what MySQL answers for a row written again as it is, since it counts the rows
 * it changes and not the rows it matches.
 */
final class MysqlRowCounts implements Middleware
{
    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                return new class(parent::connect($params)) extends AbstractConnectionMiddleware {
                    #[Override]
                    public function prepare(string $sql): Statement
                    {
                        $statement = parent::prepare($sql);

                        if (!str_starts_with($sql, 'UPDATE')) {
                            return $statement;
                        }

                        return new class($statement) extends AbstractStatementMiddleware {
                            #[Override]
                            public function execute(): Result
                            {
                                return new class(parent::execute()) extends AbstractResultMiddleware {
                                    #[Override]
                                    public function rowCount(): int
                                    {
                                        return 0;
                                    }
                                };
                            }
                        };
                    }
                };
            }
        };
    }
}
