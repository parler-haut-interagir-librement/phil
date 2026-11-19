<?php

declare(strict_types=1);

namespace Phil\ProposalReview\Tests\Unit\Fixture;

use Closure;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;
use SensitiveParameter;

/**
 * Another request that writes just before each transaction the stores start, a given number of times, outside that
 * transaction: two writes at once, made certain for a test.
 */
final class RacingWriter implements Middleware
{
    /** @var (Closure(Connection): void)|null */
    private ?Closure $write = null;

    private int $times = 0;

    /**
     * @param Closure(Connection): void $write
     */
    public function beforeTransactions(int $times, Closure $write): void
    {
        $this->times = $times;
        $this->write = $write;
    }

    public function race(Connection $connection): void
    {
        if ($this->times < 1 || !$this->write instanceof Closure) {
            return;
        }

        --$this->times;
        ($this->write)($connection);
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this) extends AbstractDriverMiddleware {
            public function __construct(
                Driver $driver,
                private readonly RacingWriter $writer,
            ) {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                return new class(parent::connect($params), $this->writer) extends AbstractConnectionMiddleware {
                    public function __construct(
                        private readonly Connection $connection,
                        private readonly RacingWriter $writer,
                    ) {
                        parent::__construct($connection);
                    }

                    #[Override]
                    public function beginTransaction(): void
                    {
                        $this->writer->race($this->connection);

                        parent::beginTransaction();
                    }
                };
            }
        };
    }
}
