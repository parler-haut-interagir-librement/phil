<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Sequencing\Prerequisite\Prerequisites;

/**
 * The sequencing rules of the activities, and the prerequisites of SCORM 1.2, evaluated on the working copy.
 *
 * @internal built by `SequencingRun` for each request
 */
final readonly class SequencingRules
{
    public function __construct(
        private ActivityTree $tree,
        private SequencingData $data,
    ) {
    }

    /**
     * The action of the first rule of that timing that holds, among those of the actions given.
     *
     * @param ?list<RuleAction> $actions
     */
    public function ruleAction(Activity $activity, RuleTiming $timing, ?array $actions = null): ?RuleAction
    {
        foreach ($activity->sequencing->rules($timing) as $rule) {
            if ((null === $actions || \in_array($rule->action, $actions, true)) && true === $this->conditions($activity, $rule->conditions, $rule->requireAll, false)) {
                return $rule->action;
            }
        }

        return null;
    }

    /**
     * The conditions combined, all or any: true, false, or null when a rollup cannot tell.
     *
     * @param non-empty-list<RuleCondition> $conditions
     */
    public function conditions(Activity $activity, array $conditions, bool $requireAll, bool $rollup): ?bool
    {
        $values = array_map(fn (RuleCondition $condition): ?bool => $this->condition($activity, $condition, $rollup), $conditions);

        if ($requireAll) {
            return \in_array(false, $values, true) ? false : (\in_array(null, $values, true) ? null : true);
        }

        return \in_array(true, $values, true) ? true : (\in_array(null, $values, true) ? null : false);
    }

    /**
     * One condition on the activity: an unknown satisfaction, completion or measure is null in a rollup, false in a
     * sequencing rule, before the negation.
     */
    private function condition(Activity $activity, RuleCondition $condition, bool $rollup): ?bool
    {
        $record = $this->data->record($activity->identifier);
        $objective = $activity->sequencing->objective($condition->referencedObjective);
        $limits = $activity->sequencing->limitConditions;
        $measure = $this->data->measure($activity, $objective);
        $value = match ($condition->type) {
            ConditionType::Satisfied => $this->data->satisfied($activity, $objective),
            ConditionType::ObjectiveStatusKnown => null !== $this->data->satisfied($activity, $objective),
            ConditionType::ObjectiveMeasureKnown => null !== $measure,
            ConditionType::ObjectiveMeasureGreaterThan => null === $measure ? null : $measure > $condition->measureThreshold,
            ConditionType::ObjectiveMeasureLessThan => null === $measure ? null : $measure < $condition->measureThreshold,
            ConditionType::Completed => $record['completed'],
            ConditionType::ActivityProgressKnown => null !== $record['completed'],
            ConditionType::Attempted => $record['attempts'] > 0,
            ConditionType::AttemptLimitExceeded => null !== $limits->attemptLimit && $record['attempts'] >= $limits->attemptLimit,
            ConditionType::TimeLimitExceeded => $limits->attemptAbsoluteDurationLimit instanceof Duration && null !== $record['duration'] && $record['duration'] > $limits->attemptAbsoluteDurationLimit->centiseconds,
            ConditionType::OutsideAvailableTimeRange => false,
            ConditionType::Always => true,
        };

        if (null === $value) {
            return $rollup ? null : $condition->not;
        }

        return $value !== $condition->not;
    }

    /**
     * Why the activity may not be delivered — a disabled rule, its attempts spent, the prerequisites of SCORM 1.2
     * unmet —, or null.
     */
    public function checkActivity(Activity $activity): ?string
    {
        if ($this->ruleAction($activity, RuleTiming::Pre, [RuleAction::Disabled]) instanceof RuleAction) {
            return \sprintf('The activity "%s" is disabled.', $activity->identifier);
        }

        $record = $this->data->record($activity->identifier);
        $limit = $activity->sequencing->limitConditions->attemptLimit;

        if (null !== $limit && $record['attempts'] >= $limit && !$record['active'] && !$record['suspended']) {
            return \sprintf('The activity "%s" has no attempt left.', $activity->identifier);
        }

        if (!$this->prerequisitesMet($activity)) {
            return \sprintf('The prerequisites of the activity "%s" are not met.', $activity->identifier);
        }

        return null;
    }

    /**
     * Whether the flow passes over the activity: a skip rule holds, or the prerequisites of SCORM 1.2 are unmet.
     */
    public function isSkipped(Activity $activity): bool
    {
        return $this->ruleAction($activity, RuleTiming::Pre, [RuleAction::Skip]) instanceof RuleAction || !$this->prerequisitesMet($activity);
    }

    private function prerequisitesMet(Activity $activity): bool
    {
        return !$activity->prerequisites instanceof Prerequisites || !$this->tree->isScorm12() || $activity->prerequisites->isMet(fn (string $identifier): string => $this->tree->has($identifier) ? $this->data->lessonStatus($identifier) : 'not attempted');
    }
}
