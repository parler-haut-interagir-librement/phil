<?php

declare(strict_types=1);

namespace Phil\Rules\Type\BuiltIn;

use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;

/**
 * What the built-in types share: reading the named fact, and comparing scalars as a rule means them.
 *
 * @internal
 */
final class Read
{
    private function __construct()
    {
    }

    /**
     * The fact, or the failing verdict when the scope does not hold it. The details name the fact, never its value,
     * so that a trace may be logged.
     */
    public static function fact(FactSource $facts, string $name): mixed
    {
        return $facts->has($name) ? $facts->get($name) : Verdict::fail('fact.missing', ['fact' => $name]);
    }

    public static function wrongType(string $name, string $expected): Verdict
    {
        return Verdict::fail('fact.type', ['fact' => $name, 'expected' => $expected]);
    }

    /**
     * Two scalars are the same when they are identical, or when both are numbers of the same value: 2 and 2.0 alike.
     */
    public static function same(mixed $fact, string|int|float|bool $value): bool
    {
        if (\is_int($fact) && \is_int($value)) {
            return $fact === $value;
        }

        if ((\is_int($fact) || \is_float($fact)) && (\is_int($value) || \is_float($value))) {
            return (float) $fact === (float) $value;
        }

        return $fact === $value;
    }
}
