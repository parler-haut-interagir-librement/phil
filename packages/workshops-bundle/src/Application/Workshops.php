<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Application;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Reservation\Exception\InvalidInterval;
use Phil\Reservation\Interval;
use Phil\WorkshopsBundle\Application\Contract\SeatClaims;
use Phil\WorkshopsBundle\Application\Exception\NotFound;
use Phil\WorkshopsBundle\Application\Exception\RegistrationRefused;
use Phil\WorkshopsBundle\Domain\Contract\RegistrationRepository;
use Phil\WorkshopsBundle\Domain\Contract\SessionRepository;
use Phil\WorkshopsBundle\Domain\Contract\WorkshopRepository;
use Phil\WorkshopsBundle\Domain\Event\RegistrationCancelled;
use Phil\WorkshopsBundle\Domain\Event\RegistrationConfirmed;
use Phil\WorkshopsBundle\Domain\Event\SessionCancelled;
use Phil\WorkshopsBundle\Domain\Event\SessionRescheduled;
use Phil\WorkshopsBundle\Domain\Event\SessionResized;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Model\Registration;
use Phil\WorkshopsBundle\Domain\Model\Workshop;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RefusalReason;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Workshops, their sessions and the registrations to them. Each request that takes or frees seats runs under the
 * session's lock: it reads the seats taken and claimed, decides, and saves, before another request on the same session
 * reads anything. The events go out once the change is saved.
 *
 * A registration takes its seats when they are free and nobody claims them, and is refused as full otherwise. The
 * `SeatClaims` port says which free seats a waiting list claims: with phil/waitlist-bundle, those offered to people on
 * the session's list and those its waiting people ask for, so that nobody skips the line.
 */
