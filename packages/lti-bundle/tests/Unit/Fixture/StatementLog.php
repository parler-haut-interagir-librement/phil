<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Logging\Middleware;
use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * The statements a connection runs, in order, for a test that counts them.
 */
final class StatementLog extends AbstractLogger
{
    /** @var list<string> */
    public array $statements = [];

    public function middleware(): Middleware
    {
        return new Middleware($this);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $sql = $context['sql'] ?? null;

        if (\is_string($sql)) {
            $this->statements[] = $sql;
        }
    }

    /**
     * How many statements begin with a text.
     */
    public function count(string $prefix): int
    {
        return \count(array_filter($this->statements, static fn (string $sql): bool => str_starts_with($sql, $prefix)));
    }
}
