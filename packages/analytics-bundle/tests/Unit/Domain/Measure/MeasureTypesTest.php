<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Measure;

use Phil\AnalyticsBundle\Domain\Measure\ActiveTime;
use Phil\AnalyticsBundle\Domain\Measure\ClickTracking;
use Phil\AnalyticsBundle\Domain\Measure\ClosedList;
use Phil\AnalyticsBundle\Domain\Measure\ConversionRate;
use Phil\AnalyticsBundle\Domain\Measure\DeclaredDimension;
use Phil\AnalyticsBundle\Domain\Measure\DeclaredOutboundLink;
use Phil\AnalyticsBundle\Domain\Measure\Goal;
use Phil\AnalyticsBundle\Domain\Measure\MeasureEvent;
use Phil\AnalyticsBundle\Domain\Measure\MeasurePlan;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveTime::class)]
#[CoversClass(ClickTracking::class)]
#[CoversClass(ClosedList::class)]
#[CoversClass(ConversionRate::class)]
#[CoversClass(DeclaredDimension::class)]
#[CoversClass(DeclaredOutboundLink::class)]
#[CoversClass(Goal::class)]
#[CoversClass(MeasureEvent::class)]
#[CoversClass(MeasurePlan::class)]
final class MeasureTypesTest extends TestCase
{
    public function testClicksAreAggregateByDefault(): void
    {
        self::assertSame(ClickTracking::Aggregate, MeasurePlan::of()->clicks);
        self::assertTrue(ClickTracking::Aggregate->counts());
        self::assertFalse(ClickTracking::Off->counts());
    }

    public function testWhichEventsComeFromAClick(): void
    {
        self::assertSame(
            [MeasureEvent::Click, MeasureEvent::Download, MeasureEvent::Outbound],
            array_values(array_filter(MeasureEvent::cases(), static fn (MeasureEvent $event): bool => $event->isClick())),
        );
    }

    /**
     * @return iterable<string, array{int, ActiveTime}>
     */
    public static function bands(): iterable
    {
        yield 'none' => [0, ActiveTime::UnderTenSeconds];
        yield '9' => [9, ActiveTime::UnderTenSeconds];
        yield '10' => [10, ActiveTime::UnderThirtySeconds];
        yield '29' => [29, ActiveTime::UnderThirtySeconds];
        yield '30' => [30, ActiveTime::UnderAMinute];
        yield '59' => [59, ActiveTime::UnderAMinute];
        yield '60' => [60, ActiveTime::UnderThreeMinutes];
        yield '179' => [179, ActiveTime::UnderThreeMinutes];
        yield '180' => [180, ActiveTime::UnderTenMinutes];
        yield '599' => [599, ActiveTime::UnderTenMinutes];
        yield '600' => [600, ActiveTime::Longer];
    }

    #[DataProvider('bands')]
    public function testActiveTimeBands(int $seconds, ActiveTime $expected): void
    {
        self::assertSame($expected, ActiveTime::ofSeconds($seconds));
    }

    public function testAClosedListHoldsWhatItDeclares(): void
    {
        $list = ClosedList::of(['a', '7z', 'a']);

        self::assertTrue($list->has('a'));
        self::assertTrue($list->has('7z'));
        self::assertFalse($list->has('b'));
        self::assertSame(['a', '7z'], $list->toArray());
        self::assertSame([], ClosedList::empty()->toArray());
        self::assertTrue(ClosedList::downloads()->has('pdf'));
        self::assertTrue(ClosedList::downloads()->has('7z'));
        self::assertFalse(ClosedList::downloads()->has('html'));
    }

    public function testADigitOnlyValueIsANumericKeyAndStaysAString(): void
    {
        self::assertSame(['123'], ClosedList::of(['123'])->toArray());
    }

