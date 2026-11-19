<?php

declare(strict_types=1);

namespace Phil\Assignment;

/**
 * The result of an assignment: where each applicant was placed and why, who was left without a slot and why, which
 * slots were closed for ending under their minimum, and the seed and draw order that let anyone run it again.
 */
final readonly class Assignment
{
    /** @var list<Placement> in applicant identifier order */
    public array $placements;

    /** @var list<Unplaced> in applicant identifier order */
    public array $unplaced;

    /** @var array<string, Placement> */
    private array $byApplicant;

    /**
     * @param string          $method      the assigner and its settings, such as `rounds/same` or `min-cost`
     * @param list<string>    $drawOrder   the applicant identifiers in the order the seed drew
     * @param list<Placement> $placements  in any order
     * @param list<Unplaced>  $unplaced    in any order
     * @param list<string>    $closedSlots the slots closed under their minimum, in the order they were closed
     */
    public function __construct(
        public Problem $problem,
        public string $method,
        public Seed $seed,
        public array $drawOrder,
        array $placements,
        array $unplaced,
        public array $closedSlots,
        public int $cost,
    ) {
        usort($placements, static fn (Placement $a, Placement $b): int => strcmp($a->applicant, $b->applicant));
        usort($unplaced, static fn (Unplaced $a, Unplaced $b): int => strcmp($a->applicant, $b->applicant));
        $this->placements = $placements;
        $this->unplaced = $unplaced;
        $byApplicant = [];

        foreach ($placements as $placement) {
            $byApplicant[$placement->applicant] = $placement;
        }

        $this->byApplicant = $byApplicant;
    }

    public function placementOf(string $applicant): ?Placement
    {
        return $this->byApplicant[$applicant] ?? null;
    }

    /**
     * Whether an applicant was left without a slot.
     */
    public function isUnplaced(string $applicant): bool
    {
        return array_any($this->unplaced, static fn (Unplaced $unplaced): bool => $unplaced->applicant === $applicant);
    }

    /**
     * The placement of the applicant a person belongs to, alone or in a group.
     */
    public function placementOfMember(string $member): ?Placement
    {
        foreach ($this->problem->applicants as $applicant) {
            if (\in_array($member, $applicant->members, true)) {
                return $this->placementOf($applicant->id);
            }
        }

        return null;
    }

    /**
     * The people placed in a slot, in the identifier order of their applicants.
     *
     * @return list<string>
     */
    public function membersOf(string $slot): array
    {
        $members = [];

        foreach ($this->placements as $placement) {
            if ($placement->slot === $slot) {
                array_push($members, ...$this->problem->applicant($placement->applicant)->members);
            }
        }

        return $members;
    }

    /**
     * The seats taken in a slot.
     */
    public function occupancy(string $slot): int
    {
        return \count($this->membersOf($slot));
    }

    /**
     * How many applicants got each rank of their choices, the first rank first; forced placements outside the choices
     * are left out.
     *
     * @return array<int, int>
     */
    public function rankCounts(): array
    {
        $counts = [];

        foreach ($this->placements as $placement) {
            if (null !== $placement->rank) {
                $counts[$placement->rank] = ($counts[$placement->rank] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    /**
     * The result as plain data, to keep beside the problem in an audit log.
     *
     * @return array{method: string, seed: string, draw_order: list<string>, placements: list<array{applicant: string, slot: string, placed_by: string, rank: ?int, round: ?int}>, unplaced: list<array{applicant: string, choices: list<array{slot: string, outcome: string}>}>, closed_slots: list<string>, cost: int}
     */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'seed' => $this->seed->value,
            'draw_order' => $this->drawOrder,
            'placements' => array_map(static fn (Placement $placement): array => [
                'applicant' => $placement->applicant,
                'slot' => $placement->slot,
                'placed_by' => $placement->placedBy->value,
                'rank' => $placement->rank,
                'round' => $placement->round,
            ], $this->placements),
            'unplaced' => array_map(static fn (Unplaced $unplaced): array => [
                'applicant' => $unplaced->applicant,
                'choices' => array_map(static fn (RefusedChoice $choice): array => ['slot' => $choice->slot, 'outcome' => $choice->outcome->value], $unplaced->choices),
            ], $this->unplaced),
            'closed_slots' => $this->closedSlots,
            'cost' => $this->cost,
        ];
    }
}