final readonly class Workshops
{
    /**
     * @param int             $maxSeats the most seats one registration takes
     * @param SeatClaims|null $claims   the free seats a waiting list claims; none when null
     */
    public function __construct(
        private WorkshopRepository $workshops,
        private SessionRepository $sessions,
        private RegistrationRepository $registrations,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private int $maxSeats = 10,
        private ?SeatClaims $claims = null,
    ) {
    }

    /**
     * @throws InvalidWorkshop when the title is empty or too long
     */
    public function createWorkshop(string $title): Workshop
    {
        $workshop = Workshop::create($title, $this->clock->now());
        $this->workshops->save($workshop);

        return $workshop;
    }

    /**
     * A session of a workshop, on the clock of a time zone, with the window in which it takes registrations.
     *
     * @throws NotFound        when the workshop does not exist
     * @throws InvalidWorkshop when the workshop is inactive, or a value out of bounds
     */
    public function schedule(string $workshopId, DateTimeInterface $start, DateTimeInterface $end, string $timezone, int $capacity, ?DateTimeInterface $opensAt = null, ?DateTimeInterface $closesAt = null): WorkshopSession
    {
        $workshop = $this->workshops->get($workshopId) ?? throw NotFound::workshop($workshopId);
        $session = WorkshopSession::schedule($workshop, $this->interval($start, $end), $timezone, $capacity);
        $session->openRegistration($this->moment($opensAt, $session), $this->moment($closesAt, $session));

        $this->sessions->save($session);

        return $session;
    }

    /**
     * Moves a session; its registrations are kept.
     *
     * @throws NotFound        when the session does not exist
     * @throws InvalidWorkshop when it is cancelled, or its registration window no longer fits
     */
    public function reschedule(string $sessionId, DateTimeInterface $start, DateTimeInterface $end): WorkshopSession
    {
        $session = $this->session($sessionId);
        $session->reschedule($this->interval($start, $end));

        $this->sessions->save($session);
        $this->dispatch(new SessionRescheduled($session->id));

        return $session;
    }

    /**
     * The window in which a session takes registrations.
     *
     * @throws NotFound        when the session does not exist
     * @throws InvalidWorkshop when the window is empty or closes after the session ends
     */
    public function openRegistration(string $sessionId, ?DateTimeInterface $opensAt, ?DateTimeInterface $closesAt): WorkshopSession
    {
        $session = $this->session($sessionId);
        $session->openRegistration($this->moment($opensAt, $session), $this->moment($closesAt, $session));

        $this->sessions->save($session);

        return $session;
    }

    /**
     * Gives a session another capacity, never below the seats taken and offered; the waiting list, when there is one,
     * hears of it through `SessionResized`.
     *
     * @throws NotFound        when the session does not exist
     * @throws InvalidWorkshop when it is cancelled, the capacity out of bounds or below the seats taken and offered
     */
    public function resize(string $sessionId, int $capacity): WorkshopSession
    {
        $session = $this->session($sessionId);

        $this->registrations->exclusively($session, function () use ($session, $capacity): void {
            $held = $this->registrations->seats($session, RegistrationStatus::Confirmed) + ($this->claims?->offered($session) ?? 0);

            if ($capacity < $held) {
                throw InvalidWorkshop::because(\sprintf('The session holds at least the %d seats taken or offered.', $held));
            }

            $session->resize($capacity);
            $this->sessions->save($session);
        });

        $this->dispatch(new SessionResized($session->id));

        return $session;
    }

    /**
     * Cancels a session: it takes no more registrations, and its registrations stay as they were.
     *
     * @throws NotFound        when the session does not exist
     * @throws InvalidWorkshop when it is already cancelled
     */
    public function cancelSession(string $sessionId): WorkshopSession
    {
        $session = $this->session($sessionId);

        $this->registrations->exclusively($session, function () use ($session): void {
            $session->cancel();
            $this->sessions->save($session);
        });

        $this->dispatch(new SessionCancelled($session->id, $session->workshop()->id));

        return $session;
    }

    /**
     * Registers a participant when the seats are free and nobody claims them. A participant already registered in the
     * session gets that registration back, unchanged.
     *
     * @throws NotFound            when the session does not exist
     * @throws RegistrationRefused when the session refuses it, or its seats are taken or claimed
     * @throws InvalidWorkshop     when the participant is not an identifier, or the seats below one
     */
    public function register(string $sessionId, string $participant, int $seats = 1): Registration
    {
        $session = $this->session($sessionId);

        [$registration, $created] = $this->registrations->exclusively($session, function () use ($session, $participant, $seats): array {
            $existing = $this->registrations->live($session, $participant);

            if ($existing instanceof Registration) {
                return [$existing, false];
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

            if ($this->count($session)->available < $seats) {
                throw new RegistrationRefused(RefusalReason::Full);
            }

            $registration = Registration::confirmed($session, $participant, $seats, $now);
            $this->registrations->save($registration);

            return [$registration, true];
        });

        if ($created) {
            $this->dispatch(new RegistrationConfirmed($registration->id, $session->id));
        }

        return $registration;
    }

    /**
     * Cancels a registration; the waiting list, when there is one, hears of the seats it frees through
     * `RegistrationCancelled`.
     *
     * @throws NotFound        when the registration does not exist
     * @throws InvalidWorkshop when it is already cancelled
     */
    public function cancel(string $registrationId): Registration
    {
        $registration = $this->registrations->get($registrationId) ?? throw NotFound::registration($registrationId);
        $session = $registration->session();

        $this->registrations->exclusively($session, function () use ($registration): void {
            $registration->cancel($this->clock->now());
            $this->registrations->save($registration);
        });

        $this->dispatch(new RegistrationCancelled($registration->id, $session->id));

        return $registration;
    }

    /**
     * A session's seats now.
     *
     * @throws NotFound when the session does not exist
     */
    public function seats(string $sessionId): SeatCount
    {
        return $this->count($this->session($sessionId));
    }

    private function count(WorkshopSession $session): SeatCount
    {
        return new SeatCount(
            $session->capacity(),
            $this->registrations->seats($session, RegistrationStatus::Confirmed),
            $this->claims?->claimed($session) ?? 0,
        );
    }

    private function session(string $id): WorkshopSession
    {
        return $this->sessions->get($id) ?? throw NotFound::session($id);
    }

    /**
     * @throws InvalidWorkshop when the end does not come after the start
     */
    private function interval(DateTimeInterface $start, DateTimeInterface $end): Interval
    {
        try {
            return new Interval(DateTimeImmutable::createFromInterface($start), DateTimeImmutable::createFromInterface($end));
        } catch (InvalidInterval) {
            throw InvalidWorkshop::because('A session ends after it starts.');
        }
    }

    private function moment(?DateTimeInterface $moment, WorkshopSession $session): ?DateTimeImmutable
    {
        return $moment instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($moment)->setTimezone($session->timezone()) : null;
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
