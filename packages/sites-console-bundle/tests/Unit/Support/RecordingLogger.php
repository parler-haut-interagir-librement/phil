<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps each line, its placeholders replaced by the strings of the context.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $lines = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $replacements = [];

        foreach ($context as $key => $value) {
            if (\is_string($value)) {
                $replacements['{'.$key.'}'] = $value;
            }
        }

        $this->lines[] = (\is_string($level) ? $level : '').': '.strtr((string) $message, $replacements);
    }
}
