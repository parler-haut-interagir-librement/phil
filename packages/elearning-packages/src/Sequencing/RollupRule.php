<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * A rollup rule: the share of the contributing children that must meet its conditions — all, any, none, at least a
 * count or a fraction from 0 to 1 —, its conditions, whether all or any must hold on a child, and its action.
 */
final readonly class RollupRule
{
    /**
     * @param non-empty-list<RuleCondition> $conditions
     */
    public function __construct(
        public array $conditions,
        public RollupAction $action,
        public ChildActivitySet $childActivitySet = ChildActivitySet::All,
        public int $minimumCount = 0,
        public float $minimumPercent = 0.0,
        public bool $requireAll = false,
    ) {
    }
}
