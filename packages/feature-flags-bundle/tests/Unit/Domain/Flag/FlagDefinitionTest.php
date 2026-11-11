<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\Evaluation;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationErrorCode;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\MemberOf;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\PercentageRollout;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlagDefinition::class)]
#[CoversClass(Evaluation::class)]
final class FlagDefinitionTest extends TestCase
{
    public function testAnActiveFlagWithoutRulesIsOnForEveryone(): void
    {
        $evaluation = new FlagDefinition(FlagName::of('f'), true)->evaluate(EvaluationContext::anonymous());

        self::assertTrue($evaluation->enabled);
        self::assertSame(EvaluationReason::Static, $evaluation->reason);
    }

    public function testAMatchingRuleTurnsTheFlagOn(): void
    {
        $evaluation = $this->targeted(true)->evaluate(EvaluationContext::of(project: 'p1'));

        self::assertTrue($evaluation->enabled);
        self::assertSame(EvaluationReason::TargetingMatch, $evaluation->reason);
    }

    public function testNoMatchingRuleLeavesTheFlagOff(): void
    {
        $evaluation = $this->targeted(true)->evaluate(EvaluationContext::of(project: 'p9'));

        self::assertFalse($evaluation->enabled);
        self::assertSame(EvaluationReason::Default, $evaluation->reason);
    }

    /**
     * Deactivation is the rollback: it wins over every rule, and the rules are still there afterwards.
     */
    public function testAnInactiveFlagIsOffWhateverItsRules(): void
    {
        $definition = $this->targeted(false);
        $evaluation = $definition->evaluate(EvaluationContext::of(project: 'p1'));

        self::assertFalse($evaluation->enabled);
        self::assertSame(EvaluationReason::Disabled, $evaluation->reason);
        self::assertCount(2, $definition->rules);
    }

    public function testTheEvaluationNamesItsFlag(): void
    {
        self::assertSame('f', $this->targeted(true)->evaluate(EvaluationContext::anonymous())->flag->value);
    }

    public function testANotFoundEvaluationIsOff(): void
    {
        $evaluation = Evaluation::notFound(FlagName::of('missing'));

        self::assertFalse($evaluation->enabled);
        self::assertSame(EvaluationReason::Error, $evaluation->reason);
        self::assertSame(EvaluationErrorCode::FlagNotFound, $evaluation->errorCode);
        self::assertSame('ERROR', $evaluation->reason->value);
        self::assertSame('FLAG_NOT_FOUND', $evaluation->errorCode->value);
    }

    public function testAPercentageRolloutThatAdmitsTheContextIsASplit(): void
    {
        $definition = new FlagDefinition(FlagName::of('f'), true, [
            new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(100)),
        ]);
        $evaluation = $definition->evaluate(EvaluationContext::of(tenant: 't1'));

        self::assertTrue($evaluation->enabled);
        self::assertSame(EvaluationReason::Split, $evaluation->reason);
        self::assertSame('SPLIT', $evaluation->reason->value);
        self::assertNull($evaluation->errorCode);
    }

    public function testAnotherActiveStateKeepsTheRules(): void
    {
        $definition = $this->targeted(true);
        $inactive = $definition->withActive(false);

        self::assertFalse($inactive->active);
        self::assertSame($definition->rules, $inactive->rules);
        self::assertSame($definition->name, $inactive->name);
        self::assertTrue($definition->active, 'the original definition is unchanged');
    }

    #[TestWith([EvaluationReason::Error, null, false])]
    #[TestWith([EvaluationReason::Default, EvaluationErrorCode::FlagNotFound, false])]
    #[TestWith([EvaluationReason::Error, EvaluationErrorCode::FlagNotFound, true])]
    public function testAnErrorCodeGoesWithTheErrorReasonOnlyAndIsOff(EvaluationReason $reason, ?EvaluationErrorCode $code, bool $enabled): void
    {
        $this->expectException(InvalidValue::class);

        new Evaluation(FlagName::of('f'), $enabled, $reason, $code);
    }

    private function targeted(bool $active): FlagDefinition
    {
        return new FlagDefinition(FlagName::of('f'), $active, [
            MemberOf::of(TargetingAttribute::Project, ['p1']),
            new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(0)),
        ]);
    }
}
