<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The rollup of the measure, the satisfaction and the completion of the activities, from one activity up to the root.
 *
 * @internal built by `SequencingRun` for each request
 */
final readonly class SequencingRollup
{
    public function __construct(
        private ActivityTree $tree,
        private SequencingData $data,
        private SequencingRules $rules,
        private SequencingFlow $flow,
    ) {
    }

    /**
     * Rolls up the measure, the satisfaction and the completion from the activity up to the root.
     */
    public function rollup(string $identifier): void
    {
        foreach (array_reverse($this->tree->path($identifier)) as $activity) {
            if (!$activity->isLeaf()) {
                $this->measureRollup($activity);
            }

            $this->objectiveRollup($activity);

            if (!$activity->isLeaf()) {
                $this->progressRollup($activity);
            }
        }
    }

    private function measureRollup(Activity $activity): void
    {
        $total = 0.0;
        $sum = 0.0;
        $known = false;

        foreach ($this->flow->available($activity) as $child) {
            if (!$child->sequencing->deliveryControls->tracked) {
                continue;
            }

            $weight = $child->sequencing->rollupControls->measureWeight;
            $total += $weight;
            $measure = $this->data->measure($child, $child->sequencing->primaryObjective());

            if (null !== $measure) {
                $known = true;
                $sum += $measure * $weight;
            }
        }

        $this->data->setMeasure($activity, $activity->sequencing->primaryObjective(), $known && $total > 0.0 ? max(-1.0, min(1.0, $sum / $total)) : null);
    }

    private function objectiveRollup(Activity $activity): void
    {
        $primary = $activity->sequencing->primaryObjective();

        if ($primary->satisfiedByMeasure) {
            $measure = $this->data->measure($activity, $primary);

            if (null === $measure) {
                $this->data->setSatisfied($activity, $primary, null);
            } elseif (!$this->data->record($activity->identifier)['active'] || $activity->sequencing->rollupControls->measureSatisfactionIfActive) {
                $this->data->setSatisfied($activity, $primary, $measure >= $primary->minNormalizedMeasure);
            }

            return;
        }

        if ($activity->isLeaf()) {
            return;
        }

        if ($this->rollupHolds($activity, RollupAction::NotSatisfied)) {
            $this->data->setSatisfied($activity, $primary, false);
        }

        if ($this->rollupHolds($activity, RollupAction::Satisfied)) {
            $this->data->setSatisfied($activity, $primary, true);
        }
    }

    private function progressRollup(Activity $activity): void
    {
        if ($this->rollupHolds($activity, RollupAction::Incomplete)) {
            $this->data->records[$activity->identifier] = ['completed' => false] + $this->data->record($activity->identifier);
        }

        if ($this->rollupHolds($activity, RollupAction::Completed)) {
            $this->data->records[$activity->identifier] = ['completed' => true] + $this->data->record($activity->identifier);
        }
    }

    /**
     * Whether a rollup rule of that action holds on the children of the activity: its own rules, else the defaults —
     * satisfied when all are, not satisfied when the satisfaction of all is known; completed when all are, incomplete
     * when the completion of all is known.
     */
    private function rollupHolds(Activity $activity, RollupAction $action): bool
    {
        $rules = array_filter($activity->sequencing->rollupRules, static fn (RollupRule $rule): bool => $rule->action->isObjective() === $action->isObjective());

        $rules = [] === $rules ? [$this->defaultRule($action)] : array_filter($rules, static fn (RollupRule $rule): bool => $rule->action === $action);

        foreach ($rules as $rule) {
            $bag = [];

            foreach ($this->flow->available($activity) as $child) {
                if ($child->sequencing->deliveryControls->tracked && $this->contributes($child, $action)) {
                    $bag[] = $this->rules->conditions($child, $rule->conditions, $rule->requireAll, true);
                }
            }

            if ($this->setHolds($rule, $bag)) {
                return true;
            }
        }

        return false;
    }

    private function defaultRule(RollupAction $action): RollupRule
    {
        return new RollupRule([new RuleCondition(match ($action) {
            RollupAction::Satisfied => ConditionType::Satisfied,
            RollupAction::NotSatisfied => ConditionType::ObjectiveStatusKnown,
            RollupAction::Completed => ConditionType::Completed,
            RollupAction::Incomplete => ConditionType::ActivityProgressKnown,
        })], $action);
    }

    /**
     * @param list<?bool> $bag
     */
    private function setHolds(RollupRule $rule, array $bag): bool
    {
        if ([] === $bag) {
            return false;
        }

        $true = \count(array_filter($bag, static fn (?bool $value): bool => true === $value));

        return match ($rule->childActivitySet) {
            ChildActivitySet::All => $true === \count($bag),
            ChildActivitySet::Any => $true > 0,
            ChildActivitySet::None => !\in_array(true, $bag, true) && !\in_array(null, $bag, true),
            ChildActivitySet::AtLeastCount => $true >= $rule->minimumCount,
            ChildActivitySet::AtLeastPercent => $true >= $rule->minimumPercent * \count($bag),
        };
    }

    /**
     * Whether the child counts in a rollup of that action: its rollup controls let it, and its rollup considerations —
     * attempted, not skipped, not suspended — hold; a disabled child does not count.
     */
    private function contributes(Activity $child, RollupAction $action): bool
    {
        $controls = $child->sequencing->rollupControls;

        if (!($action->isObjective() ? $controls->objectiveSatisfied : $controls->progressCompletion)) {
            return false;
        }

        $record = $this->data->record($child->identifier);
        $considered = match ($controls->requiredFor($action)) {
            Requirement::Always => true,
            Requirement::IfAttempted => $record['attempts'] > 0,
            Requirement::IfNotSkipped => !$this->rules->ruleAction($child, RuleTiming::Pre, [RuleAction::Skip]) instanceof RuleAction,
            Requirement::IfNotSuspended => !$record['suspended'],
        };

        return $considered && !$this->rules->ruleAction($child, RuleTiming::Pre, [RuleAction::Disabled]) instanceof RuleAction;
    }
}
