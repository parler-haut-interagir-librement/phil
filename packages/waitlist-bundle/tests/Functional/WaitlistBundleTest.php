<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Domain\Contract\EntryRepository;
use Phil\WaitlistBundle\Domain\Event\EntryJoined;
use Phil\WaitlistBundle\Domain\Event\OfferAccepted;
use Phil\WaitlistBundle\Domain\Event\OfferLapsed;
use Phil\WaitlistBundle\Domain\Event\PlaceOffered;
use Phil\WaitlistBundle\Domain\Status\EntryStatus;
use Phil\WaitlistBundle\PhilWaitlistBundle;
use Phil\WaitlistBundle\Tests\Functional\App\RecordedEvents;
use Phil\WaitlistBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

#[CoversClass(PhilWaitlistBundle::class)]
final class WaitlistBundleTest extends KernelTestCase
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

    public function testAnApplicationOffersAcceptsAndLapsesOnDoctrineAndNotifies(): void
    {
        $waitlists = $this->service(Waitlists::class, Waitlists::class);
        $waitlist = $waitlists->open('session', 'S-1');
        $first = $waitlists->join($waitlist->id, 'account-1');
        $second = $waitlists->join($waitlist->id, 'account-2', 2);

        $waitlists->release($waitlist->id, 1);
        $waitlists->accept($first->id);
        $waitlists->release($waitlist->id, 2);
        $this->service('clock', MockClock::class)->modify('+10 minutes');

        self::assertInstanceOf(KernelInterface::class, self::$kernel);
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('phil:waitlist:lapse'));
        self::assertSame(0, $tester->execute([]));

        $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class)->clear();
        $entries = $this->service(EntryRepository::class, EntryRepository::class);
        self::assertSame([EntryStatus::Accepted, EntryStatus::Lapsed], [$entries->get($first->id)?->status(), $entries->get($second->id)?->status()]);
        self::assertSame(2, $waitlists->count($waitlist->id)->held);
        self::assertSame(
            [EntryJoined::class, EntryJoined::class, PlaceOffered::class, OfferAccepted::class, PlaceOffered::class, OfferLapsed::class],
            array_map(static fn (object $event): string => $event::class, $this->service(RecordedEvents::class, RecordedEvents::class)->events),
        );

        $notifications = $this->service(NotificationRepository::class, NotificationRepository::class);
        self::assertSame(['waitlist.offered'], array_map(static fn (Notification $notification): string => $notification->type, $notifications->latest('account-1', 10)));
        self::assertSame(['waitlist.lapsed', 'waitlist.offered'], array_map(static fn (Notification $notification): string => $notification->type, $notifications->latest('account-2', 10)));
    }
}
