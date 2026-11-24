<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps each record: its level, its message and its context.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{mixed, string, array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}
