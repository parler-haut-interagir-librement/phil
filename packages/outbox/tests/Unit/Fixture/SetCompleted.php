<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit\Fixture;

/**
 * The event of a package, rebuilt from a record.
 */
final readonly class SetCompleted
{
    public function __construct(
        public string $recordId,
        public string $set,
        public int $holder,
    ) {
    }
}
