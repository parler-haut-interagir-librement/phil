<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Contract;

use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;

/**
 * Reads the application's retention schedule, wherever it is kept.
 *
 * The adapter belongs to the application: the schedule is its document, in its format.
 */
interface RetentionScheduleSource
{
    public function load(): RetentionSchedule;
}
