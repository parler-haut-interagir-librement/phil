<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\WaitlistBundle\Application\Exception\NotFound as EntryNotFound;
use Phil\WaitlistBundle\Application\Exception\RefusalReason as WaitlistRefusal;
use Phil\WaitlistBundle\Application\Exception\WaitlistRefused;
use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;
use Phil\WorkshopsBundle\Application\Exception\NotFound;
use Phil\WorkshopsBundle\Application\Exception\RegistrationRefused;
use Phil\WorkshopsBundle\Application\SeatCount;
use Phil\WorkshopsBundle\Application\Workshops;
use Phil\WorkshopsBundle\Domain\Contract\RegistrationRepository;
use Phil\WorkshopsBundle\Domain\Event\RegistrationConfirmed;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Model\Registration;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RefusalReason;
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlistListener;
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlists;
use Phil\WorkshopsBundle\Integration\Waitlist\WaitlistSeatClaims;
use Phil\WorkshopsBundle\Tests\Functional\App\RecordedEvents;
use Phil\WorkshopsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(SessionWaitlists::class)]
#[CoversClass(SessionWaitlistListener::class)]
#[CoversClass(WaitlistSeatClaims::class)]
final class SessionWaitlistsTest extends KernelTestCase
{
    private Workshops $workshops;

    private SessionWaitlists $lists;

    private Waitlists $waitlists;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->workshops = $this->service(Workshops::class, Workshops::class);
        $this->lists = $this->service(SessionWaitlists::class, SessionWaitlists::class);
        $this->waitlists = $this->service(Waitlists::class, Waitlists::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }

    private function session(int $capacity): WorkshopSession
    {
        $start = new DateTimeImmutable('+10 days');

        return $this->workshops->schedule($this->workshops->createWorkshop('Atelier')->id, $start, $start->modify('+2 hours'), 'UTC', $capacity);
    }

    private function list(WorkshopSession $session): Waitlist
    {
        return $this->lists->of($session->id) ?? self::fail('No list.');
    }

    /**
     * @return array{int, int, int} the places waiting, offered and held
     */
    private function places(WorkshopSession $session): array
    {
        $count = $this->waitlists->count($this->list($session)->id);

        return [$count->waiting, $count->offered, $count->held];
    }

    /**
     * A registration saved as it is, past the checks of the service.
     */
    private function saveRegistration(WorkshopSession $session, string $participant, int $seats): Registration
    {
        $registration = Registration::confirmed($session, $participant, $seats, new DateTimeImmutable());
        $this->service(RegistrationRepository::class, RegistrationRepository::class)->save($registration);

        return $registration;
    }

    public function testJoiningIsRefusedAsRegisteringIs(): void
    {
        $session = $this->session(2);
        $this->workshops->register($session->id, 'a', 1);

        $refusals = [
            [static fn (SessionWaitlists $lists): mixed => $lists->join($session->id, 'a', 1), RefusalReason::Registered, []],
            [static fn (SessionWaitlists $lists): mixed => $lists->join($session->id, 'b', 3), RefusalReason::TooManySeats, ['max' => 2]],
        ];

        $large = $this->session(10);
        $refusals[] = [static fn (SessionWaitlists $lists): mixed => $lists->join($large->id, 'b', 5), RefusalReason::TooManySeats, ['max' => 4]];

        foreach ($refusals as [$join, $reason, $parameters]) {
            try {
                $join($this->lists);
                self::fail('The join is refused.');
            } catch (RegistrationRefused $registrationRefused) {
                self::assertSame($reason, $registrationRefused->reason);
                self::assertSame($parameters, $registrationRefused->parameters);
            }
        }

        self::assertSame(4, $this->lists->join($large->id, 'b', 4)->places());
        self::assertSame(2, $this->lists->join($session->id, 'b', 2)->places());
        self::assertNull($this->lists->of($this->session(1)->id));

        $this->expectExceptionObject(NotFound::session('nope'));
        $this->lists->join('nope', 'b');
    }

    public function testASessionThatTakesNoRegistrationTakesNoEntry(): void
    {
        $session = $this->session(1);
        $this->workshops->openRegistration($session->id, new DateTimeImmutable('+1 day'), null);

        try {
            $this->lists->join($session->id, 'a');
            self::fail('Registration has not opened.');
        } catch (RegistrationRefused $registrationRefused) {
            self::assertSame(RefusalReason::NotOpen, $registrationRefused->reason);
        }

        self::assertNull($this->lists->of($session->id));
    }

