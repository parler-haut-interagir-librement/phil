<?php

declare(strict_types=1);

namespace Phil\Assignment;

use Override;
use Phil\Assignment\Contract\Assigner;
use Phil\Assignment\Contract\RankCost;
use Phil\Assignment\Cost\LinearRankCost;
use Phil\Assignment\Exception\InvalidProblem;
use Phil\Assignment\Internal\Closing;
use Phil\Assignment\Internal\Draft;
use Phil\Assignment\Internal\Meter;
use Phil\Assignment\Internal\MinCostFlow;

/**
 * The global optimisation: forced applicants first; then groups, in draw order, each at its best choice with room;
 * then every person on their own at once, by a minimum-cost maximum flow over the seats left. The flow places as many
 * of them as can be placed, and among those placements chooses the lowest total rank cost; among placements of equal
 * cost, the draw order decides. Under minimums, the slot furthest below its minimum is closed and the whole run made
 * again without it.
 *
 * One run costs O(P · E log V) for P people on their own, E their ranked choices plus P plus the slots, V the
 * applicants plus the slots: about a second for a thousand people ranking five of a hundred slots. The work budget
 * stops a larger one.
 *
 * The assigner keeps no state between two calls, and is safe to share across the requests of a long-running worker.
 */
final readonly class MinCostAssigner implements Assigner
{
    public const int MAX_COST = 1_000_000;

    public function __construct(
        private RankCost $cost = new LinearRankCost(),
        private WorkBudget $budget = new WorkBudget(),
    ) {
    }

    #[Override]
    public function assign(Problem $problem, Seed $seed): Assignment
    {
        $meter = new Meter($this->budget);
        $drawOrder = $seed->shuffle($problem->applicantIds());
        $costs = $this->costs($problem);

        [$draft, $closed] = Closing::solve(
            $problem,
            fn (array $closed): Draft => $this->run(new Draft($problem, $closed), $drawOrder, $costs, $meter),
        );

        return new Assignment($problem, 'min-cost', $seed, $drawOrder, $draft->placed(), $draft->unplaced(), $closed, $draft->cost($this->cost));
    }

    /**
     * The cost of each rank any applicant uses, checked once.
     *
     * @return array<int, int>
     */
    private function costs(Problem $problem): array
    {
        $ranks = 0;

        foreach ($problem->applicants as $applicant) {
            $ranks = max($ranks, \count($applicant->choices));
        }

        $costs = [];

        for ($rank = 1; $rank <= $ranks; ++$rank) {
            $cost = $this->cost->cost($rank);

            if ($cost < 0 || $cost > self::MAX_COST) {
                throw InvalidProblem::because(\sprintf('The cost of rank %d is 0 to %d.', $rank, self::MAX_COST));
            }

            $costs[$rank] = $cost;
        }

        return $costs;
    }

    /**
     * @param list<string>    $drawOrder
     * @param array<int, int> $costs
     */
    private function run(Draft $draft, array $drawOrder, array $costs, Meter $meter): Draft
    {
        $people = [];

        foreach ($draft->placeForced($drawOrder) as $id) {
            $applicant = $draft->problem->applicant($id);

            if (1 === $applicant->size()) {
                $people[] = $applicant;

                continue;
            }

            foreach ($applicant->choices as $choice) {
                $meter->spend();

                if ($draft->isAvailable($applicant, $choice)) {
                    $draft->place($applicant, $choice, PlacedBy::Group, null);

                    break;
                }
            }
        }

        $slots = $draft->problem->slotIds();
        $slotNode = array_flip($slots);
        $count = \count($people);
        $sink = $count + \count($slots) + 1;
        $flow = new MinCostFlow($sink + 1);
        $sourceEdges = [];
        $choiceEdges = [];
        $sinkEdges = [];
        $cheapest = \PHP_INT_MAX;

        foreach ($people as $index => $person) {
            $sourceEdges[$index] = $flow->addEdge(0, $index + 1, 1, 0);

            foreach ($person->choices as $position => $choice) {
                if ($draft->isAvailable($person, $choice)) {
                    $choiceEdges[$flow->addEdge($index + 1, $count + 1 + $slotNode[$choice], 1, $costs[$position + 1])] = [$index, $person, $choice, $costs[$position + 1]];
                    $cheapest = min($cheapest, $costs[$position + 1]);
                }
            }
        }

        $seats = [];

        foreach ($slots as $node => $slot) {
            if ($draft->seats[$slot] > 0) {
                $sinkEdges[$slot] = $flow->addEdge($count + 1 + $node, $sink, $draft->seats[$slot], 0);
                $seats[$slot] = $draft->seats[$slot];
            }
        }

        $flow->solve(0, $sink, $meter, [] === $choiceEdges ? [] : $this->warmStart($flow, $sourceEdges, $choiceEdges, $sinkEdges, $seats, $cheapest, $count + 1, $sink));

        foreach ($choiceEdges as $edge => [, $person, $choice]) {
            if ($flow->isUsed($edge)) {
                $draft->place($person, $choice, PlacedBy::Optimisation, null);
            }
        }

        return $draft;
    }

    /**
     * Seats each person, in draw order, at a choice of the cheapest cost of the whole network while one has room. Every
     * residual cycle then costs zero or more, so the flow is of the lowest cost for its value, and the potentials —
     * that cost on the slots and the sink, zero elsewhere — keep every residual cost non-negative for Dijkstra.
     *
     * @param array<int, int>                                $sourceEdges by person
     * @param array<int, array{int, Applicant, string, int}> $choiceEdges by edge: person, applicant, slot, cost
     * @param array<string, int>                             $sinkEdges   by slot
     * @param array<string, int>                             $seats       the seats of each slot with room
     *
     * @return array<int, int>
     */
    private function warmStart(MinCostFlow $flow, array $sourceEdges, array $choiceEdges, array $sinkEdges, array $seats, int $cheapest, int $firstSlot, int $sink): array
    {
        $seated = [];

        foreach ($choiceEdges as $edge => [$index, , $slot, $cost]) {
            if ($cost !== $cheapest || isset($seated[$index]) || 0 === $seats[$slot]) {
                continue;
            }

            $flow->push([$sourceEdges[$index], $edge, $sinkEdges[$slot]]);
            --$seats[$slot];
            $seated[$index] = true;
        }

        $potential = [];

        foreach (range($firstSlot, $sink) as $node) {
            $potential[$node] = $cheapest;
        }

        return $potential;
    }
}
