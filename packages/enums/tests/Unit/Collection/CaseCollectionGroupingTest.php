<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Unit\Collection;

use InvalidArgumentException;
use LogicException;
use Phil\Enums\Attribute\Group;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Weight;
use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Tests\Fixture\Attribute\Mass;
use Phil\Enums\Tests\Fixture\Attribute\Payload;
use Phil\Enums\Tests\Fixture\Level;
use Phil\Enums\Tests\Fixture\PayloadEnum;
use Phil\Enums\Tests\Fixture\Planet;
use Phil\Enums\Tests\Fixture\SequenceEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use UnexpectedValueException;

/**
 * @internal
 */
#[CoversClass(CaseCollection::class)]
final class CaseCollectionGroupingTest extends TestCase
{
    #[Test]
    public function partitionSplitsAcceptedFromRefused(): void
    {
        [$heavy, $light] = Planet::collect()->partition(static fn (Planet $planet): bool => $planet->mass() > 40);

        self::assertSame([Planet::Earth, Planet::Venus], $heavy->all());
        self::assertSame([Planet::Mars, Planet::Mercury, Planet::Pluto], $light->all());
    }

    #[Test]
    public function groupByPutsACaseUnderEachOfItsKeysInTheOrderTheyComeUp(): void
    {
        $groups = Planet::groupBy(static fn (Planet $planet): array|string => Planet::Earth === $planet ? ['home', 'e'] : $planet->name[1]);

        self::assertSame(['a', 'home', 'e', 'l'], array_keys($groups));
        self::assertSame([Planet::Mars], $groups['a']->all());
        self::assertSame([Planet::Earth], $groups['home']->all());
        self::assertSame([Planet::Earth, Planet::Mercury, Planet::Venus], $groups['e']->all());
        self::assertSame([Planet::Pluto], $groups['l']->all());
        self::assertSame([], CaseCollection::of([])->groupBy(static fn (): string => 'x'));
    }

    #[Test]
    public function groupByMetaReadsEveryValueOfARepeatableProperty(): void
    {
        $groups = Planet::groupByMeta(Group::class);

        self::assertSame(['inner', 'home', 'dwarf'], array_keys($groups));
        self::assertSame([Planet::Mars, Planet::Earth, Planet::Mercury, Planet::Venus], $groups['inner']->all());
        self::assertSame([Planet::Earth], $groups['home']->all());
        self::assertSame([Planet::Pluto], $groups['dwarf']->all());
    }

    #[Test]
    public function groupByMetaLeavesOutACaseWithoutTheAttributeAndANullDefault(): void
    {
        $groups = Planet::groupByMeta(Mass::class);

        self::assertSame([6, 60, 3, 49], array_keys($groups));
        self::assertSame([Planet::Earth], $groups[60]->all());
    }

    #[Test]
    public function groupByMetaPutsACaseWithoutTheAttributeUnderTheDefaultValue(): void
    {
        $groups = Level::groupByMeta(Weight::class);

        self::assertSame([3, 1, 0, 2], array_keys($groups));
        self::assertSame([Level::Medium], $groups[1]->all());
    }

    #[Test]
    public function groupByMetaRefusesAValueThatCannotBeAKey(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains(PayloadEnum::class.'::ONE has a '.Payload::class.' value of type array, which cannot be a group key.');

        PayloadEnum::collect()->groupByMeta(Payload::class);
    }

    #[Test]
    public function keyByKeysTheCasesAndRefusesADuplicateKey(): void
    {
        self::assertSame(['Low' => Level::Low, 'Medium' => Level::Medium], Level::collect()->only(Level::Low, Level::Medium)->keyBy(static fn (Level $level): string => $level->label()));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('Mars and Mercury both have the key "M".');

        Planet::collect()->keyBy(static fn (Planet $planet): string => $planet->name[0]);
    }

    #[Test]
    public function pluckReadsAMetaPropertyOfEachCase(): void
    {
        self::assertSame([6, 60, 3, 49, null], Planet::collect()->pluck(Mass::class));
        self::assertSame(['Low', 'Medium', 'High', 'Critical'], Level::collect()->pluck(Label::class));
    }

    #[Test]
    public function deprecatedCasesAreSetApart(): void
    {
        $critical = Level::from('critical');

        self::assertSame([Level::Low, Level::Medium, Level::High], Level::withoutDeprecated()->all());
        self::assertSame([$critical], Level::collect()->deprecated()->all());
        self::assertTrue($critical->isDeprecated());
        self::assertFalse(Level::Low->isDeprecated());
        self::assertFalse(Level::Low->isDeprecated());
    }

    #[Test]
    public function randomDrawsEachCaseAsOftenAsItsWeight(): void
    {
        // Weights 3, 1, 0 and 2: draws 1 to 3 give Low, 4 Medium, 5 and 6 Critical, High never.
        $randomizer = new Randomizer(new SequenceEngine([0, 1, 2, 3, 4, 5]));
        $draws = array_map(static fn (): ?Level => Level::random($randomizer), range(1, 6));

        self::assertSame([Level::Low, Level::Low, Level::Low, Level::Medium, Level::from('critical'), Level::from('critical')], $draws);
    }

    #[Test]
    public function randomDrawsNothingFromNothing(): void
    {
        self::assertNull(CaseCollection::of([])->random());
        self::assertNull(Level::collect()->only(Level::High)->random());
        self::assertSame(Level::Medium, Level::collect()->only(Level::Medium, Level::High)->random(new Randomizer(new SequenceEngine([0]))));
    }

    #[Test]
    public function randomWithoutARandomizerStillDrawsADrawableCase(): void
    {
        $drawn = Level::random();

        self::assertNotNull($drawn);
        self::assertNotSame(Level::High, $drawn);
        self::assertSame(Level::random(new Randomizer(new Xoshiro256StarStar(7))), Level::random(new Randomizer(new Xoshiro256StarStar(7))));
    }

    #[Test]
    public function aNegativeWeightIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A weight is 0 or more, -1 given.');

        new Weight(-1);
    }

    #[Test]
    public function aWeightOfZeroIsAccepted(): void
    {
        self::assertSame(0, new Weight(0)->value);
        self::assertSame(1, Weight::defaultValue());
    }
}
