<?php

declare(strict_types=1);

namespace Phil\StructuredData\Validation;

/**
 * What a node of one type needs and should have. Each entry is a property, or a list of properties of which one is
 * enough — a place needs a name or an address. A type with a parent needs what its parent needs too: a `LocalBusiness`
 * is an `Organization`.
 */
final readonly class TypeRules
{
    /**
     * @param list<string|list<string>> $required
     * @param list<string|list<string>> $recommended
     */
    public function __construct(
        public string $type,
        public array $required = [],
        public array $recommended = [],
        public ?string $parent = null,
    ) {
    }
}
