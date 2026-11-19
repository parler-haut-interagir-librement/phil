<?php

declare(strict_types=1);

namespace Phil\Assignment\Tests\Benchmark;

use Phil\Assignment\Applicant;
use Phil\Assignment\Mechanism;
use Phil\Assignment\MinCostAssigner;
use Phil\Assignment\Problem;
use Phil\Assignment\RankedRounds;
use Phil\Assignment\Seed;
use Phil\Assignment\Slot;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A thousand people ranking five of a hundred slots of ten seats, the popular slots wanted far more than the others;
 * with no minimum, and with a minimum of three seats per slot, which closes slots and runs the assignment again.
 */
final readonly class AssignmentBench
{
    private Problem $open;

    private Problem $minimums;

    private Seed $seed;

    public function __construct()
    {
        $this->open = $this->problem(0);
        $this->minimums = $this->problem(3);
        $this->seed = Seed::fromString('bench');
    }

    public function benchRounds(): void
    {
        new RankedRounds()->assign($this->open, $this->seed);
    }

    public function benchRoundsWithMinimums(): void
    {
        new RankedRounds()->assign($this->minimums, $this->seed);
    }

    public function benchSerialDictatorship(): void
    {
        new RankedRounds(Mechanism::SerialDictatorship)->assign($this->open, $this->seed);
    }

    public function benchMinCost(): void
    {
        new MinCostAssigner()->assign($this->open, $this->seed);
    }

    public function benchMinCostWithMinimums(): void
    {
        new MinCostAssigner()->assign($this->minimums, $this->seed);
    }

    private function problem(int $minimum): Problem
    {
        $slots = [];

        for ($slot = 0; $slot < 100; ++$slot) {
            $slots[] = new Slot('s'.$slot, 10, $minimum);
        }

        $applicants = [];
        $random = new Randomizer(new Mt19937(7));

        for ($person = 0; $person < 1_000; ++$person) {
            $choices = [];

            while (\count($choices) < 5) {
                $choice = 's'.intdiv($random->getInt(0, 99) * $random->getInt(0, 99), 99);

                if (!\in_array($choice, $choices, true)) {
                    $choices[] = $choice;
                }
            }

            $applicants[] = Applicant::person('p'.$person, $choices);
        }

        return new Problem($slots, $applicants);
    }
}
