<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The name of a feature flag: lowercase ASCII segments separated by `.`, `_` or `-`.
 *
 * The name is also the salt of the percentage bucket (`Bucket`), so renaming a flag reshuffles who is in
 * its rollout. A narrow alphabet keeps a name the same string in configuration, in the database and in an
 * audit line, with no case or Unicode normalization to disagree about.
 */
final readonly class FlagName
{
    public const int MAX_LENGTH = 100;

    private const string PATTERN = '/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D';

    private function __construct(
        public string $value,
    ) {
    }

    public static function of(string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw InvalidValue::because(\sprintf('A flag name is at most %d characters; this one has %d.', self::MAX_LENGTH, \strlen($value)));
        }

        if (1 !== preg_match(self::PATTERN, $value)) {
            throw InvalidValue::because(\sprintf('"%s" is not a flag name: use lowercase ASCII segments separated by ".", "_" or "-".', $value));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
