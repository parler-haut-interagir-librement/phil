<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Domain\Event\EntryJoined;
use Phil\WaitlistBundle\Domain\Event\EntryLeft;
use Phil\WaitlistBundle\Domain\Event\OfferAccepted;
use Phil\WaitlistBundle\Domain\Event\PlaceOffered;
use Phil\WaitlistBundle\Domain\Model\Waitlist;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;
use Phil\WorkshopsBundle\Application\Exception\RegistrationRefused;
use Phil\WorkshopsBundle\Application\SeatCount;
use Phil\WorkshopsBundle\Application\Workshops;
use Phil\WorkshopsBundle\Domain\Contract\RegistrationRepository;
use Phil\WorkshopsBundle\Domain\Event\RegistrationCancelled;
use Phil\WorkshopsBundle\Domain\Event\RegistrationConfirmed;
use Phil\WorkshopsBundle\Domain\Exception\InvalidWorkshop;
use Phil\WorkshopsBundle\Domain\Model\WorkshopSession;
use Phil\WorkshopsBundle\Domain\Status\RefusalReason;
use Phil\WorkshopsBundle\Domain\Status\RegistrationStatus;
use Phil\WorkshopsBundle\Infrastructure\Translation\RefusalMessages;
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlistListener;
use Phil\WorkshopsBundle\Integration\Waitlist\SessionWaitlists;
use Phil\WorkshopsBundle\Integration\Waitlist\WaitlistSeatClaims;
use Phil\WorkshopsBundle\PhilWorkshopsBundle;
use Phil\WorkshopsBundle\Tests\Functional\App\RecordedEvents;
use Phil\WorkshopsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(PhilWorkshopsBundle::class)]
#[CoversClass(SessionWaitlists::class)]
#[CoversClass(SessionWaitlistListener::class)]
#[CoversClass(WaitlistSeatClaims::class)]
final class WorkshopsBundleTest extends KernelTestCase
{
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

    /**
     * @return list<class-string> the classes of the events heard, in order
     */
    private function heard(): array
    {
        return array_map(static fn (object $event): string => $event::class, $this->service(RecordedEvents::class, RecordedEvents::class)->events);
    }

    private function session(int $capacity): WorkshopSession
    {
        $workshops = $this->service(Workshops::class, Workshops::class);
        $start = new DateTimeImmutable('+10 days');

        return $workshops->schedule($workshops->createWorkshop('Peindre des figurines')->id, $start, $start->modify('+3 hours'), 'America/Toronto', $capacity);
    }

    public function testAFullSessionsWaitingListOffersTheFreedSeatsInTurnOnDoctrine(): void
    {
        $workshops = $this->service(Workshops::class, Workshops::class);
        $lists = $this->service(SessionWaitlists::class, SessionWaitlists::class);
        $waitlists = $this->service(Waitlists::class, Waitlists::class);
        $session = $this->session(2);

        $first = $workshops->register($session->id, 'account-1', 2);

        try {
            $workshops->register($session->id, 'account-2', 1);
            self::fail('The session is full.');
        } catch (RegistrationRefused $registrationRefused) {
            self::assertSame(RefusalReason::Full, $registrationRefused->reason);
        }

        $single = $lists->join($session->id, 'account-2', 1);
        $group = $lists->join($session->id, 'account-3', 2);
        $last = $lists->join($session->id, 'account-4', 1);
        self::assertSame($single->id, $lists->join($session->id, 'account-2', 1)->id);
        $list = $lists->of($session->id);
        self::assertInstanceOf(Waitlist::class, $list);
        self::assertSame([SessionWaitlists::SUBJECT, $session->id], [$list->subjectType(), $list->subjectId()]);
        self::assertEquals(new SeatCount(2, 2, 4), $workshops->seats($session->id));

        // Two seats free up: the first in line is offered one, the group of two waits for both, the seat is held.
        $workshops->cancel($first->id);
        $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class)->clear();

        self::assertSame([EntryStatus::Offered, EntryStatus::Waiting, EntryStatus::Waiting], [$waitlists->entry($single->id)->status(), $waitlists->entry($group->id)->status(), $waitlists->entry($last->id)->status()]);
        $count = $waitlists->count($list->id);
        self::assertSame([3, 1, 1], [$count->waiting, $count->offered, $count->held]);
        self::assertSame(0, $workshops->seats($session->id)->available);

