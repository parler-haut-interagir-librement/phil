<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A dimension a site adds to its counters, with its closed list of values: the variant of an experiment, a plan, a
 * language.
 *
 * Whatever reads the value — a feature flag, a setting — stays outside: the page hands the value to the collection
 * script, and only a declared dimension and a declared value reach a counter.
 */
final readonly class DeclaredDimension
{
    private const array RESERVED = ['country', 'device', 'route', 'source'];

    private function __construct(
        public string $name,
        public ClosedList $values,
    ) {
    }

    public static function of(string $name, ClosedList $values): self
    {
        if (1 !== preg_match(CounterKey::NAME_PATTERN, $name)) {
            throw InvalidValue::because(\sprintf('"%s" is not a dimension name: 1 to 64 characters among a-z, 0-9, "_", "." and "-", starting with a letter.', $name));
        }

        if (\in_array($name, self::RESERVED, true)) {
            throw InvalidValue::because(\sprintf('The dimension "%s" is the bundle\'s own.', $name));
        }

        return new self($name, $values);
    }
}
