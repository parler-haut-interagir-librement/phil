<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The sequencing of an activity, `<imsss:sequencing>` with what a `<imsss:sequencingCollection>` entry it names adds:
 * its control modes, its sequencing rules by timing, its limits, its rollup rules and controls, its objectives, the
 * selection and reordering of its children, and its delivery controls. An activity without sequencing has the
 * defaults of IMS Simple Sequencing.
 */
final readonly class Sequencing
{
    /**
     * @param list<SequencingRule> $preConditionRules
     * @param list<SequencingRule> $exitConditionRules
     * @param list<SequencingRule> $postConditionRules
     * @param list<RollupRule>     $rollupRules
     * @param list<Objective>      $objectives         the objectives, the primary one first; one primary objective
     *                                                 without identifier when the manifest declares none
     */
    public function __construct(
        public ControlMode $controlMode = new ControlMode(),
        public array $preConditionRules = [],
        public array $exitConditionRules = [],
        public array $postConditionRules = [],
        public LimitConditions $limitConditions = new LimitConditions(),
        public array $rollupRules = [],
        public RollupControls $rollupControls = new RollupControls(),
        public array $objectives = [new Objective(primary: true)],
        public RandomizationControls $randomizationControls = new RandomizationControls(),
        public DeliveryControls $deliveryControls = new DeliveryControls(),
    ) {
    }

    public function primaryObjective(): Objective
    {
        return array_find($this->objectives, static fn (Objective $objective): bool => $objective->primary) ?? new Objective(primary: true);
    }

    /**
     * The objective of that identifier, else the primary objective.
     */
    public function objective(?string $id): Objective
    {
        return (null === $id ? null : array_find($this->objectives, static fn (Objective $objective): bool => $objective->id === $id)) ?? $this->primaryObjective();
    }

    /**
     * @return list<SequencingRule>
     */
    public function rules(RuleTiming $timing): array
    {
        return match ($timing) {
            RuleTiming::Pre => $this->preConditionRules,
            RuleTiming::Exit => $this->exitConditionRules,
            RuleTiming::Post => $this->postConditionRules,
        };
    }
}
