<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Fixtures;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps what is logged, for the tests.
 */
final class TestLogger extends AbstractLogger
{
    /** @var list<array{mixed, string|Stringable, array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, $message, $context];
    }
}
