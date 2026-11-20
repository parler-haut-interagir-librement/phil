<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Domain\ValueObject;

/**
 * What a generation did to the catalogue, by SKU: the products it created, those it changed, those it restored after
 * a withdrawal, those it left as they were, and those it withdrew.
 */
final readonly class GenerationReport
{
    /**
     * @param list<string> $created
     * @param list<string> $updated
     * @param list<string> $restored
     * @param list<string> $unchanged
     * @param list<string> $withdrawn
     */
    public function __construct(
        public array $created = [],
        public array $updated = [],
        public array $restored = [],
        public array $unchanged = [],
        public array $withdrawn = [],
    ) {
    }

    /**
     * Whether the catalogue is as it was: nothing created, changed, restored nor withdrawn.
     */
    public function changedNothing(): bool
    {
        return [] === $this->created && [] === $this->updated && [] === $this->restored && [] === $this->withdrawn;
    }
}
