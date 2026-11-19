<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Functional;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Application\View\Items;
use Phil\CalendarBundle\Application\View\Period;
use Phil\ReservationBundle\Application\Exception\ReservationRefused;
use Phil\ReservationBundle\Application\Message\SendReservationReminder;
use Phil\ReservationBundle\Application\Reminders;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Domain\Contract\ResourceRepository;
use Phil\ReservationBundle\Domain\Event\ReservationConfirmed;
use Phil\ReservationBundle\Domain\Event\ReservationHeld;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Domain\Status\ReservationStatus;
use Phil\ReservationBundle\Infrastructure\Form\ReservationType;
use Phil\ReservationBundle\Infrastructure\Translation\ViolationMessages;
use Phil\ReservationBundle\PhilReservationBundle;
use Phil\ReservationBundle\Tests\Functional\App\RecordedEvents;
use Phil\ReservationBundle\Tests\Functional\App\ReminderHandler;
use Phil\ReservationBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

#[CoversClass(PhilReservationBundle::class)]
final class ReservationBundleTest extends KernelTestCase
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

    public function testAReservationIsHeldConfirmedAndShownInTheCalendar(): void
    {
        $table = BookableResource::create('Table des dragons', 6, 'America/Toronto');
        $table->remindBefore(60 * 24 * 365);
        $this->service(ResourceRepository::class, ResourceRepository::class)->save($table);
        $reservations = $this->service(Reservations::class, Reservations::class);
        $start = new DateTimeImmutable('+10 days 19:00', new DateTimeZone('America/Toronto'));
        $end = $start->modify('+3 hours');

        $hold = $reservations->hold($table->id, $start, $end, 4, 'account-9');
        self::assertSame(ReservationStatus::Confirmed, $reservations->confirm($hold->id)->status());
        self::assertSame(
            [[ReservationHeld::class, $hold->id], [ReservationConfirmed::class, $hold->id]],
            array_map(static fn (ReservationHeld|ReservationConfirmed $event): array => [$event::class, $event->reservationId], $this->service(RecordedEvents::class, RecordedEvents::class)->events),
        );

        try {
            $reservations->book($table->id, $start, $end, 3);
            self::fail('Four and three make more than six.');
        } catch (ReservationRefused $reservationRefused) {
            self::assertSame(['Il ne reste que 2 unités à ce moment.'], $this->service(ViolationMessages::class, ViolationMessages::class)->messages($reservationRefused->decision));
        }

        $items = $this->service(Items::class, Items::class)->of(Period::of(CalendarView::Day, $start, new DateTimeZone('America/Toronto')));
        self::assertSame(['Table des dragons'], array_map(static fn (Item $item): string => $item->title, array_values(array_filter($items, static fn (Item $item): bool => 'reservation' === $item->source))));
        self::assertSame(1, $this->service(Reminders::class, Reminders::class)->send());
        self::assertSame([$hold->id], array_map(static fn (SendReservationReminder $message): string => $message->reservationId, $this->service(ReminderHandler::class, ReminderHandler::class)->handled));
    }

    public function testTheFormAndTheCommandsAreRegistered(): void
    {
        $table = BookableResource::create('Salle', 1, 'America/Toronto');
        $this->service(ResourceRepository::class, ResourceRepository::class)->save($table);

        $form = $this->service('test.form.factory', FormFactoryInterface::class)->create(ReservationType::class, null, ['resource' => $table]);
        self::assertTrue($form->has('start'));

        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        self::assertTrue($application->has('phil:reservation:expire-holds'));
        self::assertTrue($application->has('phil:reservation:send-reminders'));
    }
}
