<?php

declare(strict_types=1);

namespace Phil\Migration\Tests\Unit\Fixtures;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps each record as its level, its message and the context's reason.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $logged = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->logged[] = \sprintf('%s %s %s %s', \is_string($level) ? $level : '', $message, \is_string($context['transport'] ?? null) ? $context['transport'] : '', \is_string($context['reason'] ?? null) ? $context['reason'] : '');
    }
}
