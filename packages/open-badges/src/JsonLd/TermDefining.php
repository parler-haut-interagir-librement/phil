<?php

declare(strict_types=1);

namespace Phil\OpenBadges\JsonLd;

/**
 * The state of one local context while its terms are defined: the context, the terms so far, the vocabulary mapping,
 * whether its terms are protected unless they say otherwise, whether it may redefine protected terms — a
 * property-scoped context may —, and which terms are defined (`true`) or being defined (`false`), to find a term
 * defined through itself.
 *
 * @internal
 */
final class TermDefining
{
    /**
     * @var array<string, bool>
     */
    public array $defined = [];

    /**
     * @param array<array-key, mixed>            $context
     * @param array<string, TermDefinition|null> $terms
     */
    public function __construct(
        public readonly array $context,
        public array $terms,
        public readonly ?string $vocab,
        public readonly bool $protected = false,
        public readonly bool $overrideProtected = false,
    ) {
    }
}
