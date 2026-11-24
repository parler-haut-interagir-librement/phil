<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain\Tally;

use Phil\Poll\Ballot;
use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\PollRules;
use Phil\Poll\Result;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\Slot;

/**
 * The answers of a scheduling poll counted by the voting engine of `phil/poll`: the proposed dates are the options of
 * a multiple ballot that accepts a blank one, every respondent casts two ballots — the dates they answered yes to, and
 * those they answered if need be to — and the count of choices counts each.
 *
 * The dates are then ordered by the tie-break of a scheduling poll: the most yes answers first, then the most
 * if-need-be answers, then the earliest start. Dates with the same yes and if-need-be answers share their place; the
 * earliest start only orders them in the list and picks the date at the deadline. Revoked respondents and those who
 * have not answered are not counted. The count reads every respondent once and keeps no state.
 */
final readonly class AvailabilityCount
{
    public function __construct(
        private ChoiceCount $count = new ChoiceCount(),
    ) {
    }

    /**
     * @param list<Slot>           $slots       two to a hundred, as a scheduling poll proposes them
     * @param iterable<Respondent> $respondents
     *
     * @return list<SlotTally> the best date first
     */
    public function count(array $slots, iterable $respondents): array
    {
        $rules = PollRules::multiple(array_map(static fn (Slot $slot): string => $slot->id, $slots), abstention: true);
        $yes = [];
        $ifNeedBe = [];

        foreach ($respondents as $respondent) {
            if ($respondent->isRevoked() || !$respondent->hasAnswered()) {
                continue;
            }

            $yes[] = $this->ballot($rules, $respondent, Availability::Yes);
            $ifNeedBe[] = $this->ballot($rules, $respondent, Availability::IfNeedBe);
        }

        $yesCount = $this->count->count($rules, $yes);
        $ifNeedBeCount = $this->count->count($rules, $ifNeedBe);
        $answered = \count($yes);
        $tallies = [];

        foreach ($slots as $slot) {
            $yesScore = $this->score($yesCount, $slot);
            $ifNeedBeScore = $this->score($ifNeedBeCount, $slot);
            $tallies[] = [$slot, $yesScore, $ifNeedBeScore, $answered - $yesScore - $ifNeedBeScore];
        }

        usort($tallies, static fn (array $a, array $b): int => [$b[1], $b[2], $a[0]->startsAt()] <=> [$a[1], $a[2], $b[0]->startsAt()]);

        $ordered = [];
        $place = 1;

        foreach ($tallies as $index => [$slot, $yesScore, $ifNeedBeScore, $no]) {
            $previous = $ordered[$index - 1] ?? null;

            if ($previous instanceof SlotTally && ($previous->yes !== $yesScore || $previous->ifNeedBe !== $ifNeedBeScore)) {
                $place = $index + 1;
            }

            $ordered[] = new SlotTally($slot, $yesScore, $ifNeedBeScore, $no, $place);
        }

        return $ordered;
    }

    /**
     * The date chosen at the deadline: the first of the count, when anyone can come to it.
     *
     * @param list<SlotTally> $tallies
     */
    public static function best(array $tallies): ?Slot
    {
        $first = $tallies[0] ?? null;

        return $first instanceof SlotTally && $first->hasAvailability() ? $first->slot : null;
    }

    private function ballot(PollRules $rules, Respondent $respondent, Availability $availability): Ballot
    {
        $choices = [];

        foreach ($rules->options as $slot) {
            if ($respondent->answerTo($slot) === $availability) {
                $choices[] = $slot;
            }
        }

        return $rules->ballot($choices);
    }

    private function score(Result $result, Slot $slot): int
    {
        return $result->standingOf($slot->id)->score ?? 0;
    }
}
