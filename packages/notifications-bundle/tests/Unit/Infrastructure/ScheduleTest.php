<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\NotificationsBundle\Application\Message\PurgeNotifications;
use Phil\NotificationsBundle\Application\Message\SendDigests;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Retention;
use Phil\NotificationsBundle\Domain\Model\Notification;
use Phil\NotificationsBundle\Infrastructure\Console\PurgeCommand;
use Phil\NotificationsBundle\Infrastructure\Memory\InMemoryNotificationRepository;
use Phil\NotificationsBundle\Infrastructure\Scheduler\NotificationSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

#[CoversClass(NotificationSchedule::class)]
#[CoversClass(PurgeCommand::class)]
final class ScheduleTest extends TestCase
{
    public function testTheScheduleSendsTheDigestsAndPurges(): void
    {
        $provider = new NotificationSchedule('06:30', 'friday', '02:15', 'America/Toronto');
        $schedule = $provider->getSchedule();
        $messages = $schedule->getRecurringMessages();

        self::assertSame($schedule, $provider->getSchedule());
        self::assertCount(3, $messages);
        self::assertEquals(new SendDigests(DigestFrequency::Daily), $this->message($messages[0]));
        self::assertEquals(new SendDigests(DigestFrequency::Weekly), $this->message($messages[1]));
        self::assertEquals(new PurgeNotifications(), $this->message($messages[2]));

        $zone = new DateTimeZone('America/Toronto');
        $after = new DateTimeImmutable('tomorrow 12:00', $zone);
        self::assertSame(new DateTimeImmutable('tomorrow +1 day 06:30', $zone)->format('Y-m-d H:i e'), $this->next($messages[0]->getTrigger(), $after));
        self::assertSame(new DateTimeImmutable('tomorrow +1 day 02:15', $zone)->format('Y-m-d H:i e'), $this->next($messages[2]->getTrigger(), $after));
        $weekly = $this->next($messages[1]->getTrigger(), $after->modify('+1 week'));
        self::assertSame('Friday 06:30 America/Toronto', new DateTimeImmutable($weekly)->setTimezone($zone)->format('l H:i').' America/Toronto');
        self::assertStringEndsWith('America/Toronto', $weekly);
    }

    public function testTheScheduleHasDefaults(): void
    {
        $messages = new NotificationSchedule()->getSchedule()->getRecurringMessages();
        $after = new DateTimeImmutable('tomorrow 12:00 UTC');

        self::assertSame(new DateTimeImmutable('tomorrow +1 day 07:00 UTC')->format('Y-m-d H:i e'), $this->next($messages[0]->getTrigger(), $after));
        self::assertSame('Monday 07:00 UTC', new DateTimeImmutable($this->next($messages[1]->getTrigger(), $after->modify('+1 week')))->format('l H:i e'));
        self::assertSame(new DateTimeImmutable('tomorrow +1 day 03:30 UTC')->format('Y-m-d H:i e'), $this->next($messages[2]->getTrigger(), $after));
    }

    public function testThePartsOfTheScheduleMayBeTurnedOff(): void
    {
        $purgeOnly = new NotificationSchedule(digests: false)->getSchedule()->getRecurringMessages();
        self::assertCount(1, $purgeOnly);
        self::assertEquals(new PurgeNotifications(), $this->message($purgeOnly[0]));

        $digestsOnly = new NotificationSchedule(purge: false)->getSchedule()->getRecurringMessages();
        self::assertCount(2, $digestsOnly);
    }

    public function testThePurgeCommandSaysHowManyWereDeleted(): void
    {
        $clock = new MockClock('2026-11-08 12:00:00 UTC');
        $repository = new InMemoryNotificationRepository();
        $repository->record(new Notification('a', 'welcome', '42', null, $clock->now()->modify('-400 days')), false);

        $output = new BufferedOutput();

        self::assertSame(Command::SUCCESS, new PurgeCommand(new Retention($repository, $clock, 90, 365))(new SymfonyStyle(new ArrayInput([]), $output)));
        self::assertStringContainsString('1 notifications deleted.', $output->fetch());
    }

    private function message(RecurringMessage $message): object
    {
        $context = new MessageContext('phil_notifications', $message->getId(), $message->getTrigger(), new DateTimeImmutable());

        foreach ($message->getMessages($context) as $one) {
            return $one;
        }

        self::fail('No message.');
    }

    private function next(TriggerInterface $trigger, DateTimeImmutable $after): string
    {
        return $trigger->getNextRunDate($after)?->format('Y-m-d H:i e') ?? '';
    }
}
