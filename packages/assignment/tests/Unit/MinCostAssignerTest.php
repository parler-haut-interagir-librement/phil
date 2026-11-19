<?php

declare(strict_types=1);

namespace Phil\Assignment\Tests\Unit;

use Override;
use Phil\Assignment\Applicant;
use Phil\Assignment\Contract\RankCost;
use Phil\Assignment\Cost\LinearRankCost;
use Phil\Assignment\Cost\SquaredRankCost;
use Phil\Assignment\Exception\BudgetExceeded;
use Phil\Assignment\Exception\InvalidProblem;
use Phil\Assignment\Internal\Closing;
use Phil\Assignment\Internal\Draft;
use Phil\Assignment\Internal\Meter;
use Phil\Assignment\Internal\MinCostFlow;
use Phil\Assignment\MinCostAssigner;
use Phil\Assignment\Pair;
use Phil\Assignment\PlacedBy;
use Phil\Assignment\Placement;
use Phil\Assignment\Problem;
use Phil\Assignment\RankedRounds;
use Phil\Assignment\RefusedChoice;
use Phil\Assignment\Seed;
use Phil\Assignment\Slot;
use Phil\Assignment\Tests\Unit\Fixture\Outcomes;
use Phil\Assignment\WorkBudget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use ReflectionMethod;

#[CoversClass(MinCostAssigner::class)]
#[CoversClass(MinCostFlow::class)]
#[CoversClass(Draft::class)]
#[CoversClass(Closing::class)]
#[CoversClass(Meter::class)]
#[CoversClass(LinearRankCost::class)]
#[CoversClass(SquaredRankCost::class)]
#[CoversClass(RefusedChoice::class)]
final class MinCostAssignerTest extends TestCase
{
    public function testTheCostsOfRanks(): void
    {
        self::assertSame([0, 1, 2, 3], array_map(new LinearRankCost()->cost(...), [1, 2, 3, 4]));
        self::assertSame([0, 1, 4, 9], array_map(new SquaredRankCost()->cost(...), [1, 2, 3, 4]));
    }

    public function testThePersonWhoHasNoOtherChoiceGetsTheContestedSlot(): void
    {
        $problem = new Problem([new Slot('a', 1), new Slot('b', 1)], [Applicant::person('x', ['a', 'b']), Applicant::person('y', ['a'])]);

        foreach (['one', 'two', 'three', 'four'] as $seed) {
            $result = new MinCostAssigner()->assign($problem, Seed::fromString($seed));

            self::assertSame('min-cost', $result->method);
            self::assertEquals(new Placement('y', 'a', PlacedBy::Optimisation, 1), $result->placementOf('y'));
            self::assertEquals(new Placement('x', 'b', PlacedBy::Optimisation, 2), $result->placementOf('x'));
            self::assertSame(1, $result->cost);
        }
    }

    public function testTheSquaredCostSpreadsTheDisappointment(): void
    {
        // Linear: x fourth and z first cost 3 + 0; squared: x third and z third cost 4 + 4, less than 9 + 0.
        $problem = new Problem(
            [new Slot('a', 1), new Slot('b', 1), new Slot('c', 1), new Slot('d', 1)],
            [Applicant::person('w', ['a']), Applicant::person('x', ['a', 'b', 'd', 'c']), Applicant::person('y', ['b', 'a', 'd', 'c']), Applicant::person('z', ['d', 'b', 'c'])],
        );

        $linear = new MinCostAssigner()->assign($problem, Seed::fromString('spread'));
        $squared = new MinCostAssigner(new SquaredRankCost())->assign($problem, Seed::fromString('spread'));

        self::assertSame(['a', 'c', 'b', 'd'], array_map(static fn (Placement $placement): string => $placement->slot, $linear->placements));
        self::assertSame(3, $linear->cost);
        self::assertSame(['a', 'd', 'b', 'c'], array_map(static fn (Placement $placement): string => $placement->slot, $squared->placements));
        self::assertSame(8, $squared->cost);
    }

