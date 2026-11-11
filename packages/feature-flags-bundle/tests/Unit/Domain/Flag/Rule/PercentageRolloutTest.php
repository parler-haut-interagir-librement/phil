<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag\Rule;

use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\PercentageRollout;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PercentageRollout::class)]
final class PercentageRolloutTest extends TestCase
{
    /**
     * `new-checkout` puts `tenant-1` in bucket 81 and `tenant-2` in bucket 62 (pinned in BucketTest).
     */
    public function testATenantIsInWhenItsBucketIsBelowThePercentage(): void
    {
        $flag = FlagName::of('new-checkout');
        $rule = new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(70));

        self::assertTrue($rule->matches($flag, EvaluationContext::of(tenant: 'tenant-2')));
        self::assertFalse($rule->matches($flag, EvaluationContext::of(tenant: 'tenant-1')));
    }

    public function testTheBucketBoundaryIsExclusive(): void
    {
        $flag = FlagName::of('new-checkout');

        self::assertFalse(new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(62))->matches($flag, EvaluationContext::of(tenant: 'tenant-2')));
        self::assertTrue(new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(63))->matches($flag, EvaluationContext::of(tenant: 'tenant-2')));
    }

    /**
     * A rollout by tenant puts every subject of one tenant on the same side.
     */
    public function testARolloutByTenantIgnoresTheSubject(): void
    {
        $flag = FlagName::of('new-checkout');
        $rule = new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(70));

        for ($i = 0; $i < 50; ++$i) {
            self::assertTrue($rule->matches($flag, EvaluationContext::of(tenant: 'tenant-2', subject: 'user-'.$i)));
        }
    }

    public function testAMissingAttributeDoesNotMatchEvenAtAHundredPercent(): void
    {
        $rule = new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(100));

        self::assertFalse($rule->matches(FlagName::of('f'), EvaluationContext::of(subject: 's')));
    }

    public function testAnAdmittedContextIsASplit(): void
    {
        self::assertSame(EvaluationReason::Split, new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(50))->matchReason());
    }
}
