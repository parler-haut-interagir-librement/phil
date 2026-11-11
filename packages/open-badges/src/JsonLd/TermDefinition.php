<?php

declare(strict_types=1);

namespace Phil\OpenBadges\JsonLd;

/**
 * A term of an active context: the IRI it expands to — a keyword for an alias, null for a term mapped to nothing —,
 * the type its values are coerced to, its container, its scoped context, and whether it serves as a prefix.
 *
 * @internal
 */
final readonly class TermDefinition
{
    public function __construct(
        public ?string $iri,
        public ?string $type,
        public ?string $container,
        public bool $hasContext,
        public mixed $context,
        public bool $prefix,
    ) {
    }
}
