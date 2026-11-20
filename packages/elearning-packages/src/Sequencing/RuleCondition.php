<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * One condition of a rule: what it tests, on which objective — the primary one when none is named —, the threshold of
 * a measure, and whether its answer is negated.
 */
final readonly class RuleCondition
{
    public function __construct(
        public ConditionType $type,
        public bool $not = false,
        public ?string $referencedObjective = null,
        public float $measureThreshold = 0.0,
    ) {
    }
}
