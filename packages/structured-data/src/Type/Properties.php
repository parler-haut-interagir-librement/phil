<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

/**
 * @internal the free properties a factory is given, and its named ones over them: a named property left `null` keeps
 *           the free one of that name
 */
final class Properties
{
    /**
     * @param array<string, mixed> $free
     * @param array<string, mixed> $named
     *
     * @return array<string, mixed>
     */
    public static function merge(array $free, array $named): array
    {
        return [...$free, ...array_filter($named, static fn (mixed $value): bool => null !== $value && [] !== $value)];
    }
}
