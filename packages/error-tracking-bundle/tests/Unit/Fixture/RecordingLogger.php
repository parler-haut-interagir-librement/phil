<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Psr\Log\AbstractLogger;
use Stringable;

final class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{mixed, string, array<array-key, mixed>}>
     */
    public array $records = [];

    /**
     * @var ?Closure(): void
     */
    public ?Closure $whileLogging = null;

    public function count(): int
    {
        return \count($this->records);
    }

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];

        if ($this->whileLogging instanceof Closure) {
            ($this->whileLogging)();
        }
    }
}
