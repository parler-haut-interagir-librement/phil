<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Flag;

/**
 * One flag as the catalog shows it when its declaring provider holds it but cannot read its definition: the name
 * as stored, the reason the provider gives, and the provider it comes from. The evaluator answers `PARSE_ERROR`
 * for it.
 */
final readonly class UnreadableEntry
{
    public function __construct(
        public string $name,
        public string $reason,
        public string $source,
    ) {
    }
}