        try {
            $workshops->register($session->id, 'account-5', 1);
            self::fail('Nobody skips the line.');
        } catch (RegistrationRefused $registrationRefused) {
            self::assertSame(RefusalReason::Full, $registrationRefused->reason);
        }

        $accepted = $lists->accept($single->id);
        self::assertSame(['account-2', 1, RegistrationStatus::Confirmed], [$accepted->participant(), $accepted->seats(), $accepted->status()]);
        self::assertSame(EntryStatus::Accepted, $waitlists->entry($single->id)->status());
        self::assertSame($accepted->id, $lists->accept($single->id)->id);

        // The group leaves: the seat held for it goes to the one behind.
        $waitlists->leave($group->id);
        self::assertSame(EntryStatus::Offered, $waitlists->entry($last->id)->status());

        $registration = $lists->accept($last->id);
        self::assertEquals(new SeatCount(2, 2, 0), $workshops->seats($session->id));
        self::assertSame(RegistrationStatus::Confirmed, $this->service(RegistrationRepository::class, RegistrationRepository::class)->get($registration->id)?->status());

        $heard = $this->heard();
        self::assertSame(3, array_count_values($heard)[RegistrationConfirmed::class] ?? 0);
        self::assertSame(2, array_count_values($heard)[PlaceOffered::class] ?? 0);
        self::assertSame(2, array_count_values($heard)[OfferAccepted::class] ?? 0);
        self::assertSame(3, array_count_values($heard)[EntryJoined::class] ?? 0);
        self::assertContains(RegistrationCancelled::class, $heard);
        self::assertContains(EntryLeft::class, $heard);
    }

    public function testMoreCapacityIsOfferedAndLessIsTakenBack(): void
    {
        $workshops = $this->service(Workshops::class, Workshops::class);
        $lists = $this->service(SessionWaitlists::class, SessionWaitlists::class);
        $waitlists = $this->service(Waitlists::class, Waitlists::class);
        $session = $this->session(3);
        $workshops->register($session->id, 'account-1', 3);
        $group = $lists->join($session->id, 'account-2', 3);

        $workshops->resize($session->id, 5);
        $list = $lists->of($session->id);
        self::assertInstanceOf(Waitlist::class, $list);
        $listId = $list->id;
        self::assertSame([3, 0, 2], [$waitlists->count($listId)->waiting, $waitlists->count($listId)->offered, $waitlists->count($listId)->held]);

        $workshops->resize($session->id, 4);
        self::assertSame(1, $waitlists->count($listId)->held);

        $workshops->resize($session->id, 6);
        self::assertSame(EntryStatus::Offered, $waitlists->entry($group->id)->status());
        self::assertSame(0, $waitlists->count($listId)->held);

        try {
            $workshops->resize($session->id, 5);
            self::fail('The seats offered are kept.');
        } catch (InvalidWorkshop $invalidWorkshop) {
            self::assertSame('The session holds at least the 6 seats taken or offered.', $invalidWorkshop->getMessage());
        }

        // A cancelled session closes its list.
        $workshops->cancelSession($session->id);
        self::assertTrue($waitlists->waitlist($listId)->isClosed());

        try {
            $lists->accept($group->id);
            self::fail('The session takes no more registrations.');
        } catch (RegistrationRefused $registrationRefused) {
            self::assertSame(RefusalReason::Cancelled, $registrationRefused->reason);
        }
    }

    public function testTheOptionsAndTheTranslationsReachTheApplication(): void
    {
        $workshops = $this->service(Workshops::class, Workshops::class);
        $start = new DateTimeImmutable('+10 days');
        $session = $workshops->schedule($workshops->createWorkshop('Atelier')->id, $start, $start->modify('+1 hour'), 'UTC', 20);

        try {
            $workshops->register($session->id, 'account-1', 5);
            self::fail('The option bounds the seats.');
        } catch (RegistrationRefused $registrationRefused) {
            self::assertSame('De 1 à 4 places peuvent être prises par inscription.', $this->service(RefusalMessages::class, RefusalMessages::class)->message($registrationRefused));
        }
    }
}
