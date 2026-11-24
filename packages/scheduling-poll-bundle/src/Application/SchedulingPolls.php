<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application;

use DateInterval;
use Phil\AccessLinks\AccessLinks;
use Phil\AccessLinks\Exception\InvitationClosed;
use Phil\AccessLinks\Exception\LinkNotUsable;
use Phil\AccessLinks\InvitationAccess;
use Phil\AccessLinks\IssuedLink;
use Phil\Resource\ResourceReference;
use Phil\SchedulingPollBundle\Application\Event\ClosedWithoutDate;
use Phil\SchedulingPollBundle\Application\Event\DateChosen;
use Phil\SchedulingPollBundle\Application\Exception\AnswerRefused;
use Phil\SchedulingPollBundle\Application\Exception\NotFound;
use Phil\SchedulingPollBundle\Application\Exception\NotOrganizer;
use Phil\SchedulingPollBundle\Application\Message\AnnounceChosenDate;
use Phil\SchedulingPollBundle\Domain\Availability;
use Phil\SchedulingPollBundle\Domain\ClosedBy;
use Phil\SchedulingPollBundle\Domain\Contract\RespondentRepository;
use Phil\SchedulingPollBundle\Domain\Contract\SchedulingPollRepository;
use Phil\SchedulingPollBundle\Domain\Exception\InvalidSchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Respondent;
use Phil\SchedulingPollBundle\Domain\Model\SchedulingPoll;
use Phil\SchedulingPollBundle\Domain\Model\Slot;
use Phil\SchedulingPollBundle\Domain\Tally\AvailabilityCount;
use Phil\SchedulingPollBundle\Domain\Tally\SlotTally;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use SensitiveParameter;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * What the application does with scheduling polls: create them, invite respondents or let them join by the poll's
 * page, open a personal link, record answers, revoke a respondent, close the poll on a date — by its organizer, or at
 * its deadline on the best date — and announce that date.
 *
 * A respondent's link is an invitation of `phil/access-links` to the campaign `scheduling-poll:<poll>`, the recipient
 * being the respondent's identifier: the token is kept only as its keyed hash. The link changes the answers until the
 * deadline, or until the organizer closes the poll, and still opens, to show the date chosen, until the respondent's
 * name and address are erased.
 *
 * The closing is one conditional write: the closing that wins queues the announcement and dispatches its event, the
 * other does nothing, so that a closing by hand racing the deadline announces the date once. The service keeps no
 * state between two calls, and is safe to share across the requests of a long-running worker.
 */
