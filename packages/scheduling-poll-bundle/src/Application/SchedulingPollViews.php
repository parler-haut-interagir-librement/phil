<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application;

use DateTimeZone;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\Exception\NotFound;
use Phil\SchedulingPollBundle\Application\Exception\NotOrganizer;
use Phil\SchedulingPollBundle\Application\View\AnswerSheet;
use Phil\SchedulingPollBundle\Application\View\ResponseRow;
use Phil\SchedulingPollBundle\Application\View\ResultsView;
use Phil\SchedulingPollBundle\Application\View\SlotLine;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use Phil\SchedulingPollBundle\Domain\Tally\SlotTally;
use SensitiveParameter;

/**
 * The views of a scheduling poll for a page: a respondent's sheet, opened by their link, and the answers of all for the
 * organizer.
 *
 * Who sees the answers before the closing is the poll's choice: the organizer when the poll says so — they always see
 * them once it is closed —, and a respondent as its `ResultsVisibility` says: always, once they answered, once the poll
 * is closed, or never, the organizer alone seeing them. Every date is shown in the viewer's time zone: the one asked
 * for, else the one the respondent last chose, else the poll's; a zone the IANA database does not have is passed over.
 */
final readonly class SchedulingPollViews
{
    public function __construct(
        private SchedulingPolls $polls,
    ) {
    }

    /**
     * The sheet a personal link opens, `null` when it opens nothing.
     *
     * @param string|null $timezone the time zone to show the dates in; an unknown one is ignored
     */
    public function sheet(#[SensitiveParameter] string $token, ?string $timezone = null): ?AnswerSheet
    {
        $access = $this->polls->open($token);

        if (!$access instanceof RespondentAccess) {
            return null;
        }

        $poll = $access->poll;
        $respondent = $access->respondent;
        $zone = $this->zone($timezone) ?? $this->zone($respondent->timezone()) ?? $poll->timezone();
        $visible = match ($poll->resultsVisibility()) {
            ResultsVisibility::Always => true,
            ResultsVisibility::AfterVoting => $respondent->hasAnswered() || $poll->isClosed(),
            ResultsVisibility::AfterClosing => $poll->isClosed(),
            ResultsVisibility::Managers => false,
        };
        $results = $visible ? $this->build($poll, $access->slots, $zone, true, $respondent) : null;
        $lines = [];

        foreach ($access->slots as $index => $slot) {
            $tallied = $results?->slots[$index];
            $lines[] = new SlotLine($slot->id, $slot->startsAt($zone), $slot->endsAt($zone), $respondent->answerTo($slot->id), $tallied?->yes, $tallied?->ifNeedBe, $tallied?->no, $tallied?->place, $slot->id === $poll->chosenSlot());
        }

        return new AnswerSheet(
            $poll->id,
            $respondent->id,
            $poll->title(),
            $poll->description(),
            $poll->location(),
            $respondent->name(),
            $zone,
            $poll->deadline()->setTimezone($zone),
            $lines,
            $respondent->hasAnswered(),
            $access->editable,
            $poll->isClosed(),
            array_find($lines, static fn (SlotLine $line): bool => $line->chosen),
            $results,
        );
    }

    /**
     * The answers of all, for the organizer: hidden before the closing when the poll says so.
     *
     * @throws NotFound
     * @throws NotOrganizer
     */
    public function results(string $pollId, ResourceReference $organizer, ?string $timezone = null): ResultsView
    {
        $poll = $this->polls->get($pollId);

        if (!$poll->isOrganizedBy($organizer)) {
            throw NotOrganizer::of($pollId);
        }

        return $this->build($poll, $this->polls->slots($poll->id), $this->zone($timezone) ?? $poll->timezone(), $poll->isClosed() || $poll->organizerSeesAnswers());
    }

    /**
     * @param list<Slot> $slots
     */
    private function build(SchedulingPoll $poll, array $slots, DateTimeZone $zone, bool $visible, ?Respondent $viewer = null): ResultsView
    {
        $respondents = $this->polls->respondents($poll->id);
        $answered = \count(array_filter($respondents, static fn (Respondent $respondent): bool => $respondent->hasAnswered() && !$respondent->isRevoked()));
        $tallies = [];

        if ($visible) {
            foreach ($this->polls->tally($poll->id) as $tally) {
                $tallies[$tally->slot->id] = $tally;
            }
        }

        $lines = [];

        foreach ($slots as $slot) {
            $tally = $tallies[$slot->id] ?? null;
            $lines[] = $tally instanceof SlotTally
                ? new SlotLine($slot->id, $slot->startsAt($zone), $slot->endsAt($zone), $viewer?->answerTo($slot->id), $tally->yes, $tally->ifNeedBe, $tally->no, $tally->place, $slot->id === $poll->chosenSlot())
                : new SlotLine($slot->id, $slot->startsAt($zone), $slot->endsAt($zone), $viewer?->answerTo($slot->id), chosen: $slot->id === $poll->chosenSlot());
        }

        $rows = [];

        foreach ($visible ? $respondents : [] as $index => $respondent) {
            if ($respondent->hasAnswered() && !$respondent->isRevoked()) {
                $rows[] = new ResponseRow($respondent->id, $index + 1, $respondent->name(), $respondent->answers(), $respondent->id === $viewer?->id);
            }
        }

        return new ResultsView(
            $poll->id,
            $poll->title(),
            $zone,
            $poll->deadline()->setTimezone($zone),
            $lines,
            $rows,
            $answered,
            $visible,
            $poll->isClosed(),
            array_find($lines, static fn (SlotLine $line): bool => $line->chosen),
        );
    }

    /**
     * The time zone of this name, `null` for none or one the IANA database does not have.
     */
    private function zone(?string $name): ?DateTimeZone
    {
        try {
            return null === $name ? null : SchedulingPoll::zone($name);
        } catch (InvalidSchedulingPoll) {
            return null;
        }
    }
}
