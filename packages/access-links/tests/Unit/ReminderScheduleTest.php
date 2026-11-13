<?php

declare(strict_types=1);

namespace Phil\AccessLinks\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessLinks\Exception\InvalidConfiguration;
use Phil\AccessLinks\ReminderSchedule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReminderSchedule::class)]
final class ReminderScheduleTest extends TestCase
{
    public function testTheRemindersFallDueAfterCreationInOrder(): void
    {
        $schedule = new ReminderSchedule(['P3D', 'P7D']);
        $created = new DateTimeImmutable('2026-11-13 10:00:00 UTC');
        $closes = new DateTimeImmutable('2026-12-13 10:00:00 UTC');

        self::assertSame(2, $schedule->count());
        self::assertSame('2026-11-16 10:00:00', $schedule->next($created, 0, $closes)?->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-20 10:00:00', $schedule->next($created, 1, $closes)?->format('Y-m-d H:i:s'));
        self::assertNull($schedule->next($created, 2, $closes));
    }

    public function testNoReminderFallsAtTheClosureOrAfter(): void
    {
        $schedule = new ReminderSchedule(['P3D', 'P7D']);
        $created = new DateTimeImmutable('2026-11-13 10:00:00 UTC');

        self::assertNull($schedule->next($created, 1, new DateTimeImmutable('2026-11-20 10:00:00 UTC')));
        self::assertNotNull($schedule->next($created, 1, new DateTimeImmutable('2026-11-20 10:00:01 UTC')));
    }

    public function testNoScheduleSendsNoReminder(): void
    {
        $schedule = new ReminderSchedule();

        self::assertSame(0, $schedule->count());
        self::assertNull($schedule->next(new DateTimeImmutable('2026-11-13'), 0, new DateTimeImmutable('2027-11-13')));
    }

    public function testTenRemindersAtTheMost(): void
    {
        $ten = array_map(static fn (int $day): string => 'P'.$day.'D', range(1, 10));

        self::assertSame(10, new ReminderSchedule($ten)->count());

        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains('An invitation has 10 reminders at the most.');

        new ReminderSchedule([...$ten, 'P11D']);
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'not a duration' => [['3 days'], '"3 days" is not an ISO 8601 duration, such as P3D.'];
        yield 'zero' => [['PT0S'], 'The reminders of an invitation fall due in increasing order, after it is sent.'];
        yield 'out of order' => [['P7D', 'P3D'], 'The reminders of an invitation fall due in increasing order, after it is sent.'];
        yield 'twice the same' => [['P3D', 'PT72H'], 'The reminders of an invitation fall due in increasing order, after it is sent.'];
    }

    /**
     * @param list<string> $delays
     */
    #[DataProvider('invalid')]
    public function testItRefusesASchedule(array $delays, string $message): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessageIsOrContains($message);

        new ReminderSchedule($delays);
    }
}
