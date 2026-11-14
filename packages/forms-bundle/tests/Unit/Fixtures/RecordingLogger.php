<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Fixtures;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps each record as its level, message and context.
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
        $this->logged[] = \sprintf('%s %s %s', \is_string($level) ? $level : '', $message, json_encode($context, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }
}
