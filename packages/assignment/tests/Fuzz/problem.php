<?php

declare(strict_types=1);

/*
 * The reader of problems and both assigners, on JSON nobody wrote: the reader returns a problem or throws
 * InvalidProblem; an assigner returns a result or throws BudgetExceeded; every result keeps the capacities, places each
 * applicant at most once, never in a forbidden slot, and every forced applicant in its slot.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Assignment\Exception\BudgetExceeded;
use Phil\Assignment\Exception\InvalidProblem;
use Phil\Assignment\Limits;
use Phil\Assignment\Mechanism;
use Phil\Assignment\MinCostAssigner;
use Phil\Assignment\ProblemReader;
use Phil\Assignment\RankedRounds;
use Phil\Assignment\RoundOrder;
use Phil\Assignment\Seed;
use Phil\Assignment\WorkBudget;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new ProblemReader(new Limits(slots: 20, applicants: 50, choices: 5, members: 5));
$budget = new WorkBudget(200_000);
$assigners = [
    new RankedRounds(budget: $budget),
    new RankedRounds(Mechanism::Rounds, RoundOrder::Alternating, $budget),
    new RankedRounds(Mechanism::SerialDictatorship, budget: $budget),
    new MinCostAssigner(budget: $budget),
];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $assigners): void {
    try {
        $problem = $reader->fromJson($input);
    } catch (InvalidProblem) {
        return;
    }

    foreach ($assigners as $assigner) {
        try {
            $result = $assigner->assign($problem, Seed::fromString('fuzz'));
        } catch (BudgetExceeded) {
            continue;
        }

        if (count($result->placements) + count($result->unplaced) !== count($problem->applicants)) {
            throw new LogicException('An applicant is neither placed nor unplaced, or both.');
        }

        foreach ($problem->slots as $slot) {
            if ($result->occupancy($slot->id) > $slot->capacity) {
                throw new LogicException('A slot holds more than its capacity.');
            }
        }

        foreach ($result->placements as $placement) {
            if ($problem->isForbidden($placement->applicant, $placement->slot) || in_array($placement->slot, $result->closedSlots, true)) {
                throw new LogicException('An applicant is placed in a forbidden or closed slot.');
            }
        }

        foreach ($problem->forced as $pair) {
            if ($result->placementOf($pair->applicant)?->slot !== $pair->slot) {
                throw new LogicException('A forced applicant is not in its slot.');
            }
        }
    }
});
$config->setMaxLen(4096);
