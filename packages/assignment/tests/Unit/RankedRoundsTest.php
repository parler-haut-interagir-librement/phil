<?php

declare(strict_types=1);

namespace Phil\Assignment\Tests\Unit;

use Phil\Assignment\Applicant;
use Phil\Assignment\Assignment;
use Phil\Assignment\Exception\BudgetExceeded;
use Phil\Assignment\Internal\Closing;
use Phil\Assignment\Internal\Draft;
use Phil\Assignment\Internal\Meter;
use Phil\Assignment\Mechanism;
use Phil\Assignment\Pair;
use Phil\Assignment\PlacedBy;
use Phil\Assignment\Placement;
use Phil\Assignment\Problem;
use Phil\Assignment\RankedRounds;
use Phil\Assignment\RoundOrder;
use Phil\Assignment\Seed;
use Phil\Assignment\Slot;
use Phil\Assignment\Tests\Unit\Fixture\Outcomes;
use Phil\Assignment\Unplaced;
use Phil\Assignment\WorkBudget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RankedRounds::class)]
#[CoversClass(Draft::class)]
#[CoversClass(Closing::class)]
#[CoversClass(Meter::class)]
#[CoversClass(Placement::class)]
#[CoversClass(Unplaced::class)]
final class RankedRoundsTest extends TestCase
{
    public function testTheFirstDrawnGetsTheContestedFirstChoiceAndTheOtherWaitsForRoundTwo(): void
    {
        $problem = new Problem([new Slot('a', 1), new Slot('b', 1)], [Applicant::person('x', ['a', 'b']), Applicant::person('y', ['a', 'b'])]);
        $result = new RankedRounds()->assign($problem, Seed::fromString('s'));
        [$first, $second] = $result->drawOrder;

        self::assertSame('rounds/same', $result->method);
        self::assertEquals(new Placement($first, 'a', PlacedBy::Round, 1, 1), $result->placementOf($first));
        self::assertEquals(new Placement($second, 'b', PlacedBy::Round, 2, 2), $result->placementOf($second));
        self::assertSame([], $result->unplaced);
        self::assertSame([], $result->closedSlots);
        self::assertSame(1, $result->cost);
    }

    public function testRoundsLoseAChoiceThatFilledWhileSerialDictatorshipTakesTheNextOneWithRoom(): void
    {
        $problem = new Problem(
            [new Slot('a', 1), new Slot('b', 1)],
            [Applicant::person('x', ['a', 'b']), Applicant::person('z', ['a', 'b']), Applicant::person('y', ['b'])],
        );
        $seed = $this->seedWhere($problem, static fn (array $order): bool => 'y' === $order[2]);

        $rounds = new RankedRounds()->assign($problem, $seed);
        $loser = $rounds->drawOrder[1];
        self::assertSame('y', $rounds->drawOrder[2]);
        self::assertSame('b', $rounds->placementOf('y')?->slot);
        self::assertSame([$loser => ['a' => 'full', 'b' => 'full']], Outcomes::unplaced($rounds));

        $serial = new RankedRounds(Mechanism::SerialDictatorship)->assign($problem, $seed);
        self::assertSame('serial-dictatorship', $serial->method);
        self::assertEquals(new Placement($loser, 'b', PlacedBy::Round, 2, 1), $serial->placementOf($loser));
        self::assertSame(['y' => ['b' => 'full']], Outcomes::unplaced($serial));
    }

    public function testAlternatingRoundsServeTheLastDrawnFirstInEvenRounds(): void
    {
        $problem = new Problem(
            [new Slot('a', 1), new Slot('b', 1), new Slot('c', 1)],
            [Applicant::person('w', ['a', 'b']), Applicant::person('x', ['a', 'b']), Applicant::person('y', ['c', 'b']), Applicant::person('z', ['c', 'b'])],
        );
        $seed = Seed::fromString('alternating');
        $same = new RankedRounds()->assign($problem, $seed);
        $alternating = new RankedRounds(order: RoundOrder::Alternating)->assign($problem, $seed);
        $losers = array_values(array_filter($same->drawOrder, static fn (string $id): bool => 2 === $same->placementOf($id)?->rank || $same->isUnplaced($id)));

        self::assertSame('rounds/alternating', $alternating->method);
        self::assertCount(2, $losers);
        self::assertSame(2, $same->placementOf($losers[0])?->round);
        self::assertTrue($same->isUnplaced($losers[1]));
        self::assertSame(2, $alternating->placementOf($losers[1])?->round);
        self::assertTrue($alternating->isUnplaced($losers[0]));
    }

