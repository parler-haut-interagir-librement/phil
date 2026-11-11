<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag\Rule;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\MemberOf;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemberOf::class)]
final class MemberOfTest extends TestCase
{
    public function testAMemberMatches(): void
    {
        $rule = MemberOf::of(TargetingAttribute::Tenant, ['t1', 't2']);

        self::assertTrue($rule->matches(FlagName::of('f'), EvaluationContext::of(tenant: 't2')));
    }

    public function testANonMemberDoesNotMatch(): void
    {
        $rule = MemberOf::of(TargetingAttribute::Tenant, ['t1']);

        self::assertFalse($rule->matches(FlagName::of('f'), EvaluationContext::of(tenant: 't3')));
    }

    public function testTheRuleReadsOnlyItsOwnAttribute(): void
    {
        $rule = MemberOf::of(TargetingAttribute::Project, ['x']);

        self::assertFalse($rule->matches(FlagName::of('f'), EvaluationContext::of(tenant: 'x')));
    }

    public function testAMissingAttributeDoesNotMatch(): void
    {
        $rule = MemberOf::of(TargetingAttribute::Tenant, ['t1']);

        self::assertFalse($rule->matches(FlagName::of('f'), EvaluationContext::anonymous()));
    }

    /**
     * A numeric identifier becomes an integer array key; it must still match and read back as a string.
     */
    public function testANumericMemberStaysAString(): void
    {
        $rule = MemberOf::of(TargetingAttribute::Tenant, ['42', '42', 't']);

        self::assertTrue($rule->matches(FlagName::of('f'), EvaluationContext::of(tenant: '42')));
        self::assertSame(['42', 't'], $rule->members());
    }

    public function testAnEmptySetIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        MemberOf::of(TargetingAttribute::Tenant, []);
    }

    public function testAnEmptyMemberIsRefused(): void
    {
        $this->expectException(InvalidValue::class);

        MemberOf::of(TargetingAttribute::Tenant, ['t1', '']);
    }

    public function testAMatchIsATargetingMatch(): void
    {
        self::assertSame(EvaluationReason::TargetingMatch, MemberOf::of(TargetingAttribute::Tenant, ['t1'])->matchReason());
    }
}