    public function testGroupsAreSeatedFirstInDrawOrder(): void
    {
        $problem = new Problem(
            [new Slot('a', 2), new Slot('b', 2)],
            [Applicant::group('g', ['bo', 'cy'], ['a', 'b']), Applicant::group('h', ['dee', 'eve', 'fay'], ['a']), Applicant::person('ana', ['a', 'b'])],
        );

        $result = new MinCostAssigner()->assign($problem, Seed::fromString('groups'));

        self::assertEquals(new Placement('g', 'a', PlacedBy::Group, 1), $result->placementOf('g'));
        self::assertEquals(new Placement('ana', 'b', PlacedBy::Optimisation, 2), $result->placementOf('ana'));
        self::assertSame(['h' => ['a' => 'full']], Outcomes::unplaced($result));
    }

    public function testAGroupWithNoRoomAnywhereIsLeftUnplaced(): void
    {
        $problem = new Problem([new Slot('a', 1), new Slot('b', 3)], [Applicant::group('g', ['bo', 'cy'], ['a', 'b'])]);

        self::assertEquals(new Placement('g', 'b', PlacedBy::Group, 2), new MinCostAssigner()->assign($problem, Seed::fromString('g'))->placementOf('g'));
    }

    public function testForcedForbiddenAndClosedSlotsAreHonoured(): void
    {
        $problem = new Problem(
            [new Slot('a', 2), new Slot('b', 3, 2), new Slot('c', 1)],
            [Applicant::person('gm', []), Applicant::person('ana', ['a', 'c']), Applicant::person('bo', ['b', 'a']), Applicant::person('cy', ['a', 'c'])],
            [new Pair('cy', 'c')],
            [new Pair('gm', 'a')],
        );

        $result = new MinCostAssigner()->assign($problem, Seed::fromString('rules'));

        self::assertEquals(new Placement('gm', 'a', PlacedBy::Forced, null), $result->placementOf('gm'));
        self::assertSame(['b'], $result->closedSlots);
        self::assertSame('c', $result->placementOf('ana')?->slot);
        self::assertSame('a', $result->placementOf('cy')?->slot);
        self::assertSame(['bo' => ['b' => 'closed', 'a' => 'full']], Outcomes::unplaced($result));
        self::assertSame(1, $result->cost);
    }

    public function testNumericIdentifiersStayStrings(): void
    {
        $problem = new Problem(
            [new Slot('1', 1, 1), new Slot('2', 2), new Slot('3', 2, 2)],
            [Applicant::person('7', ['1', '2']), Applicant::person('8', ['1', '3']), Applicant::group('9', ['10', '11'], ['2']), Applicant::person('12', ['3'])],
            [new Pair('12', '1')],
            [new Pair('8', '1')],
        );

        foreach ([new MinCostAssigner(), new RankedRounds()] as $assigner) {
            $result = $assigner->assign($problem, Seed::fromString('7'));

            self::assertSame(['8', '1'], [$result->placementOf('8')?->applicant, $result->placementOf('8')?->slot]);
            self::assertSame(['12' => ['3' => 'closed'], '7' => ['1' => 'full', '2' => 'full']], Outcomes::unplaced($result));
            self::assertSame(['3'], $result->closedSlots);
            self::assertContains('7', $result->drawOrder);
        }
    }

    public function testNobodyToOptimise(): void
    {
        $result = new MinCostAssigner()->assign(new Problem([new Slot('a', 0)], [Applicant::person('p', ['a']), Applicant::person('q', [])]), Seed::fromString('n'));

        self::assertSame([], $result->placements);
        self::assertSame(0, $result->cost);
    }

    public function testACostOutOfBoundsIsRefused(): void
    {
        $problem = new Problem([new Slot('a', 1), new Slot('b', 1)], [Applicant::person('p', ['a', 'b'])]);

        self::assertSame(MinCostAssigner::MAX_COST, new MinCostAssigner($this->cost(MinCostAssigner::MAX_COST))->assign($problem, Seed::fromString('c'))->cost);

        foreach ([-1, MinCostAssigner::MAX_COST + 1] as $cost) {
            try {
                new MinCostAssigner($this->cost($cost))->assign($problem, Seed::fromString('c'));
                self::fail('A cost out of bounds is refused.');
            } catch (InvalidProblem $invalidProblem) {
                self::assertSame('The cost of rank 1 is 0 to 1000000.', $invalidProblem->getMessage());
            }
        }
    }

