<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Integration\Waitlist;

use DateTimeImmutable;
use Phil\WaitlistBundle\Application\Exception\NotFound as EntryNotFound;
use Phil\WaitlistBundle\Application\Exception\RefusalReason as WaitlistRefusal;
use Phil\WaitlistBundle\Application\Exception\WaitlistRefused;
use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Domain\Exception\InvalidWaitlist;
use Phil\WaitlistBundle\Domain\Model\Entry;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;
use Phil\WorkshopsBundle\Application\Exception\NotFound;
use Phil\WorkshopsBundle\Application\Exception\RegistrationRefused;
use Phil\WorkshopsBundle\Domain\Contract\RegistrationRepository;
use Phil\WorkshopsBundle\Domain\Contract\SessionRepository;
use Phil\WorkshopsBundle\Domain\Event\RegistrationConfirmed;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Model\Registration;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RefusalReason;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use UnexpectedValueException;

/**
 * The waiting lists of the sessions, kept by phil/waitlist-bundle: one list per session, its subject
 * `phil_workshops.session` and the session's identifier. A participant who finds a session full joins its list; the
 * seats that free up are released to the list, which offers them in turn with a deadline; an offer accepted here
 * becomes a confirmed registration.
 *
 * Each call runs under the session's lock, the same one `Workshops` takes, then under the list's: the session first,
 * always, so that two requests never wait for each other. Balancing gives the list the free seats nobody has taken or
 * been offered, up to those its waiting participants ask for, and takes back the places it holds beyond that.
 */
