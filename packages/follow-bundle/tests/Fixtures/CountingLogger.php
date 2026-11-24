<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Tests\Fixtures;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Counts the statements DBAL's logging middleware reports.
 */
final class CountingLogger extends AbstractLogger
{
    public int $statements = 0;

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (str_starts_with((string) $message, 'Executing')) {
            ++$this->statements;
        }
    }
}
