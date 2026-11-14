<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\RetentionScheduleSource;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;

final readonly class FixedScheduleSource implements RetentionScheduleSource
{
    public function __construct(
        private RetentionSchedule $schedule,
    ) {
    }

    #[Override]
    public function load(): RetentionSchedule
    {
        return $this->schedule;
    }
}
