<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Tests\Fixture;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * A logger that keeps what it is told, its placeholders replaced.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string}> */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $replace = [];

        foreach ($context as $key => $value) {
            $replace['{'.$key.'}'] = \is_scalar($value) ? (string) $value : '';
        }

        $this->records[] = ['level' => $level, 'message' => strtr((string) $message, $replace)];
    }
}
