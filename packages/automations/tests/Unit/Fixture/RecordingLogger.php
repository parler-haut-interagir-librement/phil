<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Fixture;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps what it is given.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => \is_string($level) ? $level : '', 'message' => (string) $message, 'context' => $context];
    }
}
