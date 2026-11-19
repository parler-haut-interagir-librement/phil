<?php

declare(strict_types=1);

namespace Phil\ReservationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ReservationBundle\Application\Message\SendReservationReminder;
use Phil\ReservationBundle\Application\Reminders;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Domain\Model\BookableResource;
use Phil\ReservationBundle\Infrastructure\Console\ExpireHoldsCommand;
use Phil\ReservationBundle\Infrastructure\Console\SendRemindersCommand;
use Phil\ReservationBundle\Infrastructure\Messenger\MessengerReminderSender;
use Phil\ReservationBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryReservations;
use Phil\ReservationBundle\Tests\Unit\Fixture\InMemoryResources;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ExpireHoldsCommand::class)]
#[CoversClass(SendRemindersCommand::class)]
#[CoversClass(MessengerReminderSender::class)]
#[CoversClass(SendReservationReminder::class)]
final class CommandsTest extends TestCase
{
    public function testHoldsAreExpiredByBatch(): void
    {
        $resources = new InMemoryResources();
        $reservations = new InMemoryReservations();
        $clock = new FrozenClock(new DateTimeImmutable('2026-11-01 12:00 UTC'));
        $room = BookableResource::create('Salle');
        $resources->save($room);
        $service = new Reservations($resources, $reservations, $clock);
        $service->hold($room->id, new DateTimeImmutable('2026-11-10 09:00 UTC'), new DateTimeImmutable('2026-11-10 10:00 UTC'));

        $clock->now = new DateTimeImmutable('2026-11-02 12:00 UTC');

        $command = new ExpireHoldsCommand($service, 25);
        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("1 holds expired.\n", $tester->getDisplay());
        self::assertSame([25], $reservations->limits);
        self::assertSame('phil:reservation:expire-holds', $command->getName());
    }

    public function testRemindersAreSentAsMessages(): void
    {
        $toronto = new DateTimeZone('America/Toronto');
        $resources = new InMemoryResources();
        $reservations = new InMemoryReservations();
        $clock = new FrozenClock(new DateTimeImmutable('2026-11-10 08:30', $toronto));
        $room = BookableResource::create('Salle', 1, 'America/Toronto');
        $room->remindBefore(60);

        $resources->save($room);
        $booked = new Reservations($resources, $reservations, $clock)->book($room->id, new DateTimeImmutable('2026-11-10 09:00', $toronto), new DateTimeImmutable('2026-11-10 10:00', $toronto));
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };

        $command = new SendRemindersCommand(new Reminders($resources, $reservations, new MessengerReminderSender($bus), $clock), 30);
        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("1 reminders sent.\n", $tester->getDisplay());
        self::assertEquals([new SendReservationReminder($booked->id, 60)], $bus->messages);
        self::assertSame([30], $reservations->limits);
        self::assertSame('phil:reservation:send-reminders', $command->getName());
    }
}
