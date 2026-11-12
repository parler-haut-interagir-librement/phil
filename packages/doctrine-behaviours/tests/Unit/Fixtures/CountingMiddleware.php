<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Override;
use SensitiveParameter;

/**
 * Keeps the SQL a connection reads, so that a test counts the queries of a call.
 */
final class CountingMiddleware implements Middleware
{
    /** @var list<string> */
    private array $selects = [];

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        $middleware = $this;

        return new class($driver, $middleware) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly CountingMiddleware $middleware)
            {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                $middleware = $this->middleware;

                return new class(parent::connect($params), $middleware) extends AbstractConnectionMiddleware {
                    public function __construct(Connection $connection, private readonly CountingMiddleware $middleware)
                    {
                        parent::__construct($connection);
                    }

                    #[Override]
                    public function prepare(string $sql): Statement
                    {
                        $this->middleware->seen($sql);

                        return parent::prepare($sql);
                    }

                    #[Override]
                    public function query(string $sql): Result
                    {
                        $this->middleware->seen($sql);

                        return parent::query($sql);
                    }
                };
            }
        };
    }

    /**
     * @return list<string>
     */
    public function selects(): array
    {
        return $this->selects;
    }

    public function forget(): void
    {
        $this->selects = [];
    }

    public function seen(string $sql): void
    {
        if (str_starts_with(ltrim($sql), 'SELECT')) {
            $this->selects[] = $sql;
        }
    }
}
