<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Unit\Fixtures;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps each record as its level, message and reason.
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
        $reason = $context['reason'] ?? '';
        $this->logged[] = \sprintf('%s %s %s', \is_string($level) ? $level : '', $message, \is_string($reason) ? $reason : '');
    }
}
