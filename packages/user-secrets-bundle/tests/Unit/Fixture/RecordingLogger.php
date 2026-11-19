<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Keeps every record, its placeholders filled from its context.
 */
final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{mixed, string, array<array-key, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (\is_string($value)) {
                $replace['{'.$key.'}'] = $value;
            }
        }

        $this->records[] = [$level, strtr((string) $message, $replace), $context];
    }

    /**
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(static fn (array $record): string => (\is_string($record[0]) ? $record[0] : '?').': '.$record[1], $this->records);
    }
}
