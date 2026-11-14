<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Subject;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * The classification of a personal field: `email`, `person_name`, `ip`, and whatever else the application's
 * vocabulary names.
 *
 * **The vocabulary belongs to the application**, so the bundle checks the shape and not the list: a lowercase
 * `snake_case` token, short enough to be a column value. A free-text classification cannot be grouped, counted
 * or matched against a retention rule, which is the whole point of declaring one.
 */
final readonly class DataCategory
{
    public const int MAX_LENGTH = 64;

    private function __construct(
        public string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (\strlen($value) > self::MAX_LENGTH || 1 !== preg_match('/^[a-z][a-z0-9_]*$/D', $value)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not a data category: expected a lowercase snake_case token of at most %d characters.', $value, self::MAX_LENGTH));
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
