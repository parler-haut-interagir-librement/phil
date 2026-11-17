<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Fixture;

use Closure;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Statement;
use Override;
use SensitiveParameter;

/**
 * Runs a statement of another writer just before the first statement that starts with a given text — `BEGIN` for the
 * start of a transaction —, on the same connection: the interleaving of two writes at once, made certain for a test.
 */
final class Interleaving implements Middleware
{
    private ?string $prefix = null;

    /** @var (Closure(Connection): void)|null */
    private ?Closure $write = null;

    /**
     * @param Closure(Connection): void $write
     */
    public function before(string $prefix, Closure $write): void
    {
        $this->prefix = $prefix;
        $this->write = $write;
    }

    /**
     * Runs the write once when the statement starts with the prefix.
     */
    public function meet(Connection $connection, string $sql): void
    {
        if (null === $this->prefix || !$this->write instanceof Closure || !str_starts_with($sql, $this->prefix)) {
            return;
        }

        $write = $this->write;
        $this->prefix = null;
        $this->write = null;
        $write($connection);
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        $interleaving = $this;

        return new class($driver, $interleaving) extends AbstractDriverMiddleware {
            public function __construct(
                Driver $driver,
                private readonly Interleaving $interleaving,
            ) {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                return new class(parent::connect($params), $this->interleaving) extends AbstractConnectionMiddleware {
                    public function __construct(
                        private readonly Connection $connection,
                        private readonly Interleaving $interleaving,
                    ) {
                        parent::__construct($connection);
                    }

                    #[Override]
                    public function prepare(string $sql): Statement
                    {
                        $this->interleaving->meet($this->connection, $sql);

                        return parent::prepare($sql);
                    }

                    #[Override]
                    public function beginTransaction(): void
                    {
                        $this->interleaving->meet($this->connection, 'BEGIN');

                        parent::beginTransaction();
                    }

                    #[Override]
                    public function exec(string $sql): int|string
                    {
                        $this->interleaving->meet($this->connection, $sql);

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
