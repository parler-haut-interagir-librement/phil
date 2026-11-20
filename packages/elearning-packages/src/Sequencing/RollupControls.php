<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * How an activity counts in the rollup of its parent, `<imsss:rollupRules>` and `<adlseq:rollupConsiderations>`:
 * whether its satisfaction and its completion count, the weight of its measure from 0 to 1, when it counts for each
 * rollup action, and whether the measure of an activity still active decides its satisfaction.
 */
final readonly class RollupControls
{
    public function __construct(
        public bool $objectiveSatisfied = true,
        public bool $progressCompletion = true,
        public float $measureWeight = 1.0,
        public Requirement $requiredForSatisfied = Requirement::Always,
        public Requirement $requiredForNotSatisfied = Requirement::Always,
        public Requirement $requiredForCompleted = Requirement::Always,
        public Requirement $requiredForIncomplete = Requirement::Always,
        public bool $measureSatisfactionIfActive = true,
    ) {
    }

    public function requiredFor(RollupAction $action): Requirement
    {
        return match ($action) {
            RollupAction::Satisfied => $this->requiredForSatisfied,
            RollupAction::NotSatisfied => $this->requiredForNotSatisfied,
            RollupAction::Completed => $this->requiredForCompleted,
            RollupAction::Incomplete => $this->requiredForIncomplete,
        };
    }
}