    public function testAClosedListIsBoundedAtItsLimit(): void
    {
        $values = array_map(static fn (int $i): string => 'v'.$i, range(1, ClosedList::MAX_VALUES));

        self::assertCount(ClosedList::MAX_VALUES, ClosedList::of($values)->toArray());

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A closed list holds at most 256 values; this one holds 257.');

        ClosedList::of([...$values, 'one-more']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function undeclarable(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['A'];
        yield 'leading dash' => ['-a'];
        yield 'space' => ['a b'];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'a trailing newline' => ["a\n"];
    }

    #[DataProvider('undeclarable')]
    public function testAClosedListRefusesWhatCannotBeDeclared(string $value): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('cannot be declared');

        ClosedList::of([$value]);
    }

    public function testTheLongestDeclaredValueIsAccepted(): void
    {
        $value = str_repeat('a', 64);

        self::assertTrue(ClosedList::of([$value])->has($value));
    }

    public function testADeclaredDimensionKeepsItsNameAndValues(): void
    {
        $values = ClosedList::of(['a']);
        $dimension = DeclaredDimension::of('variant', $values);

        self::assertSame('variant', $dimension->name);
        self::assertSame($values, $dimension->values);
    }

    public function testADimensionNameIsChecked(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"Bad" is not a dimension name');

        DeclaredDimension::of('Bad', ClosedList::empty());
    }

    public function testADimensionNameOfTheBundleIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The dimension "route" is the bundle\'s own.');

