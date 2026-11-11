<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Flag;

use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;

/**
 * One flag as the catalog shows it: the definition its provider declares, the provider it comes from, and the
 * active state the store holds for it, if any. What the evaluator applies is {@see self::effective()}.
 */
final readonly class CatalogEntry
{
    public function __construct(
        public FlagDefinition $declared,
        public string $source,
        public ?bool $stored,
    ) {
    }

    /**
     * The definition with the stored state applied, as the evaluator reads it.
     */
    public function effective(): FlagDefinition
    {
        return null === $this->stored ? $this->declared : $this->declared->withActive($this->stored);
    }
}
