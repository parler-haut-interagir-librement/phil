<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps each record, its placeholders replaced.
 */
final class Logs extends AbstractLogger
{
    /** @var list<string> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (\is_scalar($value)) {
                $replace['{'.$key.'}'] = (string) $value;
            }
        }

        $this->records[] = (\is_string($level) ? $level : 'unknown').': '.strtr((string) $message, $replace);
    }
}
