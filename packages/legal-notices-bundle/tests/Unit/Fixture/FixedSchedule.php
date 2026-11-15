<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Tests\Unit\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\RetentionScheduleSource;
use Phil\PrivacyBundle\Domain\Retention\AccessResponse;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Retention\Mechanism;
use Phil\PrivacyBundle\Domain\Retention\RetentionPeriod;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Phil\PrivacyBundle\Domain\Retention\RetentionSchedule;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;

/**
 * A schedule of four rules, one of each shape of period; the last holds no personal information.
 */
final class FixedSchedule implements RetentionScheduleSource
{
    #[Override]
    public function load(): RetentionSchedule
    {
        $email = [DataCategory::fromString('email')];

        return new RetentionSchedule([
            new RetentionRule('R-001', 'users', $email, RetentionPeriod::fixed('P2Y'), Mechanism::Job, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-002', 'orders', $email, RetentionPeriod::untilEvent('the end of the warranty'), Mechanism::Event, ErasureEffect::Deleted, AccessResponse::Returned),
            new RetentionRule('R-003', 'logs', $email, RetentionPeriod::notYetFixed('the security review'), Mechanism::Manual, ErasureEffect::Survives, AccessResponse::NotReturned),
            new RetentionRule('R-004', 'stats', [], RetentionPeriod::indefinite(), Mechanism::Manual, ErasureEffect::Survives, AccessResponse::NotReturned),
        ]);
    }
}