    public function testAGroupTakesAsManySeatsAsItHasMembers(): void
    {
        $problem = new Problem(
            [new Slot('a', 2), new Slot('b', 3)],
            [Applicant::group('g', ['bo', 'cy', 'dee'], ['a', 'b']), Applicant::person('ana', ['b', 'a'])],
        );
        $seed = $this->seedWhere($problem, static fn (array $order): bool => 'g' === $order[0]);

        $result = new RankedRounds(Mechanism::SerialDictatorship)->assign($problem, $seed);

        self::assertEquals(new Placement('g', 'b', PlacedBy::Round, 2, 1), $result->placementOf('g'));
        self::assertEquals(new Placement('ana', 'a', PlacedBy::Round, 2, 1), $result->placementOf('ana'));

        $exact = new RankedRounds()->assign(new Problem([new Slot('a', 2)], [Applicant::group('g', ['bo', 'cy'], ['a'])]), $seed);
        self::assertSame('a', $exact->placementOf('g')?->slot);
    }

    public function testForcedApplicantsComeFirstAndForbiddenChoicesAreSkipped(): void
    {
        $problem = new Problem(
            [new Slot('a', 2), new Slot('b', 1)],
            [Applicant::person('gm', []), Applicant::person('ana', ['a', 'b']), Applicant::person('bo', ['b', 'a']), Applicant::person('cy', ['b'])],
            [new Pair('bo', 'b')],
            [new Pair('gm', 'a'), new Pair('cy', 'b')],
        );

        $seed = $this->seedWhere($problem, static fn (array $order): bool => array_search('ana', $order, true) < array_search('bo', $order, true));

        foreach ([new RankedRounds(), new RankedRounds(Mechanism::SerialDictatorship)] as $assigner) {
            $result = $assigner->assign($problem, $seed);

            self::assertEquals(new Placement('gm', 'a', PlacedBy::Forced, null), $result->placementOf('gm'));
            self::assertEquals(new Placement('cy', 'b', PlacedBy::Forced, 1), $result->placementOf('cy'));
            self::assertSame('a', $result->placementOf('ana')?->slot);
            self::assertSame(['bo' => ['b' => 'forbidden', 'a' => 'full']], Outcomes::unplaced($result));
            self::assertSame(['ana', 'cy', 'gm'], Outcomes::placed($result));
        }
    }

    public function testApplicantsWithoutChoicesOrWithFewerChoicesStopWaiting(): void
    {
        $problem = new Problem(
            [new Slot('a', 1), new Slot('b', 1), new Slot('b2', 1)],
            [Applicant::person('none', []), Applicant::person('x', ['a']), Applicant::person('y', ['a']), Applicant::person('z', ['b', 'b2'])],
        );

        $result = new RankedRounds()->assign($problem, Seed::fromString('x'));

        self::assertEquals(new Unplaced('none', []), $result->unplaced[0]);
        self::assertCount(2, $result->unplaced);
        self::assertSame('b', $result->placementOf('z')?->slot);

        $nobody = new RankedRounds()->assign(new Problem([new Slot('a', 1)], [Applicant::person('n', [])]), Seed::fromString('x'));
        self::assertSame(['n' => []], Outcomes::unplaced($nobody));
    }

    public function testASlotUnderItsMinimumIsClosedAndItsPeoplePlacedAgain(): void
    {
        $problem = new Problem(
            [new Slot('big', 4, 2), new Slot('empty', 3, 2), new Slot('small', 4, 2), new Slot('tiny', 4, 3)],
            [
                Applicant::person('ana', ['small', 'big']),
                Applicant::person('bo', ['big', 'small']),
                Applicant::person('cy', ['big']),
                Applicant::person('dee', ['tiny', 'small']),
                Applicant::person('eve', ['tiny', 'small']),
            ],
        );

        $result = new RankedRounds()->assign($problem, Seed::fromString('minimum'));

        self::assertSame(['small', 'tiny'], $result->closedSlots);
        self::assertSame(2, $result->placementOf('ana')?->rank);
        self::assertSame(['ana', 'bo', 'cy'], Outcomes::placed($result));
        self::assertSame(['dee' => ['tiny' => 'closed', 'small' => 'closed'], 'eve' => ['tiny' => 'closed', 'small' => 'closed']], Outcomes::unplaced($result));
        self::assertSame(0, $result->occupancy('empty'));
    }

    public function testTheSlotFurthestUnderItsMinimumIsClosedFirstAndTiesGoToTheFirstIdentifier(): void
    {
        $problem = new Problem(
            [new Slot('a', 5, 3), new Slot('b', 5, 3), new Slot('c', 5, 3)],
            [
                Applicant::person('p1', ['b', 'a']),
                Applicant::person('p2', ['b', 'a']),
                Applicant::person('p3', ['c', 'a']),
                Applicant::person('p4', ['a']),
            ],
        );

        $result = new RankedRounds()->assign($problem, Seed::fromString('ties'));

        self::assertSame(['a', 'c', 'b'], $result->closedSlots);
        self::assertSame([], $result->placements);
        self::assertSame(['c' => 'closed', 'a' => 'closed'], Outcomes::unplaced($result)['p3']);
    }

