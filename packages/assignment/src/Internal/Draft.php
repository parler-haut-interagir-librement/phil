<?php

declare(strict_types=1);

namespace Phil\Assignment\Internal;

use Phil\Assignment\Applicant;
use Phil\Assignment\ChoiceOutcome;
use Phil\Assignment\Contract\RankCost;
use Phil\Assignment\PlacedBy;
use Phil\Assignment\Placement;
use Phil\Assignment\Problem;
use Phil\Assignment\RefusedChoice;
use Phil\Assignment\Unplaced;

/**
 * One attempt at a problem, with some slots closed: the seats left and the placements made so far.
 *
 * @internal
 */
final class Draft
{
    /** @var array<string, int> the seats left in each slot; none in a closed one */
    public array $seats = [];

    /** @var array<string, Placement> by applicant, for lookups only */
    public array $placements = [];

    /**
     * @param array<string, true> $closed
     */
    public function __construct(
        public readonly Problem $problem,
        public readonly array $closed,
    ) {
        foreach ($problem->slots as $slot) {
            $this->seats[$slot->id] = isset($closed[$slot->id]) ? 0 : $slot->capacity;
        }
    }

    /**
     * Places every forced applicant, and gives back the others in the order given.
     *
     * @param list<string> $order
     *
     * @return list<string>
     */
    public function placeForced(array $order): array
    {
        foreach ($this->problem->forced as $pair) {
            $this->place($this->problem->applicant($pair->applicant), $pair->slot, PlacedBy::Forced, null);
        }

        return array_values(array_filter($order, fn (string $id): bool => null === $this->problem->forcedSlot($id)));
    }

    public function isAvailable(Applicant $applicant, string $slot): bool
    {
        return !isset($this->closed[$slot])
            && !$this->problem->isForbidden($applicant->id, $slot)
            && $this->seats[$slot] >= $applicant->size();
    }

    /**
     * @param positive-int|null $round
     */
    public function place(Applicant $applicant, string $slot, PlacedBy $placedBy, ?int $round): void
    {
        $this->seats[$slot] -= $applicant->size();
        $this->placements[$applicant->id] = new Placement($applicant->id, $slot, $placedBy, $applicant->rankOf($slot), $round);
    }

    /**
     * The seats taken in an open slot.
     */
    public function taken(string $slot): int
    {
        return $this->problem->slot($slot)->capacity - $this->seats[$slot];
    }

    /**
     * Every applicant without a placement, with what became of each of their choices.
     *
     * @return list<Unplaced>
     */
    public function unplaced(): array
    {
        $unplaced = [];

        foreach ($this->problem->applicants as $applicant) {
            if (isset($this->placements[$applicant->id])) {
                continue;
            }

            $choices = [];

            foreach ($applicant->choices as $choice) {
                $choices[] = new RefusedChoice($choice, match (true) {
                    $this->problem->isForbidden($applicant->id, $choice) => ChoiceOutcome::Forbidden,
                    isset($this->closed[$choice]) => ChoiceOutcome::Closed,
                    default => ChoiceOutcome::Full,
                });
            }

            $unplaced[] = new Unplaced($applicant->id, $choices);
        }

        return $unplaced;
    }

    /**
     * The placements made.
     *
     * @return list<Placement>
     */
    public function placed(): array
    {
        return array_values($this->placements);
    }

    /**
     * The total cost of the ranked placements.
     */
    public function cost(RankCost $cost): int
    {
        $total = 0;

        foreach ($this->placements as $placement) {
            if (null !== $placement->rank) {
                $total += $cost->cost($placement->rank);
            }
        }

        return $total;
    }
}
