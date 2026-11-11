<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Infrastructure\Configuration;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagKind;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\Rule;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use Phil\FeatureFlagsBundle\Domain\Flag\Variant;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\FlagDefinitionFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlagDefinitionFormat::class)]
final class FlagDefinitionFormatTest extends TestCase
{
    private const array FULL = [
        'active' => true,
        'rules' => [
            ['attribute' => 'tenant', 'members' => ['tenant-a']],
            ['attribute' => 'project', 'percentage' => 20],
        ],
        'experiment' => ['attribute' => 'tenant', 'variants' => ['control' => 50, 'treatment' => 50]],
        'description' => 'The one-page checkout.',
        'owner' => 'checkout-team',
        'kind' => 'experiment',
        'expires_on' => '2027-03-31',
    ];

    public function testAFlagIsReadWithItsExperimentAndMetadata(): void
    {
        $definition = FlagDefinitionFormat::read(FlagName::of('new-checkout'), self::FULL);

        self::assertTrue($definition->active);
        self::assertCount(2, $definition->rules);
        self::assertSame(['control', 'treatment'], array_map(static fn (Variant $variant): string => $variant->name->value, $definition->experiment->variants ?? []));
        self::assertSame('The one-page checkout.', $definition->metadata->description);
        self::assertSame('checkout-team', $definition->metadata->owner);
        self::assertSame(FlagKind::Experiment, $definition->metadata->kind);
        self::assertSame('2027-03-31', $definition->metadata->expiresOn?->format('Y-m-d'));
    }

    /**
     * What is written reads back as the same definition, so the database and the admin keep what was declared.
     */
    public function testWhatIsWrittenReadsBack(): void
    {
        $definition = FlagDefinitionFormat::read(FlagName::of('new-checkout'), self::FULL);

        self::assertSame(self::FULL, FlagDefinitionFormat::write($definition));
        self::assertEquals($definition, FlagDefinitionFormat::read(FlagName::of('new-checkout'), FlagDefinitionFormat::write($definition)));
    }

    public function testAnEmptyFlagIsWrittenAsItsStateOnly(): void
    {
        self::assertSame(['active' => false], FlagDefinitionFormat::write(FlagDefinitionFormat::read(FlagName::of('f'), [])));
    }

    /**
     * An integer key is what PHP makes of a numeric variant name; it is still the variant of that name.
     */
    public function testANumericVariantNameIsAName(): void
    {
        $definition = FlagDefinitionFormat::read(FlagName::of('f'), ['experiment' => ['attribute' => 'tenant', 'variants' => [1 => 50, 2 => 50]]]);

        self::assertSame('1', $definition->experiment?->variants[0]->name->value);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function malformed(): iterable
    {
        yield 'experiment not a map' => [['experiment' => 'ab'], '"experiment" is a map with "attribute" and "variants".'];
        yield 'variants missing' => [['experiment' => ['attribute' => 'tenant']], '"variants" maps each variant to its weight.'];
        yield 'weight not an integer' => [['experiment' => ['attribute' => 'tenant', 'variants' => ['a' => '50', 'b' => 50]]], 'The weight of the variant "a" is a whole number.'];
        yield 'experiment attribute unknown' => [['experiment' => ['attribute' => 'country', 'variants' => ['a' => 50, 'b' => 50]]], 'An experiment targets one of the attributes tenant, project, subject.'];
        yield 'weights not adding up' => [['experiment' => ['attribute' => 'tenant', 'variants' => ['a' => 50, 'b' => 49]]], 'add up to 100; these add up to 99.'];
        yield 'kind unknown' => [['kind' => 'forever'], '"kind" is one of release, experiment, ops, permission.'];
        yield 'date malformed' => [['expires_on' => '31/03/2027'], '"expires_on" is a date written YYYY-MM-DD; got "31/03/2027".'];
        yield 'date impossible' => [['expires_on' => '2027-02-30'], '"expires_on" is a date written YYYY-MM-DD; got "2027-02-30".'];
        yield 'description not a string' => [['description' => 5], '"description" is a string.'];
        yield 'owner not a string' => [['owner' => ['a']], '"owner" is a string.'];
        yield 'both members and percentage' => [['rules' => [['attribute' => 'tenant', 'members' => ['a'], 'percentage' => 10]]], 'A rule has either "members" or "percentage", not both.'];
        yield 'neither members nor percentage' => [['rules' => [['attribute' => 'tenant']]], 'A rule needs either "members" or "percentage".'];
    }

    /**
     * @param array<string, mixed> $flag
     */
    #[DataProvider('malformed')]
    public function testAMalformedFlagIsRefused(array $flag, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        FlagDefinitionFormat::read(FlagName::of('f'), $flag);
    }

    /**
     * A force-open flag with neither rule nor experiment is what a circuit declares: accepted.
     */
    public function testAPlainForceOpenFlagIsAccepted(): void
    {
        self::assertTrue(FlagDefinitionFormat::read(FlagName::of('circuit-open.http'), ['active' => true])->active);
    }

    /**
     * Rules given as a map, as a JSON object would give them, are written back as a list.
     */
    public function testRulesAreWrittenAsAList(): void
    {
        $definition = FlagDefinitionFormat::read(FlagName::of('f'), ['rules' => ['first' => ['attribute' => 'tenant', 'percentage' => 10]]]);

        self::assertSame([['attribute' => 'tenant', 'percentage' => 10]], FlagDefinitionFormat::write($definition)['rules']);
    }

    public function testARuleOfAnotherTypeHasNoWrittenShape(): void
    {
        $rule = new class implements Rule {
            public function matches(FlagName $flag, EvaluationContext $context): bool
            {
                return false;
            }

            public function matchReason(): EvaluationReason
            {
                return EvaluationReason::TargetingMatch;
            }

            public function reads(): TargetingAttribute
            {
                return TargetingAttribute::Tenant;
            }
        };

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('has no written shape');

        FlagDefinitionFormat::write(new FlagDefinition(FlagName::of('f'), true, [$rule]));
    }

    public function testAForceOpenFlagTakesNoExperiment(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('takes no rule and no experiment');

        FlagDefinitionFormat::read(FlagName::of('circuit-open.http'), ['experiment' => ['attribute' => 'tenant', 'variants' => ['a' => 50, 'b' => 50]]]);
    }
}
