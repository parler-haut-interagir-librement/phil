<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\WorkshopsBundle\Application\Exception\NotFound;
use Phil\WorkshopsBundle\Application\Exception\RegistrationRefused;
use Phil\WorkshopsBundle\Application\SeatCount;
use Phil\WorkshopsBundle\Application\Workshops;
use Phil\WorkshopsBundle\Domain\Event\RegistrationCancelled;
use Phil\WorkshopsBundle\Domain\Event\RegistrationConfirmed;
use Phil\WorkshopsBundle\Domain\Event\SessionCancelled;
use Phil\WorkshopsBundle\Domain\Event\SessionRescheduled;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RefusalReason;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use Phil\WorkshopsBundle\Domain\Status\SessionStatus;
use Phil\WorkshopsBundle\Tests\Unit\Fixture\FixedClaims;
use Phil\WorkshopsBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\WorkshopsBundle\Tests\Unit\Fixture\InMemoryRegistrations;
use Phil\WorkshopsBundle\Tests\Unit\Fixture\InMemorySessions;
use Phil\WorkshopsBundle\Tests\Unit\Fixture\InMemoryWorkshops;
use Phil\WorkshopsBundle\Tests\Unit\Fixture\RecordingEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[CoversClass(Workshops::class)]
#[CoversClass(SeatCount::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(RegistrationRefused::class)]
final class WorkshopsTest extends TestCase
{
    private InMemoryWorkshops $workshops;

    private InMemorySessions $sessions;

    private InMemoryRegistrations $registrations;

    private FrozenClock $clock;

    private RecordingEvents $events;

    protected function setUp(): void
    {
        $this->workshops = new InMemoryWorkshops();
        $this->sessions = new InMemorySessions();
        $this->registrations = new InMemoryRegistrations();
        $this->clock = new FrozenClock();
        $this->events = new RecordingEvents();
    }

