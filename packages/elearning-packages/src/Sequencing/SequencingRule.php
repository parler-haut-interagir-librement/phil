<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * A sequencing rule: its conditions, whether all of them or any must hold, and its action.
 */
final readonly class SequencingRule
{
    /**
     * @param non-empty-list<RuleCondition> $conditions
     */
    public function __construct(
        public array $conditions,
        public RuleAction $action,
        public bool $requireAll = true,
    ) {
    }
}
