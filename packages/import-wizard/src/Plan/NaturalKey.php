<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

/**
 * The values that identify a record of an importable type — an email address, a code, a person and a course —, as
 * text: a date as `YYYY-MM-DD`, yes and no as `1` and `0`.
 */
final readonly class NaturalKey
{
    /**
     * @param list<string> $parts in the order of the profile's natural key
     */
    public function __construct(public array $parts)
    {
    }

    /**
     * One text for the whole key, the same for the same parts and different otherwise.
     */
    public function toString(): string
    {
        return json_encode($this->parts, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    public function equals(self $other): bool
    {
        return $this->parts === $other->parts;
    }
}
