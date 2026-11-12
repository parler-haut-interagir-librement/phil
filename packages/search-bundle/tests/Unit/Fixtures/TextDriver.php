<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Fixtures;

use ArrayObject;
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
 * A driver that gives every number as text, as some do, and keeps the statements it is given.
 */
final readonly class TextDriver implements Middleware
{
    /** @var ArrayObject<int, string> */
    public ArrayObject $statements;

    public function __construct()
    {
        $this->statements = new ArrayObject();
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        $statements = $this->statements;

        return new class($driver, $statements) extends AbstractDriverMiddleware {
            /** @param ArrayObject<int, string> $statements */
            public function __construct(Driver $driver, private readonly ArrayObject $statements)
            {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                return new class(parent::connect($params), $this->statements) extends AbstractConnectionMiddleware {
                    /** @param ArrayObject<int, string> $statements */
                    public function __construct(Connection $connection, private readonly ArrayObject $statements)
                    {
                        parent::__construct($connection);
                    }

                    #[Override]
                    public function prepare(string $sql): Statement
                    {
                        $this->statements[] = $sql;

                        return new class(parent::prepare($sql)) extends AbstractStatementMiddleware {
                            #[Override]
                            public function execute(): Result
                            {
                                return TextDriver::asText(parent::execute());
                            }
                        };
                    }

                    #[Override]
                    public function query(string $sql): Result
                    {
                        $this->statements[] = $sql;

                        return TextDriver::asText(parent::query($sql));
                    }
                };
            }
        };
    }

    public static function asText(Result $result): Result
    {
        return new class($result) extends AbstractResultMiddleware {
            #[Override]
            public function fetchNumeric(): array|false
            {
                $row = parent::fetchNumeric();

                return false === $row ? false : array_map(TextDriver::text(...), $row);
            }

            #[Override]
            public function fetchAssociative(): array|false
            {
                $row = parent::fetchAssociative();

                return false === $row ? false : array_map(TextDriver::text(...), $row);
            }

            #[Override]
            public function fetchOne(): mixed
            {
                return TextDriver::text(parent::fetchOne());
            }

            #[Override]
            public function fetchAllNumeric(): array
            {
                return array_map(static fn (array $row): array => array_map(TextDriver::text(...), $row), parent::fetchAllNumeric());
            }

            #[Override]
            public function fetchAllAssociative(): array
            {
                return array_map(static fn (array $row): array => array_map(TextDriver::text(...), $row), parent::fetchAllAssociative());
            }

            #[Override]
            public function fetchFirstColumn(): array
            {
                return array_map(TextDriver::text(...), parent::fetchFirstColumn());
            }
        };
    }

    public static function text(mixed $value): mixed
    {
        return \is_int($value) || \is_float($value) ? (string) $value : $value;
    }
}