    public function testAnEntryIsOfferedTheSeatsNobodyTookAtOnce(): void
    {
        $session = $this->session(3);
        $this->workshops->register($session->id, 'a', 1);

        $entry = $this->lists->join($session->id, 'b', 2);

        self::assertSame(EntryStatus::Offered, $this->waitlists->entry($entry->id)->status());
        self::assertSame([0, 2, 0], $this->places($session));
        self::assertEquals(new SeatCount(3, 1, 2), $this->workshops->seats($session->id));

        $registration = $this->lists->accept($entry->id);
        self::assertSame(2, $registration->seats());
        self::assertEquals(new SeatCount(3, 3, 0), $this->workshops->seats($session->id));
        $events = $this->service(RecordedEvents::class, RecordedEvents::class)->events;
        $last = array_last($events);
        self::assertInstanceOf(RegistrationConfirmed::class, $last);
        self::assertSame([$registration->id, $session->id], [$last->registrationId, $last->sessionId]);
    }

    public function testTheClaimsCountTheOffersAndTheMoreOfWaitingAndHeld(): void
    {
        $session = $this->session(3);
        $first = $this->workshops->register($session->id, 'a', 3);
        $this->lists->join($session->id, 'b', 1);
        $this->lists->join($session->id, 'c', 3);
        $this->lists->join($session->id, 'd', 1);
        self::assertEquals(new SeatCount(3, 3, 5), $this->workshops->seats($session->id));

        // Three seats free up: one offered to b, two held for c who needs three.
        $this->workshops->cancel($first->id);
        self::assertSame([4, 1, 2], $this->places($session));
        self::assertEquals(new SeatCount(3, 0, 5), $this->workshops->seats($session->id));

        $claims = $this->service(WaitlistSeatClaims::class, WaitlistSeatClaims::class);
        self::assertSame(1, $claims->offered($session));
        self::assertSame(0, $claims->offered($this->session(1)));
        self::assertSame(0, $claims->claimed($this->session(1)));
    }

    public function testPlacesHeldForNobodyGoBackToTheSession(): void
    {
        $session = $this->session(3);
        $this->workshops->register($session->id, 'a', 3);
        $group = $this->lists->join($session->id, 'group', 3);
        $this->workshops->resize($session->id, 5);
        self::assertSame([3, 0, 2], $this->places($session));

        $this->waitlists->leave($group->id);

        self::assertSame([0, 0, 0], $this->places($session));
        self::assertSame(2, $this->workshops->register($session->id, 'b', 2)->seats());
    }

    public function testPlacesHeldWhileNobodyWaitsAreGivenBackBeforeAnEntryJoins(): void
    {
        $session = $this->session(3);
        $this->workshops->register($session->id, 'a', 3);
        $group = $this->lists->join($session->id, 'group', 3);
        $this->workshops->resize($session->id, 4);
        $this->waitlists->leave($group->id);

        // A place released while nobody waits stays held, and a registration takes the seat meanwhile.
        $this->waitlists->release($this->list($session)->id, 1);
        $this->saveRegistration($session, 'late', 1);
        self::assertSame([0, 0, 1], $this->places($session));

        $entry = $this->lists->join($session->id, 'b', 1);

        self::assertSame(EntryStatus::Waiting, $this->waitlists->entry($entry->id)->status());
        self::assertSame([1, 0, 0], $this->places($session));
    }

    public function testAcceptingNeedsAnOfferOnASessionsListAndFreeSeats(): void
    {
        $session = $this->session(1);
        $this->workshops->register($session->id, 'a', 1);
        $waiting = $this->lists->join($session->id, 'b', 1);

        try {
            $this->lists->accept($waiting->id);
            self::fail('No offer.');
        } catch (WaitlistRefused $waitlistRefused) {
            self::assertSame(WaitlistRefusal::NoOffer, $waitlistRefused->reason);
        }

        $other = $this->waitlists->open('event', 'event-1');
        $foreign = $this->waitlists->join($other->id, 'b', 1);

        try {
            $this->lists->accept($foreign->id);
            self::fail('Not a session.');
        } catch (InvalidWorkshop $invalidWorkshop) {
            self::assertSame('The entry is not on the waiting list of a session.', $invalidWorkshop->getMessage());
        }

        $orphan = $this->waitlists->join($this->waitlists->open(SessionWaitlists::SUBJECT, 'gone')->id, 'b', 1);

        try {
            $this->lists->accept($orphan->id);
            self::fail('No session.');
        } catch (NotFound $notFound) {
            self::assertSame('No session "gone".', $notFound->getMessage());
        }

        $this->expectExceptionObject(EntryNotFound::entry('nope'));
        $this->lists->accept('nope');
    }

