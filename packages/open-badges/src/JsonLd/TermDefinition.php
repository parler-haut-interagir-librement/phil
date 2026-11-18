<?php

declare(strict_types=1);

namespace Phil\OpenBadges\JsonLd;

/**
 * A term of an active context: the IRI it expands to — a keyword for an alias, null for a term mapped to nothing —,
 * the type its values are coerced to, its container, its scoped context, whether it serves as a prefix, and whether it
 * is protected from redefinition.
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
        public bool $protected = false,
    ) {
    }

    /**
     * Whether the two definitions are the same but for their protection: a protected term may be defined again only so.
     */
    public function sameAs(self $other): bool
    {
        return $this->iri === $other->iri
            && $this->type === $other->type
            && $this->container === $other->container
            && $this->hasContext === $other->hasContext
            && $this->context === $other->context
            && $this->prefix === $other->prefix;
    }
}
