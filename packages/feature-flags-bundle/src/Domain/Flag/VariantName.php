<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The name of a variant of an experiment: `control`, `treatment`, `two-columns`. The alphabet of a flag name, so
 * that a variant reads the same in configuration, in the database, in a template and in a counter.
 */
final readonly class VariantName
{
    public const int MAX_LENGTH = 50;

    private const string PATTERN = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D';

    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A variant name is at most %d characters; this one has %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidValue::because(\sprintf('"%s" is not a variant name: use lowercase ASCII segments separated by ".", "_" or "-".', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