    public function testASlotHoldingAForcedApplicantIsNeverClosed(): void
    {
        $problem = new Problem([new Slot('a', 4, 3)], [Applicant::person('gm', []), Applicant::person('p', ['a'])], [], [new Pair('gm', 'a')]);

        $result = new RankedRounds()->assign($problem, Seed::fromString('kept'));

        self::assertSame([], $result->closedSlots);
        self::assertSame(2, $result->occupancy('a'));
    }

    public function testASlotAtItsMinimumStaysOpen(): void
    {
        $problem = new Problem([new Slot('a', 4, 2)], [Applicant::person('p', ['a']), Applicant::person('q', ['a'])]);

        self::assertSame([], new RankedRounds()->assign($problem, Seed::fromString('open'))->closedSlots);
    }

    public function testTheResultDoesNotDependOnTheOrderOfTheInput(): void
    {
        $slots = [new Slot('a', 2, 2), new Slot('b', 1), new Slot('c', 2)];
        $applicants = [Applicant::person('p', ['a', 'b', 'c']), Applicant::person('q', ['b', 'c']), Applicant::person('r', ['b', 'a']), Applicant::group('g', ['s', 't'], ['c', 'a'])];
        $seed = Seed::fromString('order');

        foreach ([Mechanism::Rounds, Mechanism::SerialDictatorship] as $mechanism) {
            $assigner = new RankedRounds($mechanism, RoundOrder::Alternating);

            self::assertSame(
                $assigner->assign(new Problem($slots, $applicants), $seed)->toArray(),
                $assigner->assign(new Problem(array_reverse($slots), array_reverse($applicants)), $seed)->toArray(),
            );
        }
    }

    public function testTheWorkBudgetStopsARun(): void
    {
        $problem = new Problem([new Slot('a', 0), new Slot('b', 0)], [Applicant::person('p', ['a', 'b'])]);

        self::assertCount(1, new RankedRounds(budget: new WorkBudget(2))->assign($problem, Seed::fromString('b'))->unplaced);
        self::assertCount(1, new RankedRounds(Mechanism::SerialDictatorship, budget: new WorkBudget(2))->assign($problem, Seed::fromString('b'))->unplaced);

        $this->expectException(BudgetExceeded::class);
        $this->expectExceptionMessageIs('The assignment stopped after 1 operations, the limit of its work budget.');

        new RankedRounds(budget: new WorkBudget(1))->assign($problem, Seed::fromString('b'));
    }

    public function testTheSerialWorkBudgetCountsEachChoiceTried(): void
    {
        $problem = new Problem([new Slot('a', 0), new Slot('b', 0)], [Applicant::person('p', ['a', 'b'])]);

        $this->expectException(BudgetExceeded::class);

        new RankedRounds(Mechanism::SerialDictatorship, budget: new WorkBudget(1))->assign($problem, Seed::fromString('b'));
    }

    /**
     * A seed whose draw order passes the test, among the first thousand.
     *
     * @param callable(list<string>): bool $wanted
     */
    private function seedWhere(Problem $problem, callable $wanted): Seed
    {
        for ($attempt = 0; $attempt < 1000; ++$attempt) {
            $seed = Seed::fromString('seed-'.$attempt);

            if ($wanted($seed->shuffle($problem->applicantIds()))) {
                return $seed;
            }
        }

        self::fail('No seed draws the wanted order.');
    }

    public function testTheResultIsAnAssignment(): void
    {
        self::assertInstanceOf(Assignment::class, new RankedRounds()->assign(new Problem([], []), Seed::fromString('e')));
    }

    public function testAnApplicantOutOfChoicesDoesNotStopTheOthersOfTheRound(): void
    {
        $problem = new Problem(
            [new Slot('a', 1), new Slot('b', 1)],
            [Applicant::person('w', ['a']), Applicant::person('x', ['a']), Applicant::person('y', ['a', 'b'])],
        );
        $seed = $this->seedWhere($problem, static fn (array $order): bool => ['w', 'x', 'y'] === $order);

        $result = new RankedRounds()->assign($problem, $seed);

        self::assertEquals(new Placement('y', 'b', PlacedBy::Round, 2, 2), $result->placementOf('y'));
        self::assertSame(['x' => ['a' => 'full']], Outcomes::unplaced($result));
    }

    public function testThoseWhoWaitKeepTheirDrawOrderFromRoundToRound(): void
    {
        $problem = new Problem(
            [new Slot('a', 1), new Slot('b', 1), new Slot('c', 1)],
            [Applicant::person('u', ['a']), Applicant::person('v', ['b']), Applicant::person('x', ['a', 'b', 'c']), Applicant::person('y', ['a', 'b', 'c'])],
        );
        $seed = $this->seedWhere($problem, static fn (array $order): bool => ['u', 'v', 'x', 'y'] === $order);

        $result = new RankedRounds(order: RoundOrder::Alternating)->assign($problem, $seed);

        self::assertEquals(new Placement('x', 'c', PlacedBy::Round, 3, 3), $result->placementOf('x'));
        self::assertTrue($result->isUnplaced('y'));
    }
}