        DeclaredDimension::of('route', ClosedList::empty());
    }

    public function testAGoalOnARoute(): void
    {
        $goal = Goal::onRoute('signup', 'welcome');

        self::assertSame('signup', $goal->name);
        self::assertTrue($goal->isReachedByRoute('welcome'));
        self::assertFalse($goal->isReachedByRoute('home'));
        self::assertFalse($goal->isReachedByEvent(MeasureEvent::Click, 'welcome'));
    }

    public function testAGoalOnAnEvent(): void
    {
        $goal = Goal::onEvent('contact', MeasureEvent::FormSubmit, 'contact-form');

        self::assertTrue($goal->isReachedByEvent(MeasureEvent::FormSubmit, 'contact-form'));
        self::assertFalse($goal->isReachedByEvent(MeasureEvent::FormSubmit, 'other'));
        self::assertFalse($goal->isReachedByEvent(MeasureEvent::Click, 'contact-form'));
        self::assertFalse($goal->isReachedByRoute('contact-form'));
    }

    public function testAGoalNameHasALimit(): void
    {
        self::assertSame(str_repeat('a', 32), Goal::onRoute(str_repeat('a', 32), 'r')->name);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('is not a goal name: 1 to 32 characters');

        Goal::onRoute(str_repeat('a', 33), 'r');
    }

    public function testAGoalNameIsChecked(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"Bad" is not a goal name');

        Goal::onEvent('Bad', MeasureEvent::Click, 'x');
    }

    public function testThePlanDefaults(): void
    {
        $plan = MeasurePlan::of();

        self::assertSame([], $plan->clickNames->toArray());
        self::assertSame([], $plan->forms->toArray());
        self::assertTrue($plan->downloads->has('pdf'));
        self::assertSame([], $plan->goals);
        self::assertSame([], $plan->dimensions);
        self::assertSame([], $plan->serverEvents->toArray());
    }

    public function testThePlanKeepsWhatItIsGiven(): void
    {
        $names = ClosedList::of(['n']);
        $forms = ClosedList::of(['f']);
        $downloads = ClosedList::of(['d']);
        $goals = [Goal::onRoute('g', 'r')];
        $dimensions = [DeclaredDimension::of('d', ClosedList::empty())];

        $events = ClosedList::of(['signup']);

        $plan = MeasurePlan::of(ClickTracking::Off, $names, $forms, $downloads, $goals, $dimensions, $events);

        self::assertSame(ClickTracking::Off, $plan->clicks);
        self::assertSame($names, $plan->clickNames);
        self::assertSame($forms, $plan->forms);
        self::assertSame($downloads, $plan->downloads);
        self::assertSame($goals, $plan->goals);
        self::assertSame($dimensions, $plan->dimensions);
        self::assertSame($events, $plan->serverEvents);
    }

    public function testAServerEventNameStaysACounterName(): void
    {
        $longest = 'e'.str_repeat('x', MeasurePlan::MAX_SERVER_EVENT_LENGTH - 1);

        self::assertTrue(MeasurePlan::of(serverEvents: ClosedList::of([$longest]))->serverEvents->has($longest));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"'.$longest.'x" cannot be a server event: it starts with a letter and holds at most 50 characters.');

        MeasurePlan::of(serverEvents: ClosedList::of([$longest.'x']));
    }

    public function testAServerEventNameStartsWithALetter(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('"1signup" cannot be a server event');

        MeasurePlan::of(serverEvents: ClosedList::of(['signup', '1signup']));
    }

    public function testThePlanBoundsItsGoals(): void
    {
        $goals = array_map(static fn (int $i): Goal => Goal::onRoute('g'.$i, 'r'), range(1, MeasurePlan::MAX_GOALS));

        self::assertCount(MeasurePlan::MAX_GOALS, MeasurePlan::of(goals: $goals)->goals);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A site declares at most 64 goals; this one declares 65.');

        MeasurePlan::of(goals: [...$goals, Goal::onRoute('more', 'r')]);
    }

    public function testThePlanBoundsItsDimensions(): void
    {
        $dimensions = array_map(static fn (int $i): DeclaredDimension => DeclaredDimension::of('d'.$i, ClosedList::empty()), range(1, MeasurePlan::MAX_DIMENSIONS));

        self::assertCount(MeasurePlan::MAX_DIMENSIONS, MeasurePlan::of(dimensions: $dimensions)->dimensions);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A site declares at most 16 dimensions; this one declares 17.');

        MeasurePlan::of(dimensions: [...$dimensions, DeclaredDimension::of('more', ClosedList::empty())]);
    }

    public function testTwoGoalsCannotShareAName(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('Two goals share a name.');

        MeasurePlan::of(goals: [Goal::onRoute('g', 'a'), Goal::onRoute('g', 'b')]);
    }

    public function testADeclaredLink(): void
    {
        $link = DeclaredOutboundLink::of(str_repeat('r', 128), str_repeat('a', 255));

        self::assertSame(str_repeat('r', 128), $link->reference);
        self::assertSame(str_repeat('a', 255), $link->author);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function badLinks(): iterable
    {
        yield 'empty reference' => ['', 'a', 'A link reference is 1 to 128 bytes'];
        yield 'long reference' => [str_repeat('r', 129), 'a', 'A link reference is 1 to 128 bytes'];
        yield 'control character' => ["r\x07", 'a', 'A link reference is 1 to 128 bytes'];
        yield 'delete character' => ["r\x7F", 'a', 'A link reference is 1 to 128 bytes'];
        yield 'empty author' => ['r', '', 'An author reference is 1 to 255 bytes'];
        yield 'long author' => ['r', str_repeat('a', 256), 'An author reference is 1 to 255 bytes'];
    }

    #[DataProvider('badLinks')]
    public function testADeclaredLinkIsChecked(string $reference, string $author, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        DeclaredOutboundLink::of($reference, $author);
    }

    public function testAConversionRateIsInBasisPoints(): void
    {
        $rate = ConversionRate::of(5, 40, SmallNumberThreshold::of(5));

        self::assertNotNull($rate);
        self::assertSame(5, $rate->conversions);
        self::assertSame(40, $rate->visits);
        self::assertSame(1250, $rate->basisPoints);
        self::assertSame(10_000, ConversionRate::of(5, 5, SmallNumberThreshold::of(5))?->basisPoints);
    }

    public function testARateOverSmallNumbersIsHidden(): void
    {
        $threshold = SmallNumberThreshold::of(5);

        self::assertNull(ConversionRate::of(4, 40, $threshold));
        self::assertNull(ConversionRate::of(40, 4, $threshold));
        self::assertNull(ConversionRate::of(0, 0, $threshold));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function negatives(): iterable
    {
        yield 'conversions' => [-1, 10];
        yield 'visits' => [10, -1];
    }

    #[DataProvider('negatives')]
    public function testANegativeCountIsRefused(int $conversions, int $visits): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('Conversions and visits are never negative.');

        ConversionRate::of($conversions, $visits, SmallNumberThreshold::of(2));
    }
}