    public function testAnOfferIsNotAcceptedIntoSeatsTakenMeanwhile(): void
    {
        $session = $this->session(2);
        $first = $this->workshops->register($session->id, 'a', 2);
        $entry = $this->lists->join($session->id, 'b', 1);
        $this->workshops->cancel($first->id);
        self::assertSame(EntryStatus::Offered, $this->waitlists->entry($entry->id)->status());
        $this->saveRegistration($session, 'c', 2);

        try {
            $this->lists->accept($entry->id);
            self::fail('The seats are taken.');
        } catch (RegistrationRefused $registrationRefused) {
            self::assertSame(RefusalReason::Full, $registrationRefused->reason);
        }

        self::assertSame(EntryStatus::Offered, $this->waitlists->entry($entry->id)->status());
    }

    public function testAnOfferFillsTheLastSeatExactly(): void
    {
        $session = $this->session(2);
        $first = $this->workshops->register($session->id, 'a', 2);
        $entry = $this->lists->join($session->id, 'b', 1);
        $this->workshops->cancel($first->id);
        $this->saveRegistration($session, 'c', 1);

        self::assertSame(1, $this->lists->accept($entry->id)->seats());
        self::assertEquals(new SeatCount(2, 2, 0), $this->workshops->seats($session->id));
    }

    public function testAParticipantRegisteredMeanwhileKeepsTheirRegistrationAndLeavesTheList(): void
    {
        $session = $this->session(2);
        $first = $this->workshops->register($session->id, 'a', 2);
        $entry = $this->lists->join($session->id, 'b', 1);
        $this->workshops->cancel($first->id);
        $registration = $this->saveRegistration($session, 'b', 1);

        self::assertSame($registration->id, $this->lists->accept($entry->id)->id);
        self::assertSame(EntryStatus::Left, $this->waitlists->entry($entry->id)->status());
        self::assertSame($registration->id, $this->lists->accept($entry->id)->id);
        self::assertSame(EntryStatus::Left, $this->waitlists->entry($entry->id)->status());
    }

    public function testNothingIsReleasedOnceRegistrationHasClosed(): void
    {
        $session = $this->session(1);
        $first = $this->workshops->register($session->id, 'a', 1);
        $entry = $this->lists->join($session->id, 'b', 1);
        $this->workshops->openRegistration($session->id, null, new DateTimeImmutable('-1 minute'));

        $this->workshops->cancel($first->id);

        self::assertSame(EntryStatus::Waiting, $this->waitlists->entry($entry->id)->status());
        self::assertSame([1, 0, 0], $this->places($session));

        try {
            $this->lists->accept($entry->id);
            self::fail('Registration has closed.');
        } catch (WaitlistRefused $waitlistRefused) {
            self::assertSame(WaitlistRefusal::NoOffer, $waitlistRefused->reason);
        }
    }

    public function testBalancingAndClosingLeaveTheOthersAlone(): void
    {
        $session = $this->session(1);
        $this->lists->balance($session->id);
        $this->lists->close($session->id);
        self::assertNull($this->lists->of($session->id));

        $this->lists->balanceList('nope');
        $foreign = $this->waitlists->open('event', 'event-1');
        $this->waitlists->join($foreign->id, 'a', 1);
        $this->waitlists->release($foreign->id, 2);

        $this->lists->balanceList($foreign->id);
        self::assertSame(1, $this->waitlists->count($foreign->id)->held);

        $orphan = $this->waitlists->open(SessionWaitlists::SUBJECT, 'gone');
        $this->lists->balanceList($orphan->id);

        $this->lists->join($session->id, 'a', 1);
        $this->lists->close($session->id);
        self::assertTrue($this->list($session)->isClosed());
        $this->lists->close($session->id);
        self::assertTrue($this->list($session)->isClosed());

        $this->expectExceptionObject(NotFound::session('nope'));
        $this->lists->balance('nope');
    }

    public function testTheListenerBalancesTheListOfTheSessionOfAnEntryThatLeft(): void
    {
        $session = $this->session(2);
        $this->workshops->register($session->id, 'a', 2);
        $group = $this->lists->join($session->id, 'group', 2);
        $single = $this->lists->join($session->id, 'single', 1);
        $this->workshops->resize($session->id, 3);
        self::assertSame(EntryStatus::Waiting, $this->waitlists->entry($single->id)->status());

        $this->waitlists->leave($group->id);

        self::assertSame(EntryStatus::Offered, $this->waitlists->entry($single->id)->status());
    }
}
