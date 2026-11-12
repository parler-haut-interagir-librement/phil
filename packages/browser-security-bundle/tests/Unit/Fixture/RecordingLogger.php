<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Fixture;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps what it is given, for a test to read.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{mixed, string|Stringable, array<array-key, mixed>}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, $message, $context];
    }
}
