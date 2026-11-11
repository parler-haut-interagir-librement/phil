<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Application\Flag;

use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CountingResolver;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingExposures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The variant of an experiment through the evaluator, and its exposure: tenant-1 falls into `control` of
 * `new-checkout`, tenant-2 into `treatment`.
 */
#[CoversClass(FlagEvaluator::class)]
final class FlagEvaluatorVariantTest extends TestCase
{
    public function testTheVariantOfTheContextIsGiven(): void
    {
        self::assertSame('control', $this->evaluator('tenant-1')->variant(FlagName::of('new-checkout'))?->value);
        self::assertSame('treatment', $this->evaluator('tenant-2')->variant(FlagName::of('new-checkout'))?->value);
        self::assertNull($this->evaluator('tenant-1')->variant(FlagName::of('plain')));
    }

    public function testAnExposureIsRecordedOncePerRequest(): void
    {
        $exposures = new RecordingExposures();
        $evaluator = $this->evaluator('tenant-2', $exposures);

        $evaluator->variant(FlagName::of('new-checkout'));
        $evaluator->isEnabled(FlagName::of('new-checkout'));
        $evaluator->isEnabled(FlagName::of('plain'));
        self::assertSame(['new-checkout:treatment'], $exposures->exposures);

        $evaluator->reset();
        $evaluator->evaluate(FlagName::of('new-checkout'));
        self::assertSame(['new-checkout:treatment', 'new-checkout:treatment'], $exposures->exposures);
    }

    public function testAContextOutsideTheExperimentIsNotExposed(): void
    {
        $exposures = new RecordingExposures();

        $this->evaluator(null, $exposures)->variant(FlagName::of('new-checkout'));

        self::assertSame([], $exposures->exposures);
    }

    public function testTheEvaluatedFlagsOfTheRequestAreListed(): void
    {
        $evaluator = $this->evaluator('tenant-1');
        $evaluator->isEnabled(FlagName::of('plain'));
        $evaluator->isEnabled(FlagName::of('absent'));

        self::assertSame(['plain', 'absent'], array_keys($evaluator->evaluated()));

        $evaluator->reset();
        self::assertSame([], $evaluator->evaluated());
    }

    private function evaluator(?string $tenant, ?RecordingExposures $exposures = null): FlagEvaluator
    {
        return new FlagEvaluator(
            [ConfiguredFlagProvider::fromConfiguration([
                'new-checkout' => ['active' => true, 'experiment' => ['attribute' => 'tenant', 'variants' => ['control' => 50, 'treatment' => 50]]],
                'plain' => ['active' => true],
            ])],
            new CountingResolver(EvaluationContext::of(tenant: $tenant)),
            exposures: $exposures,
        );
    }
}
