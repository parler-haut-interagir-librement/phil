<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit\Fixture;

use Doctrine\DBAL\ParameterType;
use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * What DBAL's logging middleware says of a connection: each statement with the types its parameters were bound by,
 * and each transaction step.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $steps = [];

    /** @var list<array{string, list<ParameterType>}> */
    public array $statements = [];

    /**
     * @param array<array-key, mixed> $context
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        if (str_ends_with((string) $message, 'transaction')) {
            $this->steps[] = (string) $message;

            return;
        }

        $types = $context['types'] ?? null;
        $sql = $context['sql'] ?? null;

        if (\is_array($types) && \is_string($sql)) {
            ksort($types);
            $this->statements[] = [$sql, array_values(array_filter($types, static fn (mixed $type): bool => $type instanceof ParameterType))];
        }
    }

    /**
     * The types of the statements that start with a text, in the order they ran.
     *
     * @return list<list<ParameterType>>
     */
    public function typesOf(string $start): array
    {
        $found = [];

        foreach ($this->statements as [$sql, $types]) {
            if (str_starts_with($sql, $start)) {
                $found[] = $types;
            }
        }

        return $found;
    }
}
