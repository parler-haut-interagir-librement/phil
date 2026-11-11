<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;

/**
 * Combinations a template does not make: the supplier has no such blank. An exclusion names values of one axis or
 * several, and excludes every combination that has one of the listed values on each axis it names — `colour` `PK`
 * with `size` `4X` and `5X` excludes pink in the two largest sizes, whatever the other axes.
 */
final readonly class Exclusion
{
    /**
     * @param non-empty-array<string, list<string>> $values value codes by axis code
     */
    private function __construct(
        public array $values,
    ) {
    }

    /**
     * @param array<string, list<string>> $values value codes by axis code, one axis at least
     */
    public static function of(array $values): self
    {
        $condition = Combination::condition($values, 'An exclusion');

        if ([] === $condition) {
            throw InvalidDefinition::because('An exclusion names one axis at least.');
        }

        return new self($condition);
    }

    public function excludes(Combination $combination): bool
    {
        return $combination->matches($this->values);
    }
}