final readonly class SessionWaitlists
{
    /** The kind of subject of a session's waiting list. */
    public const string SUBJECT = 'phil_workshops.session';

    /**
     * @param int $maxSeats the most seats one registration takes
     */
    public function __construct(
        private SessionRepository $sessions,
        private RegistrationRepository $registrations,
        private Waitlists $waitlists,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private int $maxSeats = 10,
    ) {
    }

    /**
     * A session's waiting list, once somebody joined it.
     */
    public function of(string $sessionId): ?Waitlist
    {
        return $this->waitlists->ofSubject(self::SUBJECT, $sessionId);
    }

    /**
     * Puts a participant on a session's waiting list, behind those already in it; the list is opened at the first
     * call. A participant already waiting or holding an offer gets that entry back. When seats are free that nobody
     * took, the entry may be offered them at once.
     *
     * @throws NotFound                 when the session does not exist
     * @throws RegistrationRefused      when the session refuses registrations, the seats are too many, or the
     *                                  participant already holds seats in it
     * @throws WaitlistRefused          when the list is closed or the places too many for it
     * @throws InvalidWaitlist          when the participant is not an identifier the list takes
     * @throws UnexpectedValueException when the list's offer policy picks wrong
     */
    public function join(string $sessionId, string $participant, int $seats = 1): Entry
    {
        $session = $this->session($sessionId);

        return $this->registrations->exclusively($session, function () use ($session, $participant, $seats): Entry {
            if ($this->registrations->live($session, $participant) instanceof Registration) {
                throw new RegistrationRefused(RefusalReason::Registered);
            }

            $now = $this->clock->now();
            $reason = $session->refusalAt($now);

            if ($reason instanceof RefusalReason) {
                throw new RegistrationRefused($reason);
            }

            $max = min($this->maxSeats, $session->capacity());

            if ($seats > $max) {
                throw new RegistrationRefused(RefusalReason::TooManySeats, ['max' => $max]);
            }

            $waitlist = $this->waitlists->open(self::SUBJECT, $session->id);
            // The places held for nobody go back to the session first, so that the entry is never offered seats a
            // registration took meanwhile.
            $this->settle($session, $waitlist, $now);
            $entry = $this->waitlists->join($waitlist->id, $participant, $seats);
            $this->settle($session, $waitlist, $now);

            return $entry;
        });
    }

    /**
     * Accepts the offer an entry holds and confirms its participant's registration for the places offered, in one
     * transaction. A participant who already holds seats in the session gets that registration back, and their entry
     * leaves the list.
     *
     * @throws EntryNotFound            when the entry does not exist
     * @throws InvalidWorkshop          when the entry is not on a session's waiting list
     * @throws NotFound                 when its session does not exist
     * @throws RegistrationRefused      when the session no longer takes registrations, or its seats are taken
     * @throws WaitlistRefused          when the entry holds no offer, or its deadline has come
     * @throws UnexpectedValueException when the list's offer policy picks wrong
     */
    public function accept(string $entryId): Registration
    {
        $entry = $this->waitlists->entry($entryId);
        $session = $this->sessionOf($entry->waitlist());

        [$registration, $created] = $this->registrations->exclusively($session, function () use ($entry, $session): array {
            $existing = $this->registrations->live($session, $entry->participant());

            if ($existing instanceof Registration) {
                if ($entry->status()->isLive()) {
                    $this->waitlists->leave($entry->id);
                }

                return [$existing, false];
            }

            if (EntryStatus::Offered !== $entry->status()) {
                throw new WaitlistRefused(WaitlistRefusal::NoOffer);
            }

            $now = $this->clock->now();
            $reason = $session->refusalAt($now);

            if ($reason instanceof RefusalReason) {
                throw new RegistrationRefused($reason);
            }

            if ($this->registrations->seats($session, RegistrationStatus::Confirmed) + $entry->places() > $session->capacity()) {
                throw new RegistrationRefused(RefusalReason::Full);
            }

            $this->waitlists->accept($entry->id);
            $registration = Registration::confirmed($session, $entry->participant(), $entry->places(), $now);
            $this->registrations->save($registration);

            return [$registration, true];
        });

        if ($created) {
            $this->events?->dispatch(new RegistrationConfirmed($registration->id, $session->id));
        }

        return $registration;
    }

    /**
     * Gives a session's waiting list the free seats nobody has taken or been offered, up to those its waiting
     * participants ask for, and takes back the places it holds beyond that. The listener calls it when a registration
     * is cancelled or the capacity changes; nothing happens to a session without a list.
     *
     * @throws NotFound                 when the session does not exist
     * @throws UnexpectedValueException when the list's offer policy picks wrong
     */
    public function balance(string $sessionId): void
    {
        $session = $this->session($sessionId);
        $waitlist = $this->of($session->id);

        if (!$waitlist instanceof Waitlist) {
            return;
        }

        $this->registrations->exclusively($session, function () use ($session, $waitlist): void {
            $this->settle($session, $waitlist, $this->clock->now());
        });
    }

    /**
     * Balances the waiting list of a session, given the list's identifier: what the listener does when an entry leaves,
     * so that the places held for it go to those behind. A list that is not a session's, or that does not exist, is
     * left alone.
     *
     * @throws UnexpectedValueException when the list's offer policy picks wrong
     */
    public function balanceList(string $waitlistId): void
    {
        try {
            $waitlist = $this->waitlists->waitlist($waitlistId);
        } catch (EntryNotFound) {
            return;
        }

        if (self::SUBJECT === $waitlist->subjectType() && $this->sessions->get($waitlist->subjectId()) instanceof WorkshopSession) {
            $this->balance($waitlist->subjectId());
        }
    }

    /**
     * Closes a session's waiting list, when it has an open one: it takes no more entries, and the offers held stay
     * until they are answered or lapse.
     */
    public function close(string $sessionId): void
    {
        $waitlist = $this->of($sessionId);

        if ($waitlist instanceof Waitlist && !$waitlist->isClosed()) {
            $this->waitlists->close($waitlist->id);
        }
    }

    /**
     * Gives the list the free seats it may offer: those nobody took or was offered, up to the places its waiting
     * participants ask for. The places it holds are taken back first and the share released afresh, so that its policy
     * runs again on the entries waiting now. A session that no longer takes registrations releases nothing.
     */
    private function settle(WorkshopSession $session, Waitlist $waitlist, DateTimeImmutable $now): void
    {
        if ($session->refusalAt($now) instanceof RefusalReason) {
            return;
        }

        $count = $this->waitlists->count($waitlist->id);
        $spare = $session->capacity() - $this->registrations->seats($session, RegistrationStatus::Confirmed) - $count->offered;
        $share = min($spare, $count->waiting);

        if ($count->held > 0) {
            $this->waitlists->reclaim($waitlist->id, $count->held);
        }

        if ($share > 0) {
            $this->waitlists->release($waitlist->id, $share);
        }
    }

    /**
     * @throws InvalidWorkshop when the list is not a session's
     * @throws NotFound        when the session does not exist
     */
    private function sessionOf(Waitlist $waitlist): WorkshopSession
    {
        if (self::SUBJECT !== $waitlist->subjectType()) {
            throw InvalidWorkshop::because('The entry is not on the waiting list of a session.');
        }

        return $this->session($waitlist->subjectId());
    }

    private function session(string $id): WorkshopSession
    {
        return $this->sessions->get($id) ?? throw NotFound::session($id);
    }
}
