<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\Bucket;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\Experiment;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use Phil\FeatureFlagsBundle\Domain\Flag\Variant;
use Phil\FeatureFlagsBundle\Domain\Flag\VariantName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Experiment::class)]
#[CoversClass(Variant::class)]
#[CoversClass(VariantName::class)]
#[CoversClass(Bucket::class)]
final class ExperimentTest extends TestCase
{
    /**
     * Pinned values: a change here moves the cohorts of every running experiment.
     *
     * @return iterable<string, array{string, string, int}>
     */
    public static function pinned(): iterable
    {
        yield 'murmur3a("new-checkout:variant:tenant-1") = 5855ef60' => ['new-checkout', 'tenant-1', 48];
        yield 'murmur3a("new-checkout:variant:tenant-2") = 976c831b' => ['new-checkout', 'tenant-2', 67];
        yield 'murmur3a("new-checkout:variant:42") = 65a8ac66' => ['new-checkout', '42', 98];
    }

    #[DataProvider('pinned')]
    public function testTheVariantBucketIsPinned(string $flag, string $key, int $expected): void
    {
        self::assertSame($expected, Bucket::forVariant(FlagName::of($flag), $key)->value);
    }

    /**
     * Salted apart from the rollout bucket: the same key does not land in the same bucket of both.
     */
    public function testTheVariantBucketIsSaltedApartFromTheRollout(): void
    {
        self::assertNotSame(Bucket::of(FlagName::of('new-checkout'), 'tenant-1')->value, Bucket::forVariant(FlagName::of('new-checkout'), 'tenant-1')->value);
    }

    public function testAnEmptyKeyHasNoVariantBucket(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A targeting key cannot be empty');

        Bucket::forVariant(FlagName::of('f'), '');
    }

    /**
     * Buckets 0 to 49 see the first variant, 50 to 99 the second: tenant-1 is in bucket 48, tenant-2 in 67.
     */
    public function testTheBucketsAreHandedOutByCumulativeWeight(): void
    {
        $experiment = $this->experiment(['control' => 50, 'treatment' => 50]);
        $flag = FlagName::of('new-checkout');

        self::assertSame('control', $experiment->assign($flag, EvaluationContext::of(tenant: 'tenant-1'))?->value);
        self::assertSame('treatment', $experiment->assign($flag, EvaluationContext::of(tenant: 'tenant-2'))?->value);
    }

    /**
     * The boundary is exclusive: bucket 48 is past a first arm of 48 and inside one of 49.
     */
    public function testTheBoundaryOfAnArmIsExclusive(): void
    {
        $flag = FlagName::of('new-checkout');
        $context = EvaluationContext::of(tenant: 'tenant-1');

        self::assertSame('b', $this->experiment(['a' => 48, 'b' => 52])->assign($flag, $context)?->value);
        self::assertSame('a', $this->experiment(['a' => 49, 'b' => 51])->assign($flag, $context)?->value);
    }

    /**
     * Bucket 98 falls into the last arm, and an arm of weight 0 is never reached.
     */
    public function testTheLastArmCoversTheLastBuckets(): void
    {
        $experiment = $this->experiment(['a' => 50, 'closed' => 0, 'b' => 50]);

        self::assertSame('b', $experiment->assign(FlagName::of('new-checkout'), EvaluationContext::of(tenant: '42'))?->value);
    }

    public function testAContextWithoutTheAttributeIsNotPlaced(): void
    {
        self::assertNull($this->experiment(['a' => 50, 'b' => 50])->assign(FlagName::of('f'), EvaluationContext::of(project: 'p')));
    }

    public function testTheAssignmentIsStable(): void
    {
        $experiment = $this->experiment(['a' => 34, 'b' => 33, 'c' => 33]);
        $context = EvaluationContext::of(tenant: 'tenant-7');

        self::assertEquals($experiment->assign(FlagName::of('f'), $context), $experiment->assign(FlagName::of('f'), $context));
    }

    public function testAnExperimentComparesAtLeastTwoVariants(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('An experiment compares at least two variants.');

        $this->experiment(['only' => 100]);
    }

    public function testTheWeightsAddUpToOneHundred(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The weights of the variants of an experiment add up to 100; these add up to 90.');

        $this->experiment(['a' => 50, 'b' => 40]);
    }

    public function testAVariantIsDeclaredOnce(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The variant "a" is declared twice.');

        Experiment::of(TargetingAttribute::Tenant, [Variant::of(VariantName::of('a'), 50), Variant::of(VariantName::of('a'), 50)]);
    }

    #[TestWith([-1])]
    #[TestWith([101])]
    public function testAWeightIsBetweenZeroAndOneHundred(int $weight): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The weight of the variant "a" is between 0 and 100; got %d.', $weight));

        Variant::of(VariantName::of('a'), $weight);
    }

    #[TestWith([0])]
    #[TestWith([100])]
    public function testTheWeightBoundsAreAccepted(int $weight): void
    {
        self::assertSame($weight, Variant::of(VariantName::of('a'), $weight)->weight);
    }

    #[TestWith(['control'])]
    #[TestWith(['two-columns'])]
    #[TestWith(['v2.b_1'])]
    public function testAVariantNameIsAccepted(string $name): void
    {
        self::assertSame($name, VariantName::of($name)->value);
    }

    #[TestWith(['Control'])]
    #[TestWith([''])]
    #[TestWith(['a b'])]
    #[TestWith(['-a'])]
    #[TestWith(["a\n"])]
    public function testAMalformedVariantNameIsRefused(string $name): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is not a variant name');

        VariantName::of($name);
    }

    public function testAVariantNameIsAtMostFiftyCharacters(): void
    {
        self::assertSame(50, \strlen(VariantName::of(str_repeat('a', 50))->value));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A variant name is at most 50 characters; this one has 51.');

        VariantName::of(str_repeat('a', 51));
    }

    public function testVariantNamesCompareByValue(): void
    {
        self::assertTrue(VariantName::of('a')->equals(VariantName::of('a')));
        self::assertFalse(VariantName::of('a')->equals(VariantName::of('b')));
    }

    /**
     * @param array<string, int> $weights
     */
    private function experiment(array $weights): Experiment
    {
        $variants = [];

        foreach ($weights as $name => $weight) {
            $variants[] = Variant::of(VariantName::of($name), $weight);
        }

        return Experiment::of(TargetingAttribute::Tenant, $variants);
    }
}