    public function testTheWorkBudgetStopsTheFlow(): void
    {
        $problem = new Problem([new Slot('a', 1), new Slot('b', 1)], [Applicant::person('p', ['a', 'b']), Applicant::person('q', ['a', 'b'])]);
        self::assertCount(2, new MinCostAssigner(budget: new WorkBudget(1_000))->assign($problem, Seed::fromString('b'))->placements);

        $this->expectException(BudgetExceeded::class);

        new MinCostAssigner(budget: new WorkBudget(1))->assign($problem, Seed::fromString('b'));
    }

    public function testTheGroupBudgetCountsEachChoiceTried(): void
    {
        $problem = new Problem([new Slot('a', 0), new Slot('b', 0)], [Applicant::group('g', ['p', 'q'], ['a', 'b'])]);

        $this->expectException(BudgetExceeded::class);

        new MinCostAssigner(budget: new WorkBudget(1))->assign($problem, Seed::fromString('b'));
    }

    public function testTheOptimumMatchesAnExhaustiveSearchOnSmallProblems(): void
    {
        $random = new Randomizer(new Mt19937(2026));

        for ($case = 0; $case < 150; ++$case) {
            $slots = [];

            for ($slot = 0; $slot < 3; ++$slot) {
                $slots[] = new Slot('s'.$slot, $random->getInt(0, 2));
            }

            $applicants = [];
            $people = $random->getInt(1, 6);

            for ($person = 0; $person < $people; ++$person) {
                $ids = $random->shuffleArray(['s0', 's1', 's2']);
                $applicants[] = Applicant::person('p'.$person, \array_slice($ids, 0, $random->getInt(0, 3)));
            }

            $forbidden = 0 === $case % 3 ? [new Pair('p0', 's1')] : [];
            $problem = new Problem($slots, $applicants, $forbidden);

            foreach ([new LinearRankCost(), new SquaredRankCost()] as $cost) {
                $result = new MinCostAssigner($cost)->assign($problem, Seed::fromString('case-'.$case));
                $best = self::exhaustive($problem, $cost, $problem->applicants, array_combine($problem->slotIds(), array_map(static fn (Slot $slot): int => $slot->capacity, $problem->slots)));

                self::assertSame($best, [\count($result->placements), $result->cost], 'Case '.$case);

                foreach ($problem->slots as $slot) {
                    self::assertLessThanOrEqual($slot->capacity, $result->occupancy($slot->id));
                }

                $rounds = new RankedRounds()->assign($problem, Seed::fromString('case-'.$case));
                self::assertLessThanOrEqual($best[0], \count($rounds->placements));
            }
        }
    }

    /**
     * The most people placed and, for that many, the lowest cost, over every assignment.
     *
     * @param list<Applicant>    $applicants
     * @param array<string, int> $seats
     *
     * @return array{int, int}
     */
    private static function exhaustive(Problem $problem, RankCost $cost, array $applicants, array $seats): array
    {
        if ([] === $applicants) {
            return [0, 0];
        }

        $applicant = array_shift($applicants);
        $best = self::exhaustive($problem, $cost, $applicants, $seats);

        foreach ($applicant->choices as $index => $choice) {
            if (0 === $seats[$choice] || $problem->isForbidden($applicant->id, $choice)) {
                continue;
            }

            --$seats[$choice];
            [$placed, $total] = self::exhaustive($problem, $cost, $applicants, $seats);
            ++$seats[$choice];
            $candidate = [$placed + 1, $total + $cost->cost($index + 1)];

            if ($candidate[0] > $best[0] || ($candidate[0] === $best[0] && $candidate[1] < $best[1])) {
                $best = $candidate;
            }
        }

        return $best;
    }

    private function cost(int $cost): RankCost
    {
        return new readonly class($cost) implements RankCost {
            public function __construct(
                private int $value,
            ) {
            }

            #[Override]
            public function cost(int $rank): int
            {
                return 1 === $rank ? $this->value : $this->value;
            }
        };
    }

    public function testADraftClosesSlotsListsWhoIsLeftAndKeepsPlacementsInOrder(): void
    {
        $problem = new Problem(
            [new Slot('a', 2), new Slot('b', 2)],
            [Applicant::person('f', ['b']), Applicant::person('x', ['b']), Applicant::person('y', ['a'])],
            [],
            [new Pair('f', 'b')],
        );
        $draft = new Draft($problem, ['a' => true]);

        self::assertSame(['a' => 0, 'b' => 2], $draft->seats);
        self::assertSame(['x', 'y'], $draft->placeForced(['x', 'f', 'y']));
        self::assertSame(1, $draft->taken('b'));

        $draft->place($problem->applicant('x'), 'b', PlacedBy::Group, null);

        self::assertSame(['f', 'x'], array_map(static fn (Placement $placement): string => $placement->applicant, $draft->placed()));
        self::assertSame([0, 1], array_keys($draft->placed()));
    }

