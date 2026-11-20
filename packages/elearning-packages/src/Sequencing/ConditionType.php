<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The conditions a sequencing rule or a rollup rule tests on an activity, as IMS Simple Sequencing names them.
 */
enum ConditionType: string
{
    case Satisfied = 'satisfied';
    case ObjectiveStatusKnown = 'objectiveStatusKnown';
    case ObjectiveMeasureKnown = 'objectiveMeasureKnown';
    case ObjectiveMeasureGreaterThan = 'objectiveMeasureGreaterThan';
    case ObjectiveMeasureLessThan = 'objectiveMeasureLessThan';
    case Completed = 'completed';
    case ActivityProgressKnown = 'activityProgressKnown';
    case Attempted = 'attempted';
    case AttemptLimitExceeded = 'attemptLimitExceeded';
    case TimeLimitExceeded = 'timeLimitExceeded';
    case OutsideAvailableTimeRange = 'outsideAvailableTimeRange';
    case Always = 'always';

    /**
     * Whether a rollup rule may test it: every condition but those that read the measure against a threshold, and
     * `always`.
     */
    public function isRollupCondition(): bool
    {
        return !\in_array($this, [self::ObjectiveMeasureGreaterThan, self::ObjectiveMeasureLessThan, self::Always], true);
    }
}