    private function service(int $maxSeats = 10, ?FixedClaims $claims = null): Workshops
    {
        return new Workshops($this->workshops, $this->sessions, $this->registrations, $this->clock, $this->events, $maxSeats, $claims);
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone('UTC'));
    }

    private function session(int $capacity = 4, ?Workshops $service = null): WorkshopSession
    {
        $service ??= $this->service();
        $workshop = $service->createWorkshop('Peindre des figurines');

        return $service->schedule($workshop->id, $this->at('2026-11-10 14:00'), $this->at('2026-11-10 17:00'), 'America/Toronto', $capacity);
    }

    /**
     * @return list<string> each event's class and first identifier
     */
    private function events(): array
    {
        return array_map(static function (object $event): string {
            $first = array_first(get_object_vars($event));

            return new ReflectionClass($event)->getShortName().' '.(\is_string($first) ? $first : '');
        }, $this->events->events);
    }

    /**
     * The event dispatched at a position.
     */
    private function recorded(int $position): object
    {
        return $this->events->events[$position] ?? self::fail(\sprintf('No event at %d.', $position));
    }

    public function testAWorkshopIsCreatedAndSaved(): void
    {
        $workshop = $this->service()->createWorkshop(' Peindre ');

        self::assertSame('Peindre', $workshop->title());
        self::assertSame($workshop, $this->workshops->get($workshop->id));
        self::assertSame('2026-11-01T12:00:00+00:00', $workshop->createdAt()->format(\DATE_ATOM));
    }

    public function testASessionIsScheduledWithItsWindow(): void
    {
        $service = $this->service();
        $workshop = $service->createWorkshop('Peindre');
        $session = $service->schedule($workshop->id, $this->at('2026-11-10 14:00'), $this->at('2026-11-10 17:00'), 'America/Toronto', 6, $this->at('2026-11-02 12:00'), $this->at('2026-11-09 12:00'));

        self::assertSame($session, $this->sessions->get($session->id));
        self::assertSame('2026-11-10T09:00:00-05:00', $session->span()->start->format(\DATE_ATOM));
        self::assertSame('2026-11-02T07:00:00-05:00', $session->opensAt()?->format(\DATE_ATOM));
        self::assertSame('2026-11-09T07:00:00-05:00', $session->closesAt()?->format(\DATE_ATOM));
        self::assertSame(6, $session->capacity());
        self::assertSame([], $this->events->events);
    }

    public function testSchedulingNeedsAWorkshopAndASpan(): void
    {
        $service = $this->service();

        try {
            $service->schedule('nope', $this->at('2026-11-10 14:00'), $this->at('2026-11-10 17:00'), 'UTC', 4);
            self::fail('No workshop.');
        } catch (NotFound $notFound) {
            self::assertSame('No workshop "nope".', $notFound->getMessage());
        }

        $workshop = $service->createWorkshop('Peindre');

        try {
            $service->schedule($workshop->id, $this->at('2026-11-10 14:00'), $this->at('2026-11-10 14:00'), 'UTC', 4);
            self::fail('An empty span.');
        } catch (InvalidWorkshop $invalidWorkshop) {
            self::assertSame('A session ends after it starts.', $invalidWorkshop->getMessage());
        }

        self::assertSame([], $this->sessions->sessions);
    }

    public function testAMissingIdentifierIsCutInTheMessage(): void
    {
        $this->expectExceptionObject(NotFound::session(str_repeat('x', 64)));

        $this->service()->seats(str_repeat('x', 100));
    }

    public function testAMissingIdentifierIsCutInTheMessageByCharacters(): void
    {
        $id = str_repeat('é', 70);
        $cut = str_repeat('é', 64);

        self::assertSame(\sprintf('No workshop "%s".', $cut), NotFound::workshop($id)->getMessage());
        self::assertSame(\sprintf('No session "%s".', $cut), NotFound::session($id)->getMessage());
        self::assertSame(\sprintf('No registration "%s".', $cut), NotFound::registration($id)->getMessage());
        self::assertSame('No session "abc".', NotFound::session('abc')->getMessage());
        self::assertSame('No registration "abc".', NotFound::registration('abc')->getMessage());
        self::assertSame('No workshop "abc".', NotFound::workshop('abc')->getMessage());
    }

    public function testNoSeatIsFreeBelowZero(): void
    {
        $count = new SeatCount(2, 5, 0);

        self::assertSame(0, $count->free);
        self::assertSame(0, $count->available);
        self::assertTrue($count->isFull());
        self::assertSame(1, new SeatCount(2, 1)->free);
        self::assertSame(0, new SeatCount(2, 1)->claimed);
        self::assertFalse(new SeatCount(2, 1, 0)->isFull());
    }

    public function testTheClaimedSeatsAreNotAvailable(): void
    {
        $count = new SeatCount(10, 4, 3);

        self::assertSame([10, 4, 6, 3, 3, false], [$count->capacity, $count->taken, $count->free, $count->claimed, $count->available, $count->isFull()]);
        self::assertSame(0, new SeatCount(10, 4, 6)->available);
        self::assertTrue(new SeatCount(10, 4, 6)->isFull());
        self::assertSame(0, new SeatCount(10, 4, 9)->available);
        self::assertSame(1, new SeatCount(10, 4, 5)->available);
    }

    public function testResizingSavesTheSession(): void
    {
        $session = $this->session(2);
        $saves = $this->sessions->saves;

        $this->service()->resize($session->id, 3);

        self::assertSame($saves + 1, $this->sessions->saves);
    }

    public function testARegistrationTakesFreeSeatsUnderTheLock(): void
    {
        $session = $this->session();
        $registration = $this->service()->register($session->id, 'account-1', 3);

        self::assertSame(RegistrationStatus::Confirmed, $registration->status());
        self::assertSame(3, $registration->seats());
        self::assertSame('2026-11-01T07:00:00-05:00', $registration->createdAt()->format(\DATE_ATOM));
        self::assertSame([$session->id], $this->registrations->locked);
        self::assertSame([$registration->id.' locked'], $this->registrations->saved);
        self::assertSame(['RegistrationConfirmed '.$registration->id], $this->events());
        self::assertSame($session->id, $this->recorded(0) instanceof RegistrationConfirmed ? $this->recorded(0)->sessionId : null);
        self::assertEquals(new SeatCount(4, 3, 0), $this->service()->seats($session->id));
    }

    public function testTheLastSeatsAreTakenThenTheSessionIsFull(): void
    {
        $session = $this->session(4);
        $service = $this->service();

        $first = $service->register($session->id, 'a', 3);
        $last = $service->register($session->id, 'b', 1);

        try {
            $service->register($session->id, 'c', 1);
            self::fail('The session is full.');
        } catch (RegistrationRefused $registrationRefused) {
            self::assertSame(RefusalReason::Full, $registrationRefused->reason);
            self::assertSame([], $registrationRefused->parameters);
            self::assertSame('The registration is refused: full.', $registrationRefused->getMessage());
        }

        self::assertSame(['RegistrationConfirmed '.$first->id, 'RegistrationConfirmed '.$last->id], $this->events());
        self::assertCount(2, $this->registrations->registrations);
        $count = $service->seats($session->id);
        self::assertSame([4, 4, 0, 0, true], [$count->capacity, $count->taken, $count->free, $count->available, $count->isFull()]);
    }

    public function testNobodyTakesTheSeatsTheWaitingListClaims(): void
    {
        $session = $this->session(4);
        $claims = new FixedClaims($this->registrations, claimed: 2);
        $service = $this->service(claims: $claims);
        $service->register($session->id, 'a', 1);

        // Three seats are free, two of them claimed: one is left.
        self::assertSame(1, $service->register($session->id, 'b', 1)->seats());

        try {
            $service->register($session->id, 'c', 1);
            self::fail('The free seats are claimed.');
        } catch (RegistrationRefused $registrationRefused) {
            self::assertSame(RefusalReason::Full, $registrationRefused->reason);
        }

        self::assertSame(['claimed '.$session->id.' locked', 'claimed '.$session->id.' locked', 'claimed '.$session->id.' locked'], $claims->asked);
        self::assertEquals(new SeatCount(4, 2, 2), $service->seats($session->id));
        self::assertSame(['claimed '.$session->id.' locked', 'claimed '.$session->id.' locked', 'claimed '.$session->id.' locked', 'claimed '.$session->id.' unlocked'], $claims->asked);
    }

    public function testAGroupFitsInTheSeatsNobodyClaimsAndNoMore(): void
    {
        $session = $this->session(6);
        $service = $this->service(claims: new FixedClaims($this->registrations, claimed: 2));

        self::assertSame(4, $service->register($session->id, 'group', 4)->seats());

        $this->expectExceptionObject(new RegistrationRefused(RefusalReason::Full));
        $service->register($session->id, 'single', 1);
    }

    public function testTheSeatsOfOneRegistrationAreBoundedByTheOptionAndTheCapacity(): void
    {
        $session = $this->session(4);
        $service = $this->service(maxSeats: 3);

        self::assertSame(3, $service->register($session->id, 'a', 3)->seats());

        foreach ([[$this->service(maxSeats: 3), 4, 3], [$this->service(maxSeats: 10), 5, 4]] as [$bounded, $seats, $max]) {
            try {
                $bounded->register($session->id, 'b', $seats);
                self::fail('Too many seats.');
            } catch (RegistrationRefused $refusal) {
                self::assertSame(RefusalReason::TooManySeats, $refusal->reason);
                self::assertSame(['max' => $max], $refusal->parameters);
            }
        }

        self::assertSame(1, $this->service(maxSeats: 1)->register($session->id, 'c', 1)->seats());
    }

    public function testAClosedOrCancelledSessionRefusesBeforeCountingSeats(): void
    {
        $session = $this->session(4);
        $service = $this->service(maxSeats: 1);
        $service->openRegistration($session->id, $this->at('2026-11-05'), null);

        foreach (['2026-11-04 23:59:59' => RefusalReason::NotOpen, '2026-11-10 14:00' => RefusalReason::Closed] as $moment => $reason) {
            $this->clock->now = $this->at($moment);

            try {
                $service->register($session->id, 'a', 5);
                self::fail('The session refuses.');
            } catch (RegistrationRefused $refusal) {
                self::assertSame($reason, $refusal->reason);
            }
        }

        $this->clock->now = $this->at('2026-11-06');
        $service->cancelSession($session->id);

        $this->expectExceptionObject(new RegistrationRefused(RefusalReason::Cancelled));
        $service->register($session->id, 'a', 5);
    }

    public function testRegisteringTwiceGivesBackTheSameRegistration(): void
    {
        $session = $this->session(1);
        $service = $this->service();
        $confirmed = $service->register($session->id, 'a', 1);

        // Even with more seats, once the session is full and registration has closed: the participant is already in.
        $this->clock->now = $this->at('2026-11-10 15:00');
        self::assertSame($confirmed, $service->register($session->id, 'a', 3));
        self::assertCount(1, $this->events->events);
        self::assertCount(1, $this->registrations->registrations);
    }

    public function testACancelledParticipantRegistersAgain(): void
    {
        $session = $this->session(1);
        $service = $this->service();
        $first = $service->register($session->id, 'a', 1);
        $service->cancel($first->id);

        $again = $service->register($session->id, 'a', 1);

        self::assertNotSame($first, $again);
        self::assertSame(RegistrationStatus::Confirmed, $again->status());
    }

    public function testCancellingFreesTheSeatsUnderTheLock(): void
    {
        $session = $this->session(4);
        $service = $this->service();
        $a = $service->register($session->id, 'a', 3);
        $b = $service->register($session->id, 'b', 1);
        $this->events->events = [];
        $this->registrations->saved = [];
        $this->registrations->locked = [];

        $this->clock->now = $this->at('2026-11-02 12:00');
        $cancelled = $service->cancel($a->id);

        self::assertSame($a, $cancelled);
        self::assertSame(RegistrationStatus::Cancelled, $cancelled->status());
        self::assertSame('2026-11-02T07:00:00-05:00', $cancelled->cancelledAt()?->format(\DATE_ATOM));
        self::assertSame(RegistrationStatus::Confirmed, $b->status());
        self::assertSame(['RegistrationCancelled '.$a->id], $this->events());
        $cancelledEvent = $this->recorded(0);
        self::assertInstanceOf(RegistrationCancelled::class, $cancelledEvent);
        self::assertSame($session->id, $cancelledEvent->sessionId);
        self::assertSame([$a->id.' locked'], $this->registrations->saved);
        self::assertSame([$session->id], $this->registrations->locked);
        self::assertEquals(new SeatCount(4, 1, 0), $service->seats($session->id));
    }

    public function testCancellingTwiceOrSomethingMissingIsRefused(): void
    {
        $session = $this->session(1);
        $service = $this->service();
        $a = $service->register($session->id, 'a', 1);
        $service->cancel($a->id);

        try {
            $service->cancel($a->id);
            self::fail('Already cancelled.');
        } catch (InvalidWorkshop) {
            self::assertCount(2, $this->events->events);
        }

        $this->expectExceptionObject(NotFound::registration('nope'));
        $service->cancel('nope');
    }

    public function testTheCapacityIsChangedUnderTheLock(): void
    {
        $session = $this->session(2);
        $service = $this->service();
        $service->register($session->id, 'a', 2);

        $this->events->events = [];
        $this->registrations->locked = [];

        $resized = $service->resize($session->id, 4);

        self::assertSame($session, $resized);
        self::assertSame(4, $session->capacity());
        self::assertSame(['SessionResized '.$session->id], $this->events());
        self::assertSame([$session->id], $this->registrations->locked);
        self::assertEquals(new SeatCount(4, 2, 0), $service->seats($session->id));
    }

    public function testTheCapacityNeverGoesBelowTheSeatsTaken(): void
    {
        $session = $this->session(4);
        $service = $this->service();
        $service->register($session->id, 'a', 3);

        $this->events->events = [];

        self::assertSame(3, $service->resize($session->id, 3)->capacity());

        try {
            $service->resize($session->id, 2);
            self::fail('Below the seats taken.');
        } catch (InvalidWorkshop $invalidWorkshop) {
            self::assertSame('The session holds at least the 3 seats taken or offered.', $invalidWorkshop->getMessage());
        }

        self::assertSame(3, $session->capacity());
        self::assertSame(['SessionResized '.$session->id], $this->events());
    }

    public function testTheCapacityNeverGoesBelowTheSeatsTakenAndOffered(): void
    {
        $session = $this->session(6);
        $this->service()->register($session->id, 'a', 3);
        $claims = new FixedClaims($this->registrations, claimed: 5, offered: 2);
        $service = $this->service(claims: $claims);

        self::assertSame(5, $service->resize($session->id, 5)->capacity());
        self::assertSame(['offered '.$session->id.' locked'], $claims->asked);

        try {
            $service->resize($session->id, 4);
            self::fail('Below the seats taken and offered.');
        } catch (InvalidWorkshop $invalidWorkshop) {
            self::assertSame('The session holds at least the 5 seats taken or offered.', $invalidWorkshop->getMessage());
        }

        self::assertSame(5, $session->capacity());
    }

    public function testASessionIsCancelledUnderTheLockAndItsRegistrationsKept(): void
    {
        $session = $this->session(1);
        $service = $this->service();
        $a = $service->register($session->id, 'a', 1);
        $this->events->events = [];
        $this->registrations->locked = [];
        $saves = $this->sessions->saves;

        $service->cancelSession($session->id);

        self::assertSame(SessionStatus::Cancelled, $session->status());
        self::assertSame(RegistrationStatus::Confirmed, $a->status());
        self::assertSame([$session->id], $this->registrations->locked);
        self::assertSame($saves + 1, $this->sessions->saves);
        self::assertSame(['SessionCancelled '.$session->id], $this->events());
        $event = $this->recorded(0);
        self::assertInstanceOf(SessionCancelled::class, $event);
        self::assertSame($session->workshop()->id, $event->workshopId);

        // Its registrations are still cancelled one by one.
        $service->cancel($a->id);
        self::assertSame(['SessionCancelled '.$session->id, 'RegistrationCancelled '.$a->id], $this->events());
    }

    public function testASessionIsMovedAndItsWindowChanged(): void
    {
        $session = $this->session();
        $service = $this->service();
        $saves = $this->sessions->saves;

        $service->reschedule($session->id, $this->at('2026-11-12 14:00'), $this->at('2026-11-12 16:00'));
        self::assertSame('2026-11-12T11:00:00-05:00', $session->span()->end->format(\DATE_ATOM));
        self::assertSame(['SessionRescheduled '.$session->id], $this->events());
        self::assertInstanceOf(SessionRescheduled::class, $this->recorded(0));

        $service->openRegistration($session->id, $this->at('2026-11-03'), $this->at('2026-11-11'));
        self::assertSame('2026-11-10T19:00:00-05:00', $session->closesAt()?->format(\DATE_ATOM));
        self::assertSame('2026-11-02T19:00:00-05:00', $session->opensAt()?->format(\DATE_ATOM));
        self::assertSame($saves + 2, $this->sessions->saves);
        self::assertCount(1, $this->events->events);
    }

    public function testWithoutADispatcherNothingIsHeard(): void
    {
        $session = $this->session();
        $service = new Workshops($this->workshops, $this->sessions, $this->registrations, $this->clock);

        self::assertSame(RegistrationStatus::Confirmed, $service->register($session->id, 'a')->status());
        self::assertSame(1, $service->register($session->id, 'b')->seats());
        self::assertSame([], $this->events->events);
    }

    public function testTheDefaultsTakeTenSeatsAndClaimNone(): void
    {
        $session = $this->session(10);
        $service = new Workshops($this->workshops, $this->sessions, $this->registrations, $this->clock);

        self::assertSame(10, $service->register($session->id, 'a', 10)->seats());
        self::assertEquals(new SeatCount(10, 10, 0), $service->seats($session->id));
        self::assertSame(10, $service->resize($session->id, 10)->capacity());

        $other = $this->session(20);
        $this->expectExceptionObject(new RegistrationRefused(RefusalReason::TooManySeats, ['max' => 10]));
        $service->register($other->id, 'a', 11);
    }
}
