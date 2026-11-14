<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps each log line as its message, placeholders unreplaced, and its context.
 */
final class ContextLogger extends AbstractLogger
{
    /**
     * @var list<array{string, array<mixed>}>
     */
    public array $lines = [];

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->lines[] = [(string) $message, $context];
    }
}
