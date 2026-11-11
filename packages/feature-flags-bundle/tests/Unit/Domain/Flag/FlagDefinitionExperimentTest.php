<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\Evaluation;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\Experiment;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagKind;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagMetadata;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\MemberOf;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use Phil\FeatureFlagsBundle\Domain\Flag\Variant;
use Phil\FeatureFlagsBundle\Domain\Flag\VariantName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * A flag with an experiment: tenant-1 falls into `control`, tenant-2 into `treatment` (see `ExperimentTest`).
 */
#[CoversClass(FlagDefinition::class)]
#[CoversClass(Evaluation::class)]
final class FlagDefinitionExperimentTest extends TestCase
{
    public function testAnActiveFlagSplitsEveryContextItCanPlace(): void
    {
        $definition = $this->definition(true);

        $control = $definition->evaluate(EvaluationContext::of(tenant: 'tenant-1'));
        self::assertTrue($control->enabled);
        self::assertSame(EvaluationReason::Split, $control->reason);
        self::assertSame('control', $control->variant?->value);

        self::assertSame('treatment', $definition->evaluate(EvaluationContext::of(tenant: 'tenant-2'))->variant?->value);
    }

    public function testAContextTheExperimentCannotPlaceIsOff(): void
    {
        $evaluation = $this->definition(true)->evaluate(EvaluationContext::anonymous());

        self::assertFalse($evaluation->enabled);
        self::assertSame(EvaluationReason::Default, $evaluation->reason);
        self::assertNull($evaluation->variant);
    }

    public function testAnInactiveExperimentShowsNoVariant(): void
    {
        $evaluation = $this->definition(false)->evaluate(EvaluationContext::of(tenant: 'tenant-1'));

        self::assertSame(EvaluationReason::Disabled, $evaluation->reason);
        self::assertNull($evaluation->variant);
    }

    /**
     * The rules decide who is in the experiment, the experiment which arm they see.
     */
    public function testTheRulesDecideWhoIsInTheExperiment(): void
    {
        $definition = $this->definition(true, [MemberOf::of(TargetingAttribute::Tenant, ['tenant-2'])]);

        self::assertSame(EvaluationReason::Default, $definition->evaluate(EvaluationContext::of(tenant: 'tenant-1'))->reason);
        self::assertSame('treatment', $definition->evaluate(EvaluationContext::of(tenant: 'tenant-2'))->variant?->value);
    }

    public function testAFlagWithoutExperimentShowsNoVariant(): void
    {
        self::assertNull(new FlagDefinition(FlagName::of('f'), true)->evaluate(EvaluationContext::of(tenant: 'tenant-1'))->variant);
    }

    public function testAToggleKeepsTheExperimentAndTheMetadata(): void
    {
        $metadata = FlagMetadata::of('Checkout layout.', kind: FlagKind::Experiment);
        $definition = new FlagDefinition(FlagName::of('new-checkout'), false, [], $this->experiment(), $metadata)->withActive(true);

        self::assertTrue($definition->active);
        self::assertSame($metadata, $definition->metadata);
        self::assertSame('control', $definition->evaluate(EvaluationContext::of(tenant: 'tenant-1'))->variant?->value);
    }

    public function testADefinitionWithoutMetadataHasNone(): void
    {
        self::assertEquals(FlagMetadata::none(), new FlagDefinition(FlagName::of('f'), true)->metadata);
    }

    public function testOnlyAnEvaluationThatIsOnCarriesAVariant(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('Only an evaluation that is on carries a variant.');

        new Evaluation(FlagName::of('f'), false, EvaluationReason::Default, variant: VariantName::of('a'));
    }

    /**
     * @param list<MemberOf> $rules
     */
    private function definition(bool $active, array $rules = []): FlagDefinition
    {
        return new FlagDefinition(FlagName::of('new-checkout'), $active, $rules, $this->experiment());
    }

    private function experiment(): Experiment
    {
        return Experiment::of(TargetingAttribute::Tenant, [Variant::of(VariantName::of('control'), 50), Variant::of(VariantName::of('treatment'), 50)]);
    }
}
