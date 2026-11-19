<?php

declare(strict_types=1);

namespace Phil\Assignment;

use Phil\Assignment\Exception\InvalidProblem;

/**
 * What is to be assigned: the slots, the applicants with their ranked choices, the pairs the organiser forbids or
 * forces. It is checked once, whole, at construction, against its limits; slots and applicants are kept in the order
 * of their identifiers, so that the order they were given in never changes a result.
 */
final readonly class Problem
{
    /** @var list<Slot> in identifier order */
    public array $slots;

    /** @var list<Applicant> in identifier order */
    public array $applicants;

    /** @var array<string, Slot> */
    private array $slotById;

    /** @var array<string, Applicant> */
    private array $applicantById;

    /** @var list<Pair> the pairs forbidden, as given */
    public array $forbidden;

    /** @var list<Pair> the pairs forced, one per applicant, in applicant identifier order */
    public array $forced;

    /** @var array<string, array<string, true>> */
    private array $forbiddenSlots;

    /** @var array<string, string> */
    private array $forcedSlots;

    /**
     * @param list<Slot>      $slots
     * @param list<Applicant> $applicants
     * @param list<Pair>      $forbidden
     * @param list<Pair>      $forced
     *
     * @throws InvalidProblem
     */
    public function __construct(array $slots, array $applicants, array $forbidden = [], array $forced = [], Limits $limits = new Limits())
    {
        if (\count($slots) > $limits->slots) {
            throw InvalidProblem::because(\sprintf('A problem has at most %d slots.', $limits->slots));
        }

        if (\count($applicants) > $limits->applicants) {
            throw InvalidProblem::because(\sprintf('A problem has at most %d applicants.', $limits->applicants));
        }

        $this->slotById = $this->slotsById($slots);
        $this->slots = array_values($this->slotById);
        $this->applicantById = $this->applicantsById($applicants, $limits);
        $this->applicants = array_values($this->applicantById);
        $this->forbiddenSlots = $this->forbiddenPairs($forbidden);
        $this->forbidden = $forbidden;
        $this->forcedSlots = $this->forcedPairs($forced);
        $unique = [];

        foreach ($forced as $pair) {
            $unique[$pair->applicant] = $pair;
        }

        usort($unique, static fn (Pair $a, Pair $b): int => strcmp($a->applicant, $b->applicant));
        $this->forced = $unique;
    }

    /**
     * The slot identifiers, in order.
     *
     * @return list<string>
     */
    public function slotIds(): array
    {
        return array_map(static fn (Slot $slot): string => $slot->id, $this->slots);
    }

    /**
     * The applicant identifiers, in order.
     *
     * @return list<string>
     */
    public function applicantIds(): array
    {
        return array_map(static fn (Applicant $applicant): string => $applicant->id, $this->applicants);
    }

    /**
     * The slot an applicant is forced into, if any.
     */
    public function forcedSlot(string $applicant): ?string
    {
        return $this->forcedSlots[$applicant] ?? null;
    }

    public function applicant(string $id): Applicant
    {
        return $this->applicantById[$id] ?? throw InvalidProblem::because(\sprintf('The problem has no applicant "%s".', $id));
    }

    public function slot(string $id): Slot
    {
        return $this->slotById[$id] ?? throw InvalidProblem::because(\sprintf('The problem has no slot "%s".', $id));
    }

    public function isForbidden(string $applicant, string $slot): bool
    {
        return isset($this->forbiddenSlots[$applicant][$slot]);
    }

    /**
     * @param list<Slot> $slots
     *
     * @return array<string, Slot>
     */
    private function slotsById(array $slots): array
    {
        $byId = [];

        foreach ($slots as $slot) {
            if (isset($byId[$slot->id])) {
                throw InvalidProblem::because(\sprintf('Slot "%s" is given twice.', $slot->id));
            }

            $byId[$slot->id] = $slot;
        }

        ksort($byId, \SORT_STRING);

        return $byId;
    }

    /**
     * @param list<Applicant> $applicants
     *
     * @return array<string, Applicant>
     */
    private function applicantsById(array $applicants, Limits $limits): array
    {
        $byId = [];
        $members = [];

        foreach ($applicants as $applicant) {
            if (isset($byId[$applicant->id])) {
                throw InvalidProblem::because(\sprintf('Applicant "%s" is given twice.', $applicant->id));
            }

            if (\count($applicant->choices) > $limits->choices) {
                throw InvalidProblem::because(\sprintf('Applicant "%s" ranks more than %d slots.', $applicant->id, $limits->choices));
            }

            if ($applicant->size() > $limits->members) {
                throw InvalidProblem::because(\sprintf('Applicant "%s" has more than %d members.', $applicant->id, $limits->members));
            }

            foreach ($applicant->choices as $choice) {
                $this->slot($choice);
            }

            foreach ($applicant->members as $member) {
                if (isset($members[$member])) {
                    throw InvalidProblem::because(\sprintf('Member "%s" belongs to two applicants.', $member));
                }

                $members[$member] = true;
            }

            $byId[$applicant->id] = $applicant;
        }

        ksort($byId, \SORT_STRING);

        return $byId;
    }

    /**
     * @param list<Pair> $pairs
     *
     * @return array<string, array<string, true>>
     */
    private function forbiddenPairs(array $pairs): array
    {
        $forbidden = [];

        foreach ($pairs as $pair) {
            $this->applicant($pair->applicant);
            $this->slot($pair->slot);
            $forbidden[$pair->applicant][$pair->slot] = true;
        }

        return $forbidden;
    }

    /**
     * @param list<Pair> $pairs
     *
     * @return array<string, string>
     */
    private function forcedPairs(array $pairs): array
    {
        $forced = [];
        $seats = [];

        foreach ($pairs as $pair) {
            $applicant = $this->applicant($pair->applicant);
            $slot = $this->slot($pair->slot);

            if (isset($forced[$pair->applicant]) && $forced[$pair->applicant] !== $pair->slot) {
                throw InvalidProblem::because(\sprintf('Applicant "%s" is forced into two slots.', $pair->applicant));
            }

            if (isset($this->forbiddenSlots[$pair->applicant][$pair->slot])) {
                throw InvalidProblem::because(\sprintf('Applicant "%s" is both forced into and forbidden from slot "%s".', $pair->applicant, $pair->slot));
            }

            if (!isset($forced[$pair->applicant])) {
                $seats[$slot->id] = ($seats[$slot->id] ?? 0) + $applicant->size();
            }

            if ($seats[$slot->id] > $slot->capacity) {
                throw InvalidProblem::because(\sprintf('The applicants forced into slot "%s" exceed its capacity.', $slot->id));
            }

            $forced[$pair->applicant] = $pair->slot;
        }

        return $forced;
    }
}