    public function testTheWarmStartSeatsEachPersonOnceAtTheCheapestChoiceWhileThereIsRoom(): void
    {
        $flow = new MinCostFlow(7);
        $source = [$flow->addEdge(0, 1, 1, 0), $flow->addEdge(0, 2, 1, 0), $flow->addEdge(0, 3, 1, 0)];
        $person = Applicant::person('p', []);
        $choices = [];
        $edges = [];

        foreach ([[2, 4, 'a', 5], [0, 4, 'a', 2], [0, 5, 'b', 2], [1, 4, 'a', 2], [1, 5, 'b', 2], [2, 5, 'b', 2]] as $position => [$index, $node, $slot, $cost]) {
            $edges[$position] = $flow->addEdge($index + 1, $node, 1, $cost);
            $choices[$edges[$position]] = [$index, $person, $slot, $cost];
        }

        $sink = ['a' => $flow->addEdge(4, 6, 1, 0), 'b' => $flow->addEdge(5, 6, 1, 0)];

        $potential = new ReflectionMethod(MinCostAssigner::class, 'warmStart')->invoke(
            new MinCostAssigner(),
            $flow,
            $source,
            $choices,
            $sink,
            ['a' => 1, 'b' => 1],
            2,
            4,
            6,
        );

        self::assertSame([false, true, false, false, true, false], array_map($flow->isUsed(...), $edges));
        self::assertTrue($flow->isUsed($sink['a']));
        self::assertTrue($flow->isUsed($sink['b']));
        self::assertTrue($flow->isUsed($source[0]));
        self::assertTrue($flow->isUsed($source[1]));
        self::assertFalse($flow->isUsed($source[2]));
        self::assertSame([4 => 2, 5 => 2, 6 => 2], $potential);
    }

    public function testTheOptimumHoldsWhenEveryRankCostsSomething(): void
    {
        $cost = new readonly class implements RankCost {
            #[Override]
            public function cost(int $rank): int
            {
                return 3 + ($rank * $rank);
            }
        };
        $random = new Randomizer(new Mt19937(35));

        for ($case = 0; $case < 200; ++$case) {
            $slots = [];

            for ($slot = 0; $slot < 4; ++$slot) {
                $slots[] = new Slot('s'.$slot, $random->getInt(1, 2));
            }

            $applicants = [];

            for ($person = $random->getInt(2, 7); $person > 0; --$person) {
                $applicants[] = Applicant::person('p'.$person, \array_slice($random->shuffleArray(['s0', 's1', 's2', 's3']), 0, $random->getInt(1, 4)));
            }

            $problem = new Problem($slots, $applicants);
            $result = new MinCostAssigner($cost)->assign($problem, Seed::fromString('rank-'.$case));
            $best = self::exhaustive($problem, $cost, $problem->applicants, array_combine($problem->slotIds(), array_map(static fn (Slot $slot): int => $slot->capacity, $problem->slots)));

            self::assertSame($best, [\count($result->placements), $result->cost], 'Case '.$case);
        }
    }

    public function testTheWarmStartSavesWorkOnAFlowThatIsMostlyThere(): void
    {
        $slots = [];
        $applicants = [];

        for ($slot = 0; $slot < 4; ++$slot) {
            $slots[] = new Slot('s'.$slot, 3);
        }

        for ($person = 0; $person < 12; ++$person) {
            $applicants[] = Applicant::person('p'.$person, ['s'.($person % 4), 's'.(($person + 1) % 4), 's'.(($person + 2) % 4)]);
        }

        $problem = new Problem($slots, $applicants);
        $smallest = null;

        for ($budget = 1; $budget < 10_000 && null === $smallest; ++$budget) {
            try {
                new MinCostAssigner(budget: new WorkBudget($budget))->assign($problem, Seed::fromString('warm'));
                $smallest = $budget;
            } catch (BudgetExceeded) {
            }
        }

        self::assertSame(12, $smallest);
    }
}
