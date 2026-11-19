<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Domain\ValueObject;

/**
 * The reactions of one source, under the translation key of the heading the picker shows them under.
 */
final readonly class ReactionGroup
{
    /**
     * @param list<ReactionDefinition> $definitions
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $definitions,
        public string $domain = ReactionDefinition::DEFAULT_DOMAIN,
    ) {
    }
}
