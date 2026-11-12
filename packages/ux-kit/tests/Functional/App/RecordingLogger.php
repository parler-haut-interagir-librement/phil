<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional\App;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * The logger of the test application: it keeps each record, its level and its message, for the tests to read.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{string, string, array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [\is_string($level) ? $level : '', (string) $message, $context];
    }
}
