<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Tests\Unit\Support;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * The SQL a connection runs, as the DBAL logging middleware reports it.
 */
final class QueryLog extends AbstractLogger
{
    /** @var list<string> */
    private array $sql = [];

    /**
     * @param array<mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $sql = $context['sql'] ?? null;

        if (\is_string($sql)) {
            $this->sql[] = $sql;
        }
    }

    public function clear(): void
    {
        $this->sql = [];
    }

    /**
     * How many statements named that table.
     */
    public function touching(string $table): int
    {
        return \count(array_filter($this->sql, static fn (string $sql): bool => str_contains($sql, $table)));
    }
}