final readonly class SchedulingPolls
{
    public const string CAMPAIGN_PREFIX = 'scheduling-poll:';

    /** The fewest and the most dates a poll proposes: the bounds of a poll of `phil/poll`. */
    public const int MIN_SLOTS = 2;

    public const int MAX_SLOTS = 100;

    private DateInterval $linkLifetime;

    /**
     * @param string       $linkLifetime   how long a link still opens after the deadline, an ISO 8601 duration: the delay
     *                                     after which the names and addresses are erased
     * @param positive-int $maxRespondents the most respondents a poll takes
     */
    public function __construct(
        private SchedulingPollRepository $polls,
        private RespondentRepository $respondents,
        private AccessLinks $links,
        private ClockInterface $clock,
        private AvailabilityCount $count = new AvailabilityCount(),
        private ?MessageBusInterface $bus = null,
        private ?EventDispatcherInterface $events = null,
        string $linkLifetime = 'P30D',
        private int $maxRespondents = 500,
    ) {
        $this->linkLifetime = new DateInterval($linkLifetime);
    }

    /**
     * Creates a poll on these dates.
     *
     * @throws InvalidSchedulingPoll when the title, the time zone, the deadline or the dates are out of bounds
     */
    public function create(SchedulingPollDraft $draft, ResourceReference $organizer): SchedulingPoll
    {
        $count = \count($draft->slots);

        if ($count < self::MIN_SLOTS || $count > self::MAX_SLOTS) {
            throw InvalidSchedulingPoll::because(\sprintf('A scheduling poll proposes %d to %d dates, not %d.', self::MIN_SLOTS, self::MAX_SLOTS, $count));
        }

        $poll = new SchedulingPoll(Uuid::v7()->toRfc4122(), $draft->title, $organizer, $draft->timezone, $draft->deadline, $this->clock->now(), $draft->description, $draft->location, $draft->organizerSeesAnswers, $draft->results);
        $slots = [];
        $starts = [];

        foreach ($draft->slots as $position => $proposed) {
            $slot = new Slot(Uuid::v7()->toRfc4122(), $poll->id, $position, $proposed->startsAt, $proposed->endsAt);
            $start = $slot->startsAt()->format('U');

            if (isset($starts[$start])) {
                throw InvalidSchedulingPoll::because('Two dates of a scheduling poll start at the same moment.');
            }

            $starts[$start] = true;
            $slots[] = $slot;
        }

        $this->polls->add($poll, $slots);

        return $poll;
    }

    /**
     * @throws NotFound
     */
    public function get(string $id): SchedulingPoll
    {
        return $this->polls->get($id) ?? throw NotFound::poll($id);
    }

    /**
     * @return list<Slot>
     */
    public function slots(string $poll): array
    {
        return $this->polls->slots($poll);
    }

    /**
     * Invites someone by name, and their address to receive the date chosen: the organizer sends the link returned.
     *
     * @throws NotFound
     * @throws NotOrganizer
     * @throws AnswerRefused         when the poll is closed or full
     * @throws InvalidSchedulingPoll when the name or the address is out of bounds
     */
    public function invite(string $pollId, ResourceReference $organizer, string $name, ?string $email = null, ?string $locale = null): IssuedLink
    {
        $poll = $this->organized($pollId, $organizer);

        return $this->enroll($poll, $name, $email, null, $locale);
    }

    /**
     * Lets someone join from the poll's page, by their name alone, an address if they want the date chosen, an account
     * if they are signed in: the page shows them the link returned, or takes them to it.
     *
     * @throws NotFound
     * @throws AnswerRefused         when the poll is closed or full
     * @throws InvalidSchedulingPoll when the name or the address is out of bounds
     */
    public function join(string $pollId, string $name, ?string $email = null, ?ResourceReference $account = null, ?string $locale = null): IssuedLink
    {
        return $this->enroll($this->get($pollId), $name, $email, $account, $locale);
    }

    /**
     * Issues another link to a respondent who lost theirs; the earlier links keep opening.
     *
     * @throws NotFound
     * @throws NotOrganizer
     */
    public function reissue(string $pollId, ResourceReference $organizer, string $respondentId): IssuedLink
    {
        $this->organized($pollId, $organizer);

        return $this->links->reissue($this->respondentOf($pollId, $respondentId)->invitation);
    }

    /**
     * What a personal link opens now, or `null` when it opens nothing: malformed, unknown, revoked or expired. Opening
     * changes nothing, so that the preview of a mail client answers nothing.
     */
    public function open(#[SensitiveParameter] string $token): ?RespondentAccess
    {
        $access = $this->links->open($token);
        $respondent = $access instanceof InvitationAccess ? $this->respondents->ofInvitation($access->invitation->id) : null;
        $poll = $respondent instanceof Respondent ? $this->polls->get($respondent->poll) : null;

        if (\in_array(null, [$access, $respondent, $poll], true) || $respondent->isRevoked()) {
            return null;
        }

        return new RespondentAccess($respondent, $poll, $this->polls->slots($poll->id), $access->editable && $poll->isOpenAt($this->clock->now()));
    }

    /**
     * Records the answers of the link's respondent, or changes them, while the poll is open. A date left out is
     * answered no.
     *
     * @param array<string, Availability> $answers  by slot identifier
     * @param string|null                 $timezone the time zone the respondent reads the dates in, `null` for the poll's
     *
     * @throws AnswerRefused         when the link opens nothing or the poll is closed
     * @throws InvalidSchedulingPoll when a slot is not one of the poll's, or the time zone is unknown
     */
    public function answer(#[SensitiveParameter] string $token, array $answers, ?string $timezone = null): Respondent
    {
        $access = $this->open($token) ?? throw AnswerRefused::linkNotUsable();

        if (null !== $timezone) {
            SchedulingPoll::zone($timezone);
        }

        $complete = [];

        foreach ($access->slots as $slot) {
            $complete[$slot->id] = $answers[$slot->id] ?? Availability::No;
            unset($answers[$slot->id]);
        }

        if ([] !== $answers) {
            throw InvalidSchedulingPoll::because('An answer names a date the scheduling poll does not propose.');
        }

        try {
            $this->links->respond($token, implode('', array_map(static fn (Availability $answer): string => $answer->value[0], $complete)));
        } catch (InvitationClosed) {
            throw AnswerRefused::closed();
        } catch (LinkNotUsable) {
            throw AnswerRefused::linkNotUsable();
        }

        if (!$this->respondents->answer($access->respondent->id, $complete, $timezone, $this->clock->now())) {
            throw AnswerRefused::closed();
        }

        return $this->respondents->get($access->respondent->id) ?? throw NotFound::respondent($access->respondent->id);
    }

    /**
     * Revokes a respondent: their links open nothing more and their answers are no longer counted. Returns `false` for
     * one revoked before.
     *
     * @throws NotFound
     * @throws NotOrganizer
     */
    public function revoke(string $pollId, ResourceReference $organizer, string $respondentId): bool
    {
        $this->organized($pollId, $organizer);
        $respondent = $this->respondentOf($pollId, $respondentId);
        $this->links->revoke($respondent->invitation);

        return $this->respondents->revoke($respondent->id, $this->clock->now());
    }

    /**
     * @return list<Respondent> in the order they joined, revoked ones included
     */
    public function respondents(string $pollId): array
    {
        return $this->respondents->ofPoll($pollId);
    }

    /**
     * The count of the answers, the best date first.
     *
     * @return list<SlotTally>
     */
    public function tally(string $pollId): array
    {
        return $this->count->count($this->polls->slots($pollId), $this->respondents->ofPoll($pollId));
    }

    /**
     * Closes the poll on the date its organizer chose, before or after the deadline. Returns whether this call closed
     * it: `false` when it was closed already, by hand or at its deadline.
     *
     * @throws NotFound
     * @throws NotOrganizer
     * @throws InvalidSchedulingPoll when the slot is not one of the poll's
     */
    public function close(string $pollId, ResourceReference $organizer, string $slotId): bool
    {
        $poll = $this->organized($pollId, $organizer);

        if (!array_any($this->polls->slots($poll->id), static fn (Slot $slot): bool => $slot->id === $slotId)) {
            throw InvalidSchedulingPoll::because('The date chosen is not one the scheduling poll proposes.');
        }

        return !$poll->isClosed() && $this->finish($poll, ClosedBy::Organizer, $slotId);
    }

    /**
     * Closes the polls past their deadline on their best date — the most yes answers, then the most if-need-be
     * answers, then the earliest —, or without a date when nobody can come to any. Returns how many this call closed.
     *
     * @param positive-int $limit the polls looked at, at most
     */
    public function closeDue(int $limit = 100): int
    {
        $closed = 0;

        foreach ($this->polls->due($this->clock->now(), $limit) as $id) {
            $poll = $this->polls->get($id);

            if (!$poll instanceof SchedulingPoll || $poll->isClosed()) {
                continue;
            }

            $best = AvailabilityCount::best($this->tally($id));

            if ($this->finish($poll, ClosedBy::Deadline, $best?->id)) {
                ++$closed;
            }
        }

        return $closed;
    }

    /**
     * Erases the names and addresses of the respondents of the polls closed longer ago than the lifetime of a link.
     * Returns how many were erased.
     */
    public function eraseExpired(): int
    {
        $now = $this->clock->now();

        return $this->respondents->eraseClosedBefore($now->sub($this->linkLifetime), $now);
    }

    private function finish(SchedulingPoll $poll, ClosedBy $by, ?string $slot): bool
    {
        $now = $this->clock->now();

        if (!$this->polls->close($poll->id, $now, $by, $slot)) {
            return false;
        }

        $poll->close($now, $by, $slot);

        if (null === $slot) {
            $this->events?->dispatch(new ClosedWithoutDate($poll->id));

            return true;
        }

        $this->bus?->dispatch(new AnnounceChosenDate($poll->id));
        $this->events?->dispatch(new DateChosen($poll->id, $slot, $by));

        return true;
    }

    private function enroll(SchedulingPoll $poll, string $name, ?string $email, ?ResourceReference $account, ?string $locale): IssuedLink
    {
        $now = $this->clock->now();

        if (!$poll->isOpenAt($now)) {
            throw AnswerRefused::closed();
        }

        if ($this->respondents->count($poll->id) >= $this->maxRespondents) {
            throw AnswerRefused::full($this->maxRespondents);
        }

        $id = Uuid::v7()->toRfc4122();
        // Checked before the invitation is written, so that a name refused leaves no invitation behind.
        Respondent::checkName($name);
        Respondent::checkEmail($email);
        $issued = $this->links->invite(self::CAMPAIGN_PREFIX.$poll->id, $id, $poll->deadline(), $poll->deadline()->add($this->linkLifetime));
        $this->respondents->add(new Respondent($id, $poll->id, $issued->invitation->id, $name, $now, $email, $account, $locale));

        return $issued;
    }

    /**
     * @throws NotFound
     * @throws NotOrganizer
     */
    private function organized(string $pollId, ResourceReference $organizer): SchedulingPoll
    {
        $poll = $this->get($pollId);

        if (!$poll->isOrganizedBy($organizer)) {
            throw NotOrganizer::of($pollId);
        }

        return $poll;
    }

    /**
     * @throws NotFound
     */
    private function respondentOf(string $pollId, string $respondentId): Respondent
    {
        $respondent = $this->respondents->get($respondentId);

        if (!$respondent instanceof Respondent || $respondent->poll !== $pollId) {
            throw NotFound::respondent($respondentId);
        }

        return $respondent;
    }
}
