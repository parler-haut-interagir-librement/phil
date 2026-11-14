<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use Override;
use SensitiveParameter;

/**
 * Keeps each prepared statement and the values bound to it, in order, so that a test reads what was sent.
 */
final class RecordingMiddleware implements Middleware
{
    /** @var list<array{string, list<mixed>}> */
    public array $statements = [];

    /** @var array<int, list<ParameterType>> the types bound to each statement of `$statements`, by its index */
    public array $types = [];

    /**
     * The types bound to the first statement recorded that starts with a text.
     *
     * @return list<ParameterType>
     */
    public function typesOf(string $prefix): array
    {
        foreach ($this->statements as $index => $statement) {
            if (str_starts_with($statement[0], $prefix)) {
                return $this->types[$index] ?? [];
            }
        }

        return [];
    }

    /**
     * The statements recorded that start with a text, with their values.
     *
     * @return list<array{string, list<mixed>}>
     */
    public function startingWith(string $prefix): array
    {
        return array_values(array_filter($this->statements, static fn (array $statement): bool => str_starts_with($statement[0], $prefix)));
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        $recording = $this;

        return new class($driver, $recording) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly RecordingMiddleware $recording)
            {
                parent::__construct($driver);
            }

            #[Override]
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                $recording = $this->recording;

                return new class(parent::connect($params), $recording) extends AbstractConnectionMiddleware {
                    public function __construct(Connection $connection, private readonly RecordingMiddleware $recording)
                    {
                        parent::__construct($connection);
                    }

                    #[Override]
                    public function prepare(string $sql): Statement
                    {
                        $this->recording->statements[] = [$sql, []];
                        $index = array_key_last($this->recording->statements);
                        $recording = $this->recording;

                        return new class(parent::prepare($sql), $recording, $index) extends AbstractStatementMiddleware {
                            public function __construct(Statement $statement, private readonly RecordingMiddleware $recording, private readonly int $index)
                            {
                                parent::__construct($statement);
                            }

                            #[Override]
                            public function bindValue(int|string $param, mixed $value, ParameterType $type): void
                            {
                                $this->recording->statements[$this->index][1][] = $value;
                                $this->recording->types[$this->index][] = $type;
                                parent::bindValue($param, $value, $type);
                            }

                            #[Override]
                            public function execute(): Result
                            {
                                return parent::execute();
                            }
                        };
                    }
                };
            }
        };
    }
}
