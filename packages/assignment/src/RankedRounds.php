<?php

declare(strict_types=1);

namespace Phil\Assignment;

use Override;
use Phil\Assignment\Contract\Assigner;
use Phil\Assignment\Contract\RankCost;
use Phil\Assignment\Cost\LinearRankCost;
use Phil\Assignment\Internal\Closing;
use Phil\Assignment\Internal\Draft;
use Phil\Assignment\Internal\Meter;

/**
 * Assignment by ranked choices, served in an order the seed draws: forced applicants first, then rounds of choices or
 * one pass of best available choices, as the mechanism says. Each placement names its round and rank. Under minimums,
 * the slot furthest below its minimum is closed and the whole draw run again without it.
 *
 * It does at most `slots + 1` runs of `applicants × choices` steps. The rank cost only prices the result, for
 * comparison with the global optimisation.
 *
 * The assigner keeps no state between two calls, and is safe to share across the requests of a long-running worker.
 */
final readonly class RankedRounds implements Assigner
{
    public function __construct(
        private Mechanism $mechanism = Mechanism::Rounds,
        private RoundOrder $order = RoundOrder::Same,
        private WorkBudget $budget = new WorkBudget(),
        private RankCost $cost = new LinearRankCost(),
    ) {
    }

    #[Override]
    public function assign(Problem $problem, Seed $seed): Assignment
    {
        $meter = new Meter($this->budget);
        $drawOrder = $seed->shuffle($problem->applicantIds());

        [$draft, $closed] = Closing::solve(
            $problem,
            fn (array $closed): Draft => $this->run(new Draft($problem, $closed), $drawOrder, $meter),
        );

        return new Assignment($problem, $this->method(), $seed, $drawOrder, $draft->placed(), $draft->unplaced(), $closed, $draft->cost($this->cost));
    }

    /**
     * @param list<string> $drawOrder
     */
    private function run(Draft $draft, array $drawOrder, Meter $meter): Draft
    {
        $waiting = $draft->placeForced($drawOrder);

        if (Mechanism::SerialDictatorship === $this->mechanism) {
            foreach ($waiting as $id) {
                $applicant = $draft->problem->applicant($id);

                foreach ($applicant->choices as $choice) {
                    $meter->spend();

                    if ($draft->isAvailable($applicant, $choice)) {
                        $draft->place($applicant, $choice, PlacedBy::Round, 1);

                        break;
                    }
                }
            }

            return $draft;
        }

        for ($round = 1; [] !== $waiting; ++$round) {
            $next = [];
            $turn = RoundOrder::Alternating === $this->order && 0 === $round % 2 ? array_reverse($waiting) : $waiting;

            foreach ($turn as $id) {
                $applicant = $draft->problem->applicant($id);
                $choice = $applicant->choices[$round - 1] ?? null;

                if (null === $choice) {
                    continue;
                }

                $meter->spend();

                if ($draft->isAvailable($applicant, $choice)) {
                    $draft->place($applicant, $choice, PlacedBy::Round, $round);
                } else {
                    $next[] = $id;
                }
            }

            $waiting = array_values(array_intersect($waiting, $next));
        }

        return $draft;
    }

    private function method(): string
    {
        return Mechanism::SerialDictatorship === $this->mechanism ? $this->mechanism->value : $this->mechanism->value.'/'.$this->order->value;
    }
}
